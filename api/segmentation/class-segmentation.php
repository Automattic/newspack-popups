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
	const IS_PARSING_FILE_PATH = WP_CONTENT_DIR . '/../.is-parsing';
	const LOG_FILE_PATH        = WP_CONTENT_DIR . '/../newspack-popups-visits.log';

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
		$visits_table_name = self::get_visits_table_name();
		$clients_visits    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM $visits_table_name WHERE client_id = %s", $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		return $clients_visits;
	}

	/**
	 * Get clients table name.
	 */
	public static function get_clients_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_clients';
	}

	/**
	 * Get visits table name.
	 */
	public static function get_visits_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_visits';
	}

	/**
	 * Create the clients and visits tables.
	 */
	public static function create_segmentation_tables() {
		global $wpdb;
		$clients_table_name = self::get_clients_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $clients_table_name ) ) != $clients_table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $clients_table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				created_at date NOT NULL,
				updated_at date NOT NULL,
				client_id varchar(100) NOT NULL,
				-- If the user is logged in, they can be linked to a user id
				wp_user_id bigint(20),
				-- Track donations
				donations text,
				-- Email subscriptions status
				email_subscriptions text,
				PRIMARY KEY  (id)
			) $charset_collate;";

			$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		$visits_table_name = self::get_visits_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $visits_table_name ) ) != $visits_table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $visits_table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				created_at date NOT NULL,
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
