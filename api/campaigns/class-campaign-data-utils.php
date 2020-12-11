<?php
/**
 * Newspack Campaigns API utils.
 *
 * @package Newspack
 */

/**
 * Utils.
 */
class Campaign_Data_Utils {
	/**
	 * Is the URL from a newsletter?
	 *
	 * @param string $url A URL.
	 */
	public static function is_url_from_email( $url ) {
		return stripos( $url, 'utm_medium=email' );
	}

	/**
	 * Is client a donor?
	 *
	 * @param object $client_data Client data.
	 */
	public static function is_donor( $client_data ) {
		return ! empty( $client_data['donations'] );
	}

	/**
	 * Is client a subscriber?
	 *
	 * @param object $client_data Client data.
	 * @param string $url URL.
	 */
	public static function is_subscriber( $client_data, $url ) {
		// If coming from email, assume it's a subscriber.
		return ! empty( $client_data['email_subscriptions'] ) || self::is_url_from_email( $url );
	}

	/**
	 * Add default values to a segment.
	 *
	 * @param object $segment Segment configuration.
	 * @return object Segment configuration with default values.
	 */
	public static function canonize_segment( $segment ) {
		return (object) array_merge(
			[
				'min_posts'         => 0,
				'max_posts'         => 0,
				'is_subscribed'     => false,
				'is_not_subscribed' => false,
				'is_donor'          => false,
				'is_not_donor'      => false,
				'referrers'         => '',
			],
			(array) $segment
		);
	}

	/**
	 * Given a segment and client data, decide if the campaign should be shown.
	 *
	 * @param object $campaign_segment Segment data.
	 * @param object $client_data Client data.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referrer_url URL of the referrer of the frontend page that is making the API request.
	 * @return bool Whether the campaign should be shown.
	 */
	public static function should_display_campaign( $campaign_segment, $client_data, $referer_url = '', $page_referrer_url = '' ) {
		$should_display   = true;
		$posts_read_count = count( $client_data['posts_read'] );
		$is_subscriber    = self::is_subscriber( $client_data, $referer_url );
		$is_donor         = self::is_donor( $client_data );
		$campaign_segment = self::canonize_segment( $campaign_segment );

		if ( $campaign_segment->min_posts > 0 && $campaign_segment->min_posts > $posts_read_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_posts > 0 && $campaign_segment->max_posts < $posts_read_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_subscribed && ! $is_subscriber ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_subscribed && $is_subscriber ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_donor && ! $is_donor ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_donor && $is_donor ) {
			$should_display = false;
		}

		if ( ! empty( $campaign_segment->referrers ) ) {
			if ( empty( $page_referrer_url ) ) {
				$should_display = false;
			}
			$referer_domain = parse_url( $page_referrer_url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			// Handle the 'www' prefix – assume `www.example.com` and `example.com` referrers are the same.
			$referer_domain_alternative = strpos( $referer_domain, 'www.' ) === 0 ? substr( $referer_domain, 4 ) : "www.$referer_domain";
			$referrer_matches           = array_intersect(
				[ $referer_domain, $referer_domain_alternative ],
				array_map( 'trim', explode( ',', $campaign_segment->referrers ) )
			);
			if ( empty( $referrer_matches ) ) {
				$should_display = false;
			}
		}
		return $should_display;
	}
}
