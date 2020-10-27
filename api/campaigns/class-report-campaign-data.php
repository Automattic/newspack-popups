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
class Report_Campaign_Data extends Lightweight_API {

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		parent::__construct();
		$payload = $this->get_post_payload();
		if ( empty( $payload ) ) {
			return;
		}
		$this->report_campaign( $payload );
		$this->respond();
	}

	/**
	 * Handle reporting campaign data – e.g. views, dismissals.
	 *
	 * @param object $request A request.
	 */
	public function report_campaign( $request ) {
		$client_id          = $this->get_request_param( 'cid', $request );
		$campaign_id        = $this->get_request_param( 'popup_id', $request );
		$campaign_data      = $this->get_campaign_data( $client_id, $campaign_id );
		$client_data_update = [];

		$campaign_data['count']++;
		$campaign_data['last_viewed'] = time();

		// Handle permanent suppression.
		if ( $this->get_request_param( 'suppress_forever', $request ) ) {
			$campaign_data['suppress_forever'] = true;

			// Suppressed a newsletter campaign.
			if ( $this->get_request_param( 'is_newsletter_popup', $request ) ) {
				$client_data_update['suppressed_newsletter_campaign'] = true;
			}
		}

		// Subscribed to a newsletter – suppress this campaign.
		if ( 'subscribed' === $this->get_request_param( 'mailing_list_status', $request ) ) {
			$campaign_data['suppress_forever'] = true;
		}

		// Add an email subscription to client data.
		$email_address = $this->get_request_param( 'email', $request );
		if ( $email_address ) {
			// This is an array, so it's possible to collect data for separate lists in the future.
			$client_data_update['email_subscriptions'][] = [
				'email' => $email_address,
			];
		}

		$this->save_client_data( $client_id, $client_data_update );
		$this->save_campaign_data( $client_id, $campaign_id, $campaign_data );
	}
}
new Report_Campaign_Data();
