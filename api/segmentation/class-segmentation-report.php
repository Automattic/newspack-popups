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
	 * @param string $ga_client_id GA's Client ID.
	 */
	public function add_visit_data( $client_id, $visit_data, $ga_client_id = null ) {
		global $api_wpdb;
		$clients_table_name = Segmentation::get_clients_table_name();
		$visits_table_name  = Segmentation::get_visits_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_client = $api_wpdb->get_row( $api_wpdb->prepare( "SELECT * FROM $clients_table_name WHERE client_id = %s", $client_id ) );

		if ( null === $found_client ) {
			$updates = [
				'client_id'  => $client_id,
				'created_at' => gmdate( 'Y-m-d' ),
				'updated_at' => gmdate( 'Y-m-d' ),
			];
			if ( null !== $ga_client_id ) {
				$updates['ga_client_id'] = $ga_client_id;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$api_wpdb->insert(
				$clients_table_name,
				$updates
			);
		} else {
			$updates = [
				'updated_at' => gmdate( 'Y-m-d' ),
			];
			if ( null !== $ga_client_id && null === $found_client->ga_client_id ) {
				$updates['ga_client_id'] = $ga_client_id;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$api_wpdb->update(
				$clients_table_name,
				$updates,
				[ 'ID' => $found_client->id ]
			);
		}

		$post_id              = $visit_data['post_id'];
		$existing_post_visits = $api_wpdb->get_row(
			$api_wpdb->prepare( "SELECT * FROM $visits_table_name WHERE post_id = %s AND client_id = %s", $post_id, $client_id )
		);
		if ( null === $existing_post_visits ) {
			$api_wpdb->insert(
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

			$ga_client_id = self::get_ga_client_id();
			if ( $ga_client_id ) {
				self::add_visit_data( $client_id, $visit_data_payload, $ga_client_id );
			} else {
				self::add_visit_data( $client_id, $visit_data_payload );
			}
		}
	}

	/**
	 * Get GA's cookie with client id.
	 */
	public function get_ga_client_id() {
		$ga_cookie = isset( $_COOKIE['_ga'] ) ? filter_var( $_COOKIE['_ga'], FILTER_SANITIZE_STRING ) : false; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( isset( $ga_cookie ) ) {
			// Remove the prefix:
			// In AMP standard mode, the value of `_ga` cookie will be the client id,
			// In non-AMP contexts it will be prefixed with `GA1.<domain-level>.` (e.g. `GA1.3.`).
			return preg_replace( '/GA\d\.\d\./', '', $ga_cookie );
		}
		return false;
	}
}

new Segmentation_Report();
