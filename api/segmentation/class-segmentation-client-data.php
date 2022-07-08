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
	 * Handle reporting client data – e.g. views, subscriptions, donations.
	 *
	 * @param object $request A request.
	 */
	public function report_client_data( $request ) {
		$client_id     = $this->get_request_param( 'client_id', $request );
		$reader_events = [];

		// Add a donation to client.
		$donation = $this->get_request_param( 'donation', $request );
		if ( $donation ) {
			if ( 'string' === gettype( $donation ) ) {
				$donation = (array) json_decode( $donation );
			}

			$donation_event = [
				'type'  => 'donation',
				'value' => $donation,
			];

			if ( isset( $donation['order_id'] ) ) {
				$donation_event['context'] = 'woocommerce';
			}
			if ( isset( $donation['stripe_id'] ) ) {
				$donation_event['context'] = 'stripe';
			}

			$reader_events[] = $donation_event;
		}

		// Add a subscription to client.
		$email_subscription = $this->get_request_param( 'email_subscription', $request );
		$email_address      = false;
		if ( $email_subscription ) {
			if ( 'string' === gettype( $email_subscription ) ) {
				$email_subscription = (array) json_decode( $email_subscription );
			}

			if ( isset( $email_subscription['email'] ) ) {
				$email_address = $email_subscription['email'];
			} elseif ( isset( $email_subscription['address'] ) ) {
				$email_address = $email_subscription['address'];
			}
		}
		if ( $email_address ) {
			$reader_events[] = [
				'type'    => 'subscription',
				'context' => $email_address,
			];
		}

		// Get user ID if they have a user account.
		$user_id = $this->get_request_param( 'user_id', $request );
		if ( $user_id ) {
			$existing_user_accounts = $this->get_reader_events( $client_id, 'user_account', $user_id );
			$add_user_account       = true;

			// Only add a new user account if it hasn't already been logged for this client.
			if ( 0 < count( $existing_user_accounts ) ) {
				$add_user_account = false;
			}

			if ( $add_user_account ) {
				$reader_events[] = [
					'type'    => 'user_account',
					'context' => $user_id,
				];
			}
		}

		// Add donations data from WC orders.
		$orders = $this->get_request_param( 'orders', $request );
		if ( $orders ) {
			// Lookup existing orders so we don't log duplicate events.
			$existing_orders = array_map(
				function( $order_event ) {
					return isset( $order_event['value']['order_id'] ) ? (int) $order_event['value']['order_id'] : 0;
				},
				$this->get_reader_events( $client_id, 'donation', 'woocommerce' )
			);

			if ( 'string' === gettype( $orders ) ) {
				$orders = json_decode( $orders, true );
			}

			$order_events  = array_reduce(
				$orders,
				function( $acc, $order ) use ( $existing_orders ) {
					if ( ! in_array( (int) $order['order_id'], $existing_orders, true ) ) {
						$acc[] = [
							'type'    => 'donation',
							'context' => 'woocommerce',
							'value'   => $order,
						];
					}

					return $acc;
				},
				[]
			);
			$reader_events = array_merge( $reader_events, $order_events );
		}

		// Update reader events.
		if ( ! empty( $reader_events ) ) {
			$this->save_reader_events( $client_id, $reader_events );
		}
	}
}
new Segmentation_Client_Data();
