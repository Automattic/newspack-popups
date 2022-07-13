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
	 * Handle reporting campaign data – views and subscriptions.
	 *
	 * @param object $request A request.
	 */
	public function report_campaign( $request ) {
		$client_id = $this->get_request_param( 'cid', $request );
		$popup_id  = $this->get_request_param( 'popup_id', $request );
		$action    = $this->get_request_param( 'dismiss', $request ) ? 'prompt_dismissed' : 'prompt_seen';
		$events    = [
			[
				'client_id' => $client_id,
				'type'      => $action,
				'context'   => $popup_id,
			],
		];

		// Log a newsletter subscription event.
		$email_address = $this->get_request_param( 'email', $request );
		if ( $email_address ) {
			$subscription_event = [
				'client_id' => $client_id,
				'type'      => 'subscription',
				'context'   => $email_address,
			];
			$esp                = $this->get_request_param( 'esp', $request );

			if ( $esp ) {
				$subscription_event['value']['esp'] = $esp;
			}

			$events[] = $subscription_event;
		}

		$this->save_reader_events( $client_id, $events );
	}
}
new Report_Campaign_Data();
