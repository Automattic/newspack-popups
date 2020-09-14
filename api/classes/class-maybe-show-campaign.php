<?php
/**
 * Newspack Campaigns maybe display campaign.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once 'class-lightweight-api.php';

/**
 * GET endpoint to determine if campaign is shown or not.
 */
class Maybe_Show_Campaign extends Lightweight_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$campaigns = json_decode( $_REQUEST['popups'] );
		$settings  = json_decode( $_REQUEST['settings'] );
		$response  = [];
		foreach ( $campaigns as $campaign ) {
			$response[ $campaign->id ] = $this->should_campaign_be_shown( $_REQUEST['cid'], $campaign, $settings );
		}
		$this->response = $response;
		$this->respond();
	}

	/**
	 * Primary campaign visibility logic.
	 *
	 * @param string $client_id Client ID.
	 * @param string $campaign_id Campaign ID.
	 * @param object $settings Settings.
	 * @return bool Whether campaign should be shown.
	 */
	public function should_campaign_be_shown( $client_id, $campaign, $settings ) {
		$campaign_data = $this->get_campaign_data( $client_id, $campaign->id );

		if ( $campaign_data['suppress_forever'] ) {
			return false;
		}

		$should_display = true;

		// Handle frequency.
		$frequency = $campaign->f;
		switch ( $frequency ) {
			case 'daily':
				$should_display = $campaign_data['last_viewed'] < strtotime( '-1 day' );
				break;
			case 'once':
				$should_display = $campaign_data['count'] < 1;
				break;
			case 'always':
				$should_display = true;
				break;
			case 'never':
			default:
				$should_display = false;
				break;
		}

		$has_newsletter_prompt = $campaign->n;

		// Handle referer-based conditions.
		$referer_url = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );
		if ( ! empty( $referer_url ) ) {
			// Suppressing based on UTM Source parameter in the URL.
			$utm_suppression = ! empty( $campaign->utm ) ? urldecode( $campaign->utm ) : null;
			if ( $utm_suppression && stripos( urldecode( $referer_url ), 'utm_source=' . $utm_suppression ) ) {
				$should_display                    = false;
				$campaign_data['suppress_forever'] = true;
			}

			// Suppressing based on UTM Medium parameter in the URL.
			$has_utm_medium_in_url = stripos( $referer_url, 'utm_medium=email' );
			if (
				$has_utm_medium_in_url &&
				$settings->suppress_newsletter_campaigns &&
				$has_newsletter_prompt
			) {
				$should_display                    = false;
				$campaign_data['suppress_forever'] = true;
			}
		}

		$client_data                        = $this->get_client_data( $client_id );
		$has_suppressed_newsletter_campaign = $client_data['suppressed_newsletter_campaign'];

		// Handle suppressing a newsletter campaign if any newsletter campaign was dismissed.
		if (
		   $has_newsletter_prompt &&
		   $settings->suppress_all_newsletter_campaigns_if_one_dismissed &&
		   $has_suppressed_newsletter_campaign
		) {
			$should_display                    = false;
			$campaign_data['suppress_forever'] = true;
		}

		$this->save_campaign_data( $client_id, $campaign->id, $campaign_data );

		return $should_display;
	}
}
new Maybe_Show_Campaign();
