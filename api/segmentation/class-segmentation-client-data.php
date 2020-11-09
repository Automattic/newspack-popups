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

require_once __DIR__ . '/../../vendor/autoload.php';

use \DrewM\MailChimp\MailChimp;

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
			$client_data_update['donations'][] = $donation;
		}

		// Add a subscription to client.
		$email_subscription = $this->get_request_param( 'email_subscription', $request );
		if ( $email_subscription ) {
			$client_data_update['email_subscriptions'][] = $email_subscription;
		}

		// Fetch Mailchimp data.
		$mailchimp_campaign_id   = $this->get_request_param( 'mc_cid', $request );
		$mailchimp_subscriber_id = $this->get_request_param( 'mc_eid', $request );
		if ( $mailchimp_campaign_id && $mailchimp_subscriber_id ) {
			$mailchimp_api_key_option_name = 'newspack_newsletters_mailchimp_api_key';
			global $wpdb;
			$mailchimp_api_key = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name=%s", $mailchimp_api_key_option_name )
			);
			if ( $mailchimp_api_key ) {
				$mc            = new Mailchimp( $mailchimp_api_key->option_value );
				$campaign_data = $mc->get( "campaigns/$mailchimp_campaign_id" );
				if ( isset( $campaign_data['recipients'], $campaign_data['recipients']['list_id'] ) ) {
					$list_id = $campaign_data['recipients']['list_id'];
					$members = $mc->get( "/lists/$list_id/members", [ 'unique_email_id' => $mailchimp_subscriber_id ] )['members'];

					if ( ! empty( $members ) ) {
						$client                                      = $members[0];
						$client_data_update['email_subscriptions'][] = [
							'email' => $client['email_address'],
						];
						$revenue                                     = $client['stats']['ecommerce_data']['total_revenue'];
						if ( $revenue > 0 ) {
							$client_data_update['donations'][] = [
								'mailchimp_revenue' => $revenue,
							];
						}
					}
				}
			}
		}

		if ( ! empty( $client_data_update ) ) {
			$this->save_client_data( $client_id, $client_data_update );
		}
	}
}
new Segmentation_Client_Data();
