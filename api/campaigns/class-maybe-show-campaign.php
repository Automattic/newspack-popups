<?php
/**
 * Newspack Campaigns maybe display prompt.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../classes/class-lightweight-api.php';

require_once dirname( __FILE__ ) . '/../segmentation/class-segmentation-report.php';
require_once dirname( __FILE__ ) . '/class-campaign-data-utils.php';

/**
 * GET endpoint to determine if prompt is shown or not.
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
		$campaigns = json_decode( $_REQUEST['popups'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$settings  = json_decode( $_REQUEST['settings'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$visit     = (array) json_decode( $_REQUEST['visit'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$response  = [];
		$client_id = $_REQUEST['cid']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$view_as_spec = [];
		if ( ! empty( $_REQUEST['view_as'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$view_as_spec = Segmentation::parse_view_as( json_decode( $_REQUEST['view_as'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}

		if ( $visit['is_post'] && ( ! defined( 'DISABLE_CAMPAIGN_EVENT_LOGGING' ) || true !== DISABLE_CAMPAIGN_EVENT_LOGGING ) ) {
			// Update the cache.
			$posts_read        = $this->get_client_data( $client_id )['posts_read'];
			$already_read_post = count(
				array_filter(
					$posts_read,
					function ( $post_data ) use ( $visit ) {
						return $post_data['post_id'] == $visit['post_id'];
					}
				)
			) > 0;

			if ( false === $already_read_post ) {
				$posts_read[] = [
					'post_id'      => $visit['post_id'],
					'category_ids' => $visit['categories'],
					'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				];
				$this->save_client_data(
					$client_id,
					[
						'posts_read' => $posts_read,
					]
				);
			}

			Segmentation_Report::log_single_visit(
				array_merge(
					[
						'clientId' => $client_id,
					],
					$visit
				)
			);
		}

		$referer_url                   = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );
		$page_referer_url              = isset( $_REQUEST['ref'] ) ? $_REQUEST['ref'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$all_segments                  = isset( $settings->all_segments ) ? $settings->all_segments : [];
		$overlay_to_maybe_display      = null;
		$above_header_to_maybe_display = null;

		if ( $settings ) {
			$settings->best_priority_segment_id = $this->get_best_priority_segment_id( $all_segments, $client_id, $referer_url, $page_referer_url, $view_as_spec );
			$this->debug['matching_segment']    = $settings->best_priority_segment_id;
		}

		// Check each matching campaign against other global factors.
		foreach ( $campaigns as $campaign ) {
			$campaign_should_be_shown = $this->should_campaign_be_shown(
				$client_id,
				$campaign,
				$settings,
				$referer_url,
				$page_referer_url,
				$view_as_spec,
				false
			);

			// If an overlay is already able to be shown, pick the one that has the higher priority.
			if ( $campaign_should_be_shown && 'o' === $campaign->t ) {
				if ( empty( $overlay_to_maybe_display ) ) {
					$overlay_to_maybe_display = $campaign;
				} else {
					$higher_priority_item = self::get_higher_priority_item( $overlay_to_maybe_display, $campaign, $all_segments );

					// If the previous overlay already has a higher priority, only show that one. Otherwise, show this one instead.
					$response[ $overlay_to_maybe_display->id ] = $overlay_to_maybe_display->id === $higher_priority_item->id;
					$campaign_should_be_shown                  = $campaign->id === $higher_priority_item->id;
					$overlay_to_maybe_display                  = $higher_priority_item;
				}
			}

			// If an above-header is already able to be shown, pick the one that has the higher priority.
			if ( $campaign_should_be_shown && 'a' === $campaign->t ) {
				if ( empty( $above_header_to_maybe_display ) ) {
					$above_header_to_maybe_display = $campaign;
				} else {
					$higher_priority_item = self::get_higher_priority_item( $above_header_to_maybe_display, $campaign, $all_segments );

					// If the previous above-header already has a higher priority, only show that one. Otherwise, show this one instead.
					$response[ $above_header_to_maybe_display->id ] = $above_header_to_maybe_display->id === $higher_priority_item->id;
					$campaign_should_be_shown                       = $campaign->id === $higher_priority_item->id;
					$above_header_to_maybe_display                  = $higher_priority_item;
				}
			}

			$response[ $campaign->id ] = $campaign_should_be_shown;
		}

		$this->response = $response;
		$this->respond();
	}

	/**
	 * Get the best-priority segment that matches the client.
	 *
	 * @param object      $all_segments Segments to check.
	 * @param string      $client_id Client ID.
	 * @param string      $referer_url URL of the page performing the API request.
	 * @param string      $page_referer_url URL of the referrer of the frontend page that is making the API request.
	 * @param object|bool $view_as_spec View as spec.
	 *
	 * @return string|null ID of the best matching segment, or null if the client matches no segment.
	 */
	public function get_best_priority_segment_id( $all_segments = [], $client_id, $referer_url = '', $page_referer_url = '', $view_as_spec = false ) {
		// If using "view as" feature, automatically make that the matching segment. Otherwise, find the matching segment with the best priority.
		if ( $view_as_spec && isset( $view_as_spec['segment'] ) ) {
			return $view_as_spec['segment'];
		}

		$client_data              = $this->get_client_data( $client_id );
		$best_segment_priority    = PHP_INT_MAX;
		$best_priority_segment_id = null;

		foreach ( $all_segments as $segment_id => $segment ) {
			// Determine whether the client matches the segment criteria.
			$segment                = Campaign_Data_Utils::canonize_segment( $segment );
			$client_matches_segment = Campaign_Data_Utils::does_client_match_segment(
				$segment,
				$client_data,
				$referer_url,
				$page_referer_url
			);

			// Find the matching segment with the best priority.
			if ( $client_matches_segment && $segment->priority < $best_segment_priority ) {
				$best_segment_priority    = $segment->priority;
				$best_priority_segment_id = $segment_id;
			}
		}

		return $best_priority_segment_id;
	}

	/**
	 * Primary prompt visibility logic.
	 *
	 * @param string $client_id Client ID.
	 * @param object $campaign Prompt.
	 * @param object $settings Settings.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referer_url URL of the referrer of the frontend page that is making the API request.
	 * @param object $view_as_spec "View As" specification.
	 * @param string $now Current timestamp.
	 * @return bool Whether prompt should be shown.
	 */
	public function should_campaign_be_shown( $client_id, $campaign, $settings, $referer_url = '', $page_referer_url = '', $view_as_spec = false, $now = false ) {
		if ( false === $now ) {
			$now = time();
		}
		$campaign_data      = $this->get_campaign_data( $client_id, $campaign->id );
		$init_campaign_data = $campaign_data;

		if ( $campaign_data['suppress_forever'] ) {
			return false;
		}

		$should_display = true;

		$has_newsletter_prompt = $campaign->n;
		// Suppressing based on UTM Medium parameter in the URL.
		$has_utm_medium_in_url = Campaign_Data_Utils::is_url_from_email( $referer_url );

		// Handle referer-based conditions.
		if ( ! empty( $referer_url ) ) {
			// Suppressing based on UTM Source parameter in the URL.
			$utm_suppression = ! empty( $campaign->utm ) ? urldecode( $campaign->utm ) : null;
			if ( $utm_suppression && stripos( urldecode( $referer_url ), 'utm_source=' . $utm_suppression ) ) {
				$should_display                    = false;
				$campaign_data['suppress_forever'] = true;
			}

			if (
				$has_utm_medium_in_url &&
				$settings->suppress_newsletter_campaigns &&
				$has_newsletter_prompt
			) {
				$should_display                    = false;
				$campaign_data['suppress_forever'] = true;
			}
		}

		$client_data = $this->get_client_data( $client_id );

		$has_suppressed_newsletter_campaign = $client_data['suppressed_newsletter_campaign'];

		// Handle suppressing a newsletter prompt if any newsletter prompt was dismissed.
		if (
			$has_newsletter_prompt &&
			$settings->suppress_all_newsletter_campaigns_if_one_dismissed &&
			$has_suppressed_newsletter_campaign
		) {
			$should_display                    = false;
			$campaign_data['suppress_forever'] = true;
		}

		$has_donated        = count( $client_data['donations'] ) > 0;
		$has_donation_block = $campaign->d;

		// Handle suppressing a donation prompt if reader is a donor and appropriate setting is active.
		if (
			$has_donation_block &&
			$settings->suppress_donation_campaigns_if_donor &&
			$has_donated
		) {
			$should_display                    = false;
			$campaign_data['suppress_forever'] = true;
		}

		// Handle segmentation.
		$campaign_segment_ids = ! empty( $campaign->s ) ? explode( ',', $campaign->s ) : [];

		// Using "view as" feature.
		if ( $view_as_spec ) {
			$should_display = false;
			if ( isset( $view_as_spec['segment'] ) && $view_as_spec['segment'] ) {
				// Show prompts with matching segments, or "everyone". Don't show any prompts that don't match the previewed segment.
				if ( in_array( $view_as_spec['segment'], $campaign_segment_ids ) || empty( $campaign->s ) ) {
					$should_display = true;
				}

				// Show prompts if the view_as segment doesn't exist.
				if ( ! property_exists( $settings->all_segments, $view_as_spec['segment'] ) ) {
					$should_display = true;
				}
			}
		} elseif ( ! empty( $campaign_segment_ids ) ) {
			// $settings->best_priority_segment_id should always be present, but in case it's not (e.g. in a unit test), we can fetch it here.
			$best_priority_segment_id = isset( $settings->best_priority_segment_id ) ?
				$settings->best_priority_segment_id :
				$this->get_best_priority_segment_id( $settings->all_segments, $client_id, $referer_url, $page_referer_url, $view_as_spec );

			// Only factor in the best=priority segment.
			$is_best_priority = ! empty( $best_priority_segment_id ) ? in_array( $best_priority_segment_id, $campaign_segment_ids ) : false;
			$campaign_segment = $is_best_priority ?
				$settings->all_segments->{$best_priority_segment_id} :
				[];

			$campaign_segment = Campaign_Data_Utils::canonize_segment( $campaign_segment );

			// Check whether client matches the prompt's segment.
			$should_display = $is_best_priority && Campaign_Data_Utils::does_client_match_segment(
				$campaign_segment,
				$client_data,
				$referer_url,
				$page_referer_url
			);

			if (
				$campaign_segment->is_not_subscribed &&
				$has_utm_medium_in_url &&
				! empty( $client_data['email_subscriptions'] )
			) {
				// Save suppression for this prompt.
				$campaign_data['suppress_forever'] = true;
			}
		}

		// Handle frequency.
		$frequency = $campaign->f;
		if ( ! empty( array_diff( $init_campaign_data, $campaign_data ) ) ) {
			$this->save_campaign_data( $client_id, $campaign->id, $campaign_data );
		}
		if ( 'once' === $frequency && $campaign_data['count'] >= 1 ) {
			$should_display = false;
		}
		if ( 'daily' === $frequency && $campaign_data['last_viewed'] >= strtotime( '-1 day', $now ) ) {
			$should_display = false;
		}

		return $should_display;
	}

	/**
	 * Compare two campaign objects and return the one with the higher segment priority (lower priority index).
	 * If both have equal priority, just return the first one.
	 *
	 * @param object $campaign_a First campaign to compare.
	 * @param object $campaign_b Second campaign to compare.
	 * @param array  $segments   Array of segments, to extract priority values from.
	 * @return integer The campaign with the higher priority.
	 */
	public function get_higher_priority_item( $campaign_a, $campaign_b, $segments ) {
		$priority_a = ! empty( $segments->{$campaign_a->s}->priority ) ? $segments->{$campaign_a->s}->priority : PHP_INT_MAX;
		$priority_b = ! empty( $segments->{$campaign_b->s}->priority ) ? $segments->{$campaign_b->s}->priority : PHP_INT_MAX;

		if ( $priority_a <= $priority_b ) {
			return $campaign_a;
		}

		return $campaign_b;
	}
}
new Maybe_Show_Campaign();
