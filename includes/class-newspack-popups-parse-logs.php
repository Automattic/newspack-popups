<?php
/**
 * Newspack Segmentation Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/segmentation/class-segmentation.php';

/**
 * Main Newspack Segmentation Plugin Class.
 */
final class Newspack_Popups_Parse_Logs {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Parse_Logs
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Segmentation Plugin Instance.
	 * Ensures only one instance of Newspack Segmentation Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Segmentation Plugin Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'newspack_popups_segmentation_cron_hook', [ __CLASS__, 'parse_visit_logs' ] );
		if ( ! wp_next_scheduled( 'newspack_popups_segmentation_cron_hook' ) ) {
			wp_schedule_event( time(), 'every_minute', 'newspack_popups_segmentation_cron_hook' );
		}
	}


	/**
	 * Add a CRON interval of one minute.
	 *
	 * @param object $schedules The schedules.
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['every_minute'] = [
			'interval' => 60,
			'display'  => esc_html__( 'Every Minute' ),
		];
		return $schedules;
	}

	/**
	 * Insert multiple rows in one DB transaction.
	 *
	 * @param string $table_name Table name.
	 * @param array  $rows Rows to insert.
	 * @param array  $column_names Names of the columns.
	 * @param array  $placeholder Row placeholder for wpdb->prepare (e.g. `(%s, %s)`).
	 */
	public static function bulk_db_insert( $table_name, $rows, $column_names, $placeholder ) {
		if ( 0 === count( $rows ) ) {
			return;
		}

		global $wpdb;

		$column_names = implode( ', ', $column_names );
		$query        = "INSERT INTO $table_name ($column_names) VALUES ";
		$query       .= implode(
			', ',
			array_map(
				function( $row ) use ( $placeholder ) {
					return $placeholder;
				},
				$rows
			)
		);

		// Flatten the rows two-dimensional array.
		$values = array_reduce(
			$rows,
			function( $acc, $arr ) {
				return array_merge( $acc, $arr );
			},
			[]
		);

		$sql = $wpdb->prepare( "$query ", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( $wpdb->query( $sql ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Parse the log file, write data to the DB, and remove the file.
	 */
	public static function parse_visit_logs() {
		global $wpdb;

		$start_time = microtime( true );

		$visits_table_name    = Segmentation::get_visits_table_name();
		$clients_table_name   = Segmentation::get_clients_table_name();
		$log_file_path        = Segmentation::LOG_FILE_PATH;
		$is_parsing_file_path = Segmentation::IS_PARSING_FILE_PATH;

		if ( file_exists( Segmentation::IS_PARSING_FILE_PATH ) ) {
			return;
		}

		file_put_contents( $is_parsing_file_path, '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		$log_file = fopen( $log_file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		$lines    = [];

		while ( ! feof( $log_file ) ) {
			$line = trim( fgets( $log_file ) );
			if ( ! empty( $line ) ) {
				$lines[] = $line;
			}
		}

		error_log( 'Parsing ' . count( $lines ) . ' lines of log file: ' . $log_file_path ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

		$lines = array_unique( $lines );

		$visits_rows = [];

		foreach ( $lines as $line ) {
			$result    = explode( ';', $line );
			$client_id = $result[0];
			$post_id   = $result[2];

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found_client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $clients_table_name WHERE client_id = %s", $client_id ) );

			if ( null === $found_client ) {
				$updates = [
					'client_id'  => $client_id,
					'created_at' => gmdate( 'Y-m-d' ),
					'updated_at' => gmdate( 'Y-m-d' ),
				];
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->insert(
					$clients_table_name,
					$updates
				);
			} else {
				$updates = [
					'updated_at' => gmdate( 'Y-m-d' ),
				];
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$clients_table_name,
					$updates,
					[ 'ID' => $found_client->id ]
				);
			}

			$existing_post_visits = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT * FROM $visits_table_name WHERE post_id = %s AND client_id = %s", $post_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			);
			if ( null === $existing_post_visits ) {
				$visits_rows[] = [ $client_id, $result[1], $post_id, $result[3] ];
			}
		}

		self::bulk_db_insert(
			$visits_table_name,
			$visits_rows,
			[
				'client_id',
				'created_at',
				'post_id',
				'categories_ids',
			],
			'( %s, %s, %s, %s )'
		);

		fclose( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		// Clear the log file.
		file_put_contents( $log_file_path, '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		// Remove the is-parsing file to enable logging again.
		unlink( $is_parsing_file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink

		error_log( 'parsing duration: ' . round( ( microtime( true ) - $start_time ) * 1000 ) . 'ms' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
Newspack_Popups_Parse_Logs::instance();
