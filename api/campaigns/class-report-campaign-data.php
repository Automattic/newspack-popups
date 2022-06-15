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
	 * TODO: Rethink this model to concatenate views/dismissals by prompt id to avoid creating new rows for each interaction.
	 *
	 * @param object $request A request.
	 */
	public function report_campaign( $request ) {
		$client_id = $this->get_request_param( 'cid', $request );
		$popup_id  = $this->get_request_param( 'popup_id', $request );
		$action    = $this->get_request_param( 'dismiss', $request ) ? 'dismissed' : 'seen';
		$events    = [
			[
				'client_id' => $client_id,
				'type'      => 'prompt',
				'context'   => $action,
				'value'     => [ 'id' => $popup_id ],
			],
		];

		// Log a newsletter subscription event.
		$email_address = $this->get_request_param( 'email', $request );
		if ( $email_address ) {
			$events[] = [
				'client_id' => $client_id,
				'type'      => 'subscription',
				'value'     => [ 'email' => $email_address ],
			];
		}

		$this->save_reader_data( $client_id, $events );
	}
}
new Report_Campaign_Data();
