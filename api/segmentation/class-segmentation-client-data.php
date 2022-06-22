<?php
/**
 * Newspack Campaigns report client data.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../classes/class-lightweight-api.php';

/**
 * POST endpoint to report client data.
 */
class Segmentation_Client_Data extends Lightweight_API {
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
		$this->report_client_data( $payload );
		$this->respond();
	}

	/**
	 * Handle reporting client data – e.g. views, dismissals.
	 *
	 * @param object $request A request.
	 */
	public function report_client_data( $request ) {
		$client_id          = $this->get_request_param( 'client_id', $request );
		$client_data_update = [];

		// Add a donation to client.
		$donation = $this->get_request_param( 'donation', $request );
		if ( $donation ) {
			if ( 'string' === gettype( $donation ) ) {
				$donation = (array) json_decode( $donation );
			}
			$client_data_update['donations'][] = $donation;
		}

		// Add a subscription to client.
		$email_subscription = $this->get_request_param( 'email_subscription', $request );
		if ( $email_subscription ) {
			$client_data_update['email_subscriptions'][] = $email_subscription;
		}

		// Get user ID if they are logged in.
		$user_id = $this->get_request_param( 'user_id', $request );
		if ( $user_id ) {
			$client_data_update['user_id'] = $user_id;
		}
		// Add donations data from WC orders.
		$orders = $this->get_request_param( 'orders', $request );
		if ( $orders ) {
			$client_data_update['donations'] = json_decode( $orders );
		}

		// Fetch Mailchimp data.
		$mailchimp_campaign_id   = $this->get_request_param( 'mc_cid', $request );
		$mailchimp_subscriber_id = $this->get_request_param( 'mc_eid', $request );
		if ( $mailchimp_campaign_id && $mailchimp_subscriber_id ) {
			$this->report_mailchimp( $client_id, $mailchimp_campaign_id, $mailchimp_subscriber_id );
		}

		if ( ! empty( $client_data_update ) ) {
			$this->save_client_data( $client_id, $client_data_update );
		}
	}
}
new Segmentation_Client_Data();
