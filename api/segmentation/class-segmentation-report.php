<?php
/**
 * Newspack Popups Segmentation Report
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once '../classes/class-lightweight-api.php';

/**
 * Manages Segmentation.
 */
class Segmentation_Report extends Lightweight_API {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->api_handle_visit( $this->get_post_payload() );
		$this->respond();
	}

	/**
	 * Add visit data to the table.
	 *
	 * @param string $client_id Client ID.
	 * @param object $visit_data visit data.
	 */
	public function add_visit_data( $client_id, $visit_data ) {
		global $wpdb;
		$clients_table_name = Segmentation::get_clients_table_name();
		$visits_table_name  = Segmentation::get_visits_table_name();
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

		$post_id              = $visit_data['post_id'];
		$existing_post_visits = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM $visits_table_name WHERE post_id = %s AND client_id = %s", $post_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		if ( null === $existing_post_visits ) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$visits_table_name,
				[
					'client_id'      => $client_id,
					'created_at'     => $visit_data['date'],
					'post_id'        => $post_id,
					'categories_ids' => $visit_data['categories_ids'],
				]
			);
		}
	}

	/**
	 * Handle visitor.
	 *
	 * May or may not add a visit to the visits table – ideally the request should be made
	 * only on single posts, but in order to make amp-analytics set the client ID cookie,
	 * the code has to be placed on each page. There may be a better solution, as that non-visit-adding
	 * request is not necessary.
	 *
	 * @param object $payload a payload.
	 */
	public function api_handle_visit( $payload ) {
		$add_visit = $payload['add_visit'];
		if ( '1' === $add_visit ) {
			$client_id          = $payload['clientId'];
			$visit_data_payload = [
				'post_id'        => $payload['id'],
				'date'           => gmdate( 'Y-m-d', time() ),
				'categories_ids' => $payload['categories'],
			];
			self::add_visit_data( $client_id, $visit_data_payload );
		}
	}
}

new Segmentation_Report();
