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
	 * Is reader a newsletter subscriber?
	 *
	 * @param object $reader_data Reader data.
	 */
	public static function is_subscriber( $reader_data ) {
		return 0 < count(
			array_filter(
				$reader_data,
				function( $item ) {
					return 'subscription' === $item['type'];
				}
			)
		);
	}

	/**
	 * Is reader a donor?
	 *
	 * @param object $reader_data Reader data.
	 */
	public static function is_donor( $reader_data ) {
		return 0 < count(
			array_filter(
				$reader_data,
				function( $item ) {
					return 'donation' === $item['type'];
				}
			)
		);
	}

	/**
	 * Does reader have a WP user account?
	 *
	 * @param object $reader_data Reader data.
	 */
	public static function has_user_account( $reader_data ) {
		return 0 < count(
			array_filter(
				$reader_data,
				function( $item ) {
					return 'user_id' === $item['type'];
				}
			)
		);
	}

	/**
	 * Compare page referrer to a list of referrers.
	 *
	 * @param string $page_referrer_url Referrer to compare to.
	 * @param string $referrers_list_string Comma-separated referrer domains list.
	 */
	public static function does_referrer_match( $page_referrer_url, $referrers_list_string ) {
		$referer_domain      = parse_url( $page_referrer_url, PHP_URL_HOST ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$referer_domain_root = implode( '.', array_slice( explode( '.', $referer_domain ), -2 ) );
		$referrer_matches    = in_array(
			$referer_domain_root,
			array_map( 'trim', explode( ',', $referrers_list_string ) )
		);
		return $referrer_matches;
	}

	/**
	 * Given a reader's data, get the total view count of posts with post_type = `post`.
	 * Posts are articles in Newspack sites.
	 *
	 * @param array $reader_data Array of reader data as returned by Lightweight_Api::get_reader_data.
	 *
	 * @return int Total view count of posts with post_type = `post`.
	 */
	public static function get_post_view_count( $reader_data ) {
		return array_reduce(
			$reader_data,
			function( $acc, $item ) {
				if ( 'view_count' === $item['type'] && 'post' === $item['context'] && isset( $item['value']['count'] ) ) {
					$acc += (int) $item['value']['count'];
				}
				return $acc;
			},
			0
		);
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
				'min_posts'           => 0,
				'max_posts'           => 0,
				'min_session_posts'   => 0,
				'max_session_posts'   => 0,
				'is_subscribed'       => false,
				'is_not_subscribed'   => false,
				'is_donor'            => false,
				'is_not_donor'        => false,
				'has_user_account'    => false,
				'no_user_account'     => false,
				'referrers'           => '',
				'favorite_categories' => [],
				'priority'            => PHP_INT_MAX,
			],
			(array) $segment
		);
	}

	/**
	 * Given a segment and client data, decide if the prompt should be shown.
	 *
	 * @param object $campaign_segment Segment data.
	 * @param string $reader_data Reader data for the given client ID.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referrer_url URL of the referrer of the frontend page that is making the API request.
	 * @return bool Whether the prompt should be shown.
	 */
	public static function does_reader_match_segment( $campaign_segment, $reader_data, $referer_url = '', $page_referrer_url = '' ) {
		$should_display              = true;
		$is_subscriber               = self::is_subscriber( $reader_data );
		$is_donor                    = self::is_donor( $reader_data );
		$has_user_account            = self::has_user_account( $reader_data );
		$campaign_segment            = self::canonize_segment( $campaign_segment );
		$article_views_count         = self::get_post_view_count( $reader_data );
		$article_views_count_session = count(
			array_filter(
				$reader_data,
				function ( $item ) {
					$hour_ago  = strtotime( '-1 hour', time() );
					$item_time = strtotime( $item['date_created'] );
					return 'view' === $item['type'] && 'post' === $item['context'] && $item_time > $hour_ago;
				}
			)
		);

		// Read counts for categories.
		$favorite_category_matches_segment = false;
		$categories_read_counts            = false;
		foreach ( $reader_data as $item ) {
			if ( 'term_count' === $item['type'] && 'category' === $item['context'] ) {
				$categories_read_counts = $item['value'];
			}
		}
		if ( $categories_read_counts ) {
			arsort( $categories_read_counts );
			$favorite_category_matches_segment = in_array( key( $categories_read_counts ), $campaign_segment->favorite_categories );
		}

		/**
		 * By article view count.
		 */
		if ( $campaign_segment->min_posts > 0 && $campaign_segment->min_posts > $article_views_count ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_posts > 0 && $campaign_segment->max_posts < $article_views_count ) {
			$should_display = false;
		}

		/**
		 * By article views in past hour.
		 */
		if ( $campaign_segment->min_session_posts > 0 && $campaign_segment->min_session_posts > $article_views_count_session ) {
			$should_display = false;
		}
		if ( $campaign_segment->max_session_posts > 0 && $campaign_segment->max_session_posts < $article_views_count_session ) {
			$should_display = false;
		}

		/**
		 * By subscription status.
		 */
		if ( $campaign_segment->is_subscribed && ! $is_subscriber ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_subscribed && $is_subscriber ) {
			$should_display = false;
		}

		/**
		 * By donation status.
		 */
		if ( $campaign_segment->is_donor && ! $is_donor ) {
			$should_display = false;
		}
		if ( $campaign_segment->is_not_donor && $is_donor ) {
			$should_display = false;
		}

		/**
		 * By login status. is_logged_in and is_not_logged_in are legacy option names
		 * that have been renamed has_user_account and no_user_account, for clarity.
		 */
		$segment_has_user_account = ( isset( $campaign_segment->has_user_account ) && $campaign_segment->has_user_account ) ||
			( isset( $campaign_segment->is_logged_in ) && $campaign_segment->is_logged_in );
		$segment_no_user_account  = ( isset( $campaign_segment->no_user_account ) && $campaign_segment->no_user_account ) ||
		( isset( $campaign_segment->is_not_logged_in ) && $campaign_segment->is_not_logged_in );

		if ( $segment_has_user_account && ! $has_user_account ) {
			$should_display = false;
		}
		if ( $segment_no_user_account && $has_user_account ) {
			$should_display = false;
		}

		/**
		 * By referrer domain.
		 */
		if ( ! empty( $campaign_segment->referrers ) ) {
			if ( empty( $page_referrer_url ) ) {
				$should_display = false;
			}
			if ( empty( self::does_referrer_match( $page_referrer_url, $campaign_segment->referrers ) ) ) {
				$should_display = false;
			}
		}

		/**
		 * By referrer domain - negative.
		 */
		if ( ! empty( $campaign_segment->referrers_not ) && ! empty( self::does_referrer_match( $page_referrer_url, $campaign_segment->referrers_not ) ) ) {
			$should_display = false;
		}

		/**
		 * By most read category.
		 */
		if ( count( $campaign_segment->favorite_categories ) > 0 && ! $favorite_category_matches_segment ) {
			$should_display = false;
		}

		return $should_display;
	}
}
