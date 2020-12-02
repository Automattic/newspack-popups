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
	 * Get log file path.
	 */
	public static function get_log_file_path() {
		global $table_prefix;
		return get_temp_dir() . $table_prefix . 'newspack-popups-events.log';
	}

	/**
	 * Get events table name.
	 */
	public static function get_events_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_events';
	}
}
