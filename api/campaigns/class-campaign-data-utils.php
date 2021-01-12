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
				'min_session_posts' => 0,
				'max_session_posts' => 0,
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
	 * @param object $view_as_segment If using the "view as" feature, this is a segment to conform to.
	 * @return bool Whether the campaign should be shown.
	 */
	public static function should_display_campaign( $campaign_segment, $client_data, $referer_url = '', $page_referrer_url = '', $view_as_segment = false ) {
		$should_display = true;
		// Posts read.
		$posts_read_count = count( $client_data['posts_read'] );
		// Posts read in the current session.
		$session_start            = strtotime( '-45 minutes', time() );
		$posts_read_count_session = count(
			array_filter(
				$client_data['posts_read'],
				function ( $post_data ) use ( $session_start ) {
					if ( ! isset( $post_data['created_at'] ) ) {
						return false;
					}
					return strtotime( $post_data['created_at'] ) > $session_start;
				}
			)
		);
		$is_subscriber            = self::is_subscriber( $client_data, $referer_url );
		$is_donor                 = self::is_donor( $client_data );
		$campaign_segment         = self::canonize_segment( $campaign_segment );

		if ( $view_as_segment ) {
			$view_as_segment = self::canonize_segment( $view_as_segment );
			if ( $view_as_segment->min_posts > 0 ) {
				$posts_read_count = $view_as_segment->min_posts;
			}
			if ( $view_as_segment->max_posts > 0 ) {
				$posts_read_count = $view_as_segment->max_posts;
			}
			if ( $view_as_segment->min_session_posts > 0 ) {
				$posts_read_count_session = $view_as_segment->min_session_posts;
			}
			if ( $view_as_segment->max_session_posts > 0 ) {
				$posts_read_count_session = $view_as_segment->max_session_posts;
			}
			$is_subscriber = $view_as_segment->is_subscribed;
			$is_donor      = $view_as_segment->is_donor;
			if ( ! empty( $view_as_segment->referrers ) ) {
				$first_referrer = array_map( 'trim', explode( ',', $campaign_segment->referrers ) )[0];
				if ( strpos( $first_referrer, 'http' ) !== 0 ) {
					$first_referrer = 'https://' . $first_referrer;
				}
				$page_referrer_url = $first_referrer;
			}
		}

		if ( $campaign_segment->min_posts > 0 && $campaign_segment->min_posts > $posts_read_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_posts > 0 && $campaign_segment->max_posts < $posts_read_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->min_session_posts > 0 && $campaign_segment->min_session_posts > $posts_read_count_session ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_session_posts > 0 && $campaign_segment->max_session_posts < $posts_read_count_session ) {
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
