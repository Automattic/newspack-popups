<?php
/**
 * Newspack Campaigns maybe display campaign.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once '../classes/class-lightweight-api.php';

/**
 * GET endpoint to determine if campaign is shown or not.
 */
class Maybe_Show_Campaign extends Lightweight_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		if ( ! isset( $_REQUEST['popups'], $_REQUEST['settings'], $_REQUEST['cid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$campaigns                       = json_decode( $_REQUEST['popups'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings                        = json_decode( $_REQUEST['settings'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$response                        = [];
		$client_id                       = $_REQUEST['cid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings->client_read_posts_ids = array_map(
			function ( $post_visit ) {
				return $post_visit->post_id;
			},
			Newspack_Popups_Segmentation::get_client_read_posts( $client_id )
		);
		foreach ( $campaigns as $campaign ) {
			$response[ $campaign->id ] = $this->should_campaign_be_shown( $client_id, $campaign, $settings );
		}
		$this->response = $response;
		$this->respond();
	}

	/**
	 * Primary campaign visibility logic.
	 *
	 * @param string $client_id Client ID.
	 * @param object $campaign Campaign.
	 * @param object $settings Settings.
	 * @return bool Whether campaign should be shown.
	 */
	public function should_campaign_be_shown( $client_id, $campaign, $settings ) {
		$campaign_data      = $this->get_campaign_data( $client_id, $campaign->id );
		$init_campaign_data = $campaign_data;

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

		$min_posts_read = (int) $campaign->min;
		if ( $min_posts_read > 0 ) {
			$read_posts = $settings->client_read_posts_ids;
			$read_count = count( $read_posts );

			// Increment read count if reading a new article now.
			if ( false === array_search( $settings->article_id, $read_posts ) ) {
				$read_count += 1;
			}
			if ( $min_posts_read > $read_count ) {
				$should_display = false;
			}
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

		if ( ! empty( array_diff( $init_campaign_data, $campaign_data ) ) ) {
			$this->save_campaign_data( $client_id, $campaign->id, $campaign_data );
		}

		return $should_display;
	}
}
new Maybe_Show_Campaign();
