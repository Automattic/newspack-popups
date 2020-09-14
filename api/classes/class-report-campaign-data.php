<?php
/**
 * Newspack Campaigns report campaign data.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once 'class-lightweight-api.php';

/**
 * POST endpoint to report campaign data.
 */
class Report_Campaign_Data extends Lightweight_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$payload = $_POST;
		if ( null == $payload ) {
			// A POST request made by amp-analytics has to be parsed in this way.
			// $_POST contains the payload if the request has FormData.
			$payload = file_get_contents( 'php://input' );
			if ( ! empty( $payload ) ) {
				$payload = (array) json_decode( $payload );
			}
		}
		$this->report_campaign( $payload );
		$this->respond();
	}

	/**
	 * Handle reporting campaign data – e.g. views, dismissals.
	 *
	 * @param string $client_id Client ID.
	 * @param string $campaign_id Campaign ID.
	 */
	public function report_campaign( $request ) {
		$client_id     = $this->get_request_param( 'cid', $request );
		$campaign_id   = $this->get_request_param( 'popup_id', $request );
		$campaign_data = $this->get_campaign_data( $client_id, $campaign_id );
		$client_data   = $this->get_client_data( $client_id );

		$campaign_data['count']       = (int) $campaign_data['count'] + 1;
		$campaign_data['last_viewed'] = time();

		// Handle permanent suppression.
		if ( $this->get_request_param( 'suppress_forever', $request ) ) {
			$campaign_data['suppress_forever'] = true;

			// Suppressed a newsletter campaign.
			if ( $this->get_request_param( 'is_newsletter_popup', $request ) ) {
				$client_data['suppressed_newsletter_campaign'] = true;
			}
		}

		// Subscribed to a newsletter.
		if ( 'subscribed' === $this->get_request_param( 'mailing_list_status', $request ) ) {
			$campaign_data['suppress_forever'] = true;
		}

		// Add an email subscription to client.
		$email_address = $this->get_request_param( 'email', $request );
		if ( $email_address ) {
			$client_data['email_subscriptions'] = [
				'email' => $email_address,
			];
		}

		$this->save_client_data( $client_id, $client_data );
		$this->save_campaign_data( $client_id, $campaign_id, $campaign_data );
	}

	/**
	 * Get request param.
	 *
	 * @param object $request A POST request.
	 */
	public function get_request_param( $param, $request ) {
		$value = isset( $request[ $param ] ) ? $request[ $param ] : false;
		return $value;
	}
}
new Report_Campaign_Data();
