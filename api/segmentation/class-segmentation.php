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
	const LOG_FILE_PATH = '/tmp/newspack-popups-events.log';

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
}
