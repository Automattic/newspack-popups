<?php
/**
 * Newspack Popups Segmentation
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages Segmentation.
 */
class Segmentation {
	/**
	 * An empty file, the presence of which will prevent log file modifications while the log is being parsed.
	 */
	const IS_PARSING_FILE_PATH = WP_CONTENT_DIR . '/../.is-parsing';

	/**
	 * The log file path.
	 */
	const LOG_FILE_PATH = WP_CONTENT_DIR . '/../newspack-popups-events.log';

	/**
	 * Initialize.
	 */
	public static function init() {
		self::create_segmentation_tables();
	}

	/**
	 * Get client's read posts.
	 *
	 * @param string $client_id Client ID.
	 */
	public static function get_client_read_posts( $client_id ) {
		global $wpdb;
		$events_table_name = self::get_events_table_name();
		$clients_events    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM $events_table_name WHERE client_id = %s AND type = 'post_read'", $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return array_map(
			function ( $item ) {
				return [
					'post_id'        => $item->post_id,
					'categories_ids' => $item->categories_ids,
				];
			},
			$clients_events
		);
	}

	/**
	 * Get events table name.
	 */
	public static function get_events_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_events';
	}

	/**
	 * Create the clients and events tables.
	 */
	public static function create_segmentation_tables() {
		global $wpdb;
		$events_table_name = self::get_events_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table_name ) ) != $events_table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $events_table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				created_at date NOT NULL,
				-- type of event
				type varchar(20) NOT NULL,
				-- Unique id of a device/browser pair
				client_id varchar(100) NOT NULL,
				-- Article ID
				post_id bigint(20),
				-- Article categories IDs
				categories_ids varchar(100),
				PRIMARY KEY  (id)
			) $charset_collate;";

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}

Segmentation::init();
