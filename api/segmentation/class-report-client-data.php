<?php
/**
 * Newspack Campaigns report campaign data.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../classes/class-lightweight-api.php';

/**
 * POST endpoint to report campaign data.
 */
class Report_Client_Data extends Lightweight_API {

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		parent::__construct();
		if ( isset( $_REQUEST['cid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$response = [
				'f' => self::get_client_reading_frequency( $_REQUEST['cid'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			];
		}
		$this->response = $response;
		$this->respond();
	}

	/**
	 * Handle reporting campaign data – e.g. views, dismissals.
	 *
	 * @param object $client_id Client ID.
	 */
	public function get_client_reading_frequency( $client_id ) {
		$period_ago_date      = date( 'Y-m-d', strtotime( '-1 months' ) ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
		$posts_read           = self::get_client_data( $client_id )['posts_read'];
		$posts_in_last_period = array_filter(
			$posts_read,
			function ( $item ) use ( $period_ago_date ) {
				return $item['date'] >= $period_ago_date;
			}
		);
		$read_count           = count( $posts_in_last_period );

		// NCI-inspired tiers, but we may opt for more fine-grained Memberkit-inspired tiers.
		if ( $read_count > 1 && $read_count <= 14 ) {
			return 'loyal';
		} elseif ( $read_count > 14 ) {
			return 'brand_lover';
		}
		return 'casual';
	}
}
new Report_Client_Data();
