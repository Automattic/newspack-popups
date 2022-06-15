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
		if ( file_exists( Segmentation::get_log_file_path() ) ) {
			add_filter( 'cron_schedules', [ __CLASS__, 'add_cron_interval' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
			add_action( 'newspack_popups_segmentation_cron_hook', [ __CLASS__, 'parse_events_logs' ] );
			if ( ! wp_next_scheduled( 'newspack_popups_segmentation_cron_hook' ) ) {
				wp_schedule_event( time(), 'every_quarter_hour', 'newspack_popups_segmentation_cron_hook' );
			}
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
	 * Parse the log file, write data to the DB, and remove the file.
	 * TODO: reformat rows with context and id
	 */
	public static function parse_events_logs() {
		global $wpdb;

		if ( ! file_exists( Segmentation::get_log_file_path() ) ) {
			return;
		}

		$log_file = fopen( Segmentation::get_log_file_path(), 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		if ( flock( $log_file, LOCK_EX ) ) { // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock
			$lines = [];

			while ( ! feof( $log_file ) ) {
				$line = trim( fgets( $log_file ) );
				if ( ! empty( $line ) ) {
					$lines[] = $line;
				}
			}

			$lines       = array_unique( $lines );
			$events_rows = [];

			foreach ( $lines as $line ) {
				$result = explode( '|', $line );
				if ( isset( $result[1] ) ) {
					$client_id    = $result[0];
					$date_created = $result[1];
					$event_type   = $result[2];
					$event_value  = $result[3];
					$is_preview   = $result[4];
				} else {
					// Handle legacy format.
					$result       = explode( ';', $line );
					$client_id    = $result[1];
					$date_created = $result[2];
					$event_type   = 'article_view';
					$event_value  = maybe_serialize(
						[
							'post_id'    => (int) $result[3],
							'categories' => $result[4],
						]
					);
					$is_preview   = 'preview' === substr( $client_id, 0, 7 );
				}

				$events_rows[] = [ $client_id, $date_created, $event_type, $event_value, $is_preview ];
			}

			try {
				Segmentation::bulk_db_insert(
					Segmentation::get_reader_data_table_name(),
					$events_rows,
					[
						'client_id',
						'date_created',
						'type',
						'event_value',
						'is_preview',
					],
					'( %s, %s, %s, %s, %s )'
				);
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// An error will be thrown for rows violating the UNIQUE constraint.
			}

			flock( $log_file, LOCK_UN ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_flock

			// Clear the log file.
			file_put_contents( Segmentation::get_log_file_path(), '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			fclose( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}
	}
}
Newspack_Popups_Parse_Logs::instance();
