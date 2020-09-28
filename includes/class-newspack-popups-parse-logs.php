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
		add_action( 'newspack_popups_segmentation_cron_hook', [ __CLASS__, 'parse_events_logs' ] );
		if ( ! wp_next_scheduled( 'newspack_popups_segmentation_cron_hook' ) ) {
			wp_schedule_event( time(), 'every_quarter_hour', 'newspack_popups_segmentation_cron_hook' );
		}
	}


	/**
	 * Add a CRON interval of one minute.
	 *
	 * @param object $schedules The schedules.
	 */
	public static function add_cron_interval( $schedules ) {
		$schedules['every_quarter_hour'] = [
			'interval' => 60 * 15,
			'display'  => esc_html__( 'Every Quarter-Hour' ),
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
	public static function parse_events_logs() {
		global $wpdb;

		$start_time        = microtime( true );
		$events_table_name = Segmentation::get_events_table_name();

		if ( ! file_exists( Segmentation::LOG_FILE_PATH ) ) {
			return;
		}

		$log_file = fopen( Segmentation::LOG_FILE_PATH, 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( flock( $log_file, LOCK_EX ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock
			$lines = [];

			while ( ! feof( $log_file ) ) {
				$line = trim( fgets( $log_file ) );
				if ( ! empty( $line ) ) {
					$lines[] = $line;
				}
			}

			error_log( 'Parsing ' . count( $lines ) . ' lines of log file: ' . Segmentation::LOG_FILE_PATH ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			$lines       = array_unique( $lines );
			$events_rows = [];

			foreach ( $lines as $line ) {
				$result     = explode( ';', $line );
				$event_type = $result[0];
				$client_id  = $result[1];
				$date       = $result[2];
				$post_id    = $result[3];
				$categories = $result[4];

				$existing_post_events = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->prepare( "SELECT * FROM $events_table_name WHERE post_id = %s AND client_id = %s", $post_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				);
				if ( null === $existing_post_events ) {
					$events_rows[] = [ $event_type, $client_id, $date, $post_id, $categories ];
				}
			}

			self::bulk_db_insert(
				$events_table_name,
				$events_rows,
				[
					'type',
					'client_id',
					'created_at',
					'post_id',
					'categories_ids',
				],
				'( %s, %s, %s, %s, %s )'
			);

			error_log( 'parsing duration: ' . round( ( microtime( true ) - $start_time ) * 1000 ) . 'ms' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			flock( $log_file, LOCK_UN ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock

			// Clear the log file.
			file_put_contents( Segmentation::LOG_FILE_PATH, '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			fclose( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		} else {
			error_log( 'Log file locking unsuccessful, logs were not parsed.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
Newspack_Popups_Parse_Logs::instance();
