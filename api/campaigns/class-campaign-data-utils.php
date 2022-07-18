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
	 * Array of non-singular view contexts.
	 */
	const NON_SINGULAR_VIEW_CONTEXTS = [
		'unknown',
		'archive',
		'search',
		'feed',
		'posts_page',
		'404',
	];

	/**
	 * Is the URL from a newsletter?
	 *
	 * @param string $url A URL.
	 */
	public static function is_url_from_email( $url ) {
		return stripos( $url, 'utm_medium=email' ) !== false;
	}

	/**
	 * Is reader a newsletter subscriber?
	 *
	 * @param object $reader_events Reader data.
	 * @param string $url Referrer URL.
	 */
	public static function is_subscriber( $reader_events, $url = '' ) {
		return self::is_url_from_email( $url ) || 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'subscription' === $event['type'];
				}
			)
		);
	}

	/**
	 * Is reader a donor?
	 *
	 * @param object $reader_events Reader data.
	 */
	public static function is_donor( $reader_events ) {
		return 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'donation' === $event['type'];
				}
			)
		);
	}

	/**
	 * Does reader have a WP user account?
	 *
	 * @param object $reader_events Reader data.
	 */
	public static function has_user_account( $reader_events ) {
		return 0 < count(
			array_filter(
				$reader_events,
				function( $event ) {
					return 'user_account' === $event['type'];
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
	 * Given a reader's data, get the total view count of singular posts.
	 * Articles are any singular post type in Newspack sites.
	 *
	 * @param array $reader Reader data.
	 *
	 * @return int Total view count of singular posts.
	 */
	public static function get_post_view_count( $reader ) {
		$total_view_count      = 0;
		$non_singular_contexts = self::NON_SINGULAR_VIEW_CONTEXTS;

		if ( isset( $reader['reader_data']['views'] ) ) {
			foreach ( $reader['reader_data']['views'] as $post_type => $count ) {
				if ( ! in_array( $post_type, $non_singular_contexts, true ) ) {
					$total_view_count += (int) $count;
				}
			}
		}

		return $total_view_count;
	}

	/**
	 * Given a reader's recent events, get the view count of singular posts in the current session.
	 * A session is considered one hour.
	 *
	 * @param array $reader_events Array of reader data as returned by Lightweight_Api::get_reader_events.
	 *
	 * @return int View count of singular posts in current session.
	 */
	public static function get_post_view_count_session( $reader_events ) {
		$non_singular_contexts = self::NON_SINGULAR_VIEW_CONTEXTS;
		return count(
			array_filter(
				$reader_events,
				function ( $event ) use ( $non_singular_contexts ) {
					$hour_ago   = strtotime( '-1 hour', time() );
					$event_time = strtotime( $event['date_created'] );
					return 'view' === $event['type'] && ! in_array( $event['context'], $non_singular_contexts, true ) && $event_time > $hour_ago;
				}
			)
		);
	}

	/**
	 * Given a reader's data, get the view counts of each term of the given taxonomy.
	 *
	 * @param array  $reader Reader data.
	 * @param string $taxonomy Taxonomy to look for. Defaults to 'category'.
	 *
	 * @return int View counts of the given taxonomy.
	 */
	public static function get_term_view_counts( $reader, $taxonomy = 'category' ) {
		return isset( $reader['reader_data'][ $taxonomy ] ) ? $reader['reader_data'][ $taxonomy ] : false;
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
	 * @param string $reader Reader data for the given client ID.
	 * @param string $reader_events Reader data for the given client ID.
	 * @param string $referer_url URL of the page performing the API request.
	 * @param string $page_referrer_url URL of the referrer of the frontend page that is making the API request.
	 * @return bool Whether the prompt should be shown.
	 */
	public static function does_reader_match_segment( $campaign_segment, $reader, $reader_events, $referer_url = '', $page_referrer_url = '' ) {
		$should_display              = true;
		$is_subscriber               = self::is_subscriber( $reader_events, $referer_url );
		$is_donor                    = self::is_donor( $reader_events );
		$has_user_account            = self::has_user_account( $reader_events );
		$campaign_segment            = self::canonize_segment( $campaign_segment );
		$article_views_count         = self::get_post_view_count( $reader );
		$article_views_count_session = self::get_post_view_count_session( $reader_events );

		// Read counts for categories.
		$favorite_category_matches_segment = false;
		$category_view_counts              = self::get_term_view_counts( $reader );
		if ( $category_view_counts ) {
			arsort( $category_view_counts );
			$favorite_category_matches_segment = in_array( key( $category_view_counts ), $campaign_segment->favorite_categories );
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

	/**
	 * If the prompt is an overlay.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_overlay( $popup ) {
		return isset( $popup->t ) && 'o' === $popup->t;
	}

	/**
	 * If the prompt is an inline prompt.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_inline( $popup ) {
		return isset( $popup->t ) && 'i' === $popup->t;
	}

	/**
	 * If the prompt is an above-header prompt.
	 *
	 * @param array $popup Popup object.
	 *
	 * @return boolean
	 */
	public static function is_above_header( $popup ) {
		return isset( $popup->t ) && 'a' === $popup->t;
	}

	/**
	 * Whether to ignore persistent cache when fetching reader data or events.
	 *
	 * @return boolean
	 */
	public static function ignore_cache() {
		return ! file_exists( WP_CONTENT_DIR . '/object-cache.php' ) || ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV );
	}

	/**
	 * Given an array of reader events, only return those with matching $types and/or $contexts.
	 * If both $types and $contexts are null, simply return the array as-is.
	 *
	 * @param array             $events Array of reader events.
	 * @param string|array|null $types Data type or array of data types to filter by.
	 *                                 If not given, will only retrieve temporary data types.
	 * @param string|array|null $contexts Data context or array of data contexts to filter by.
	 *
	 * @return array Filtered array of data items.
	 */
	public static function filter_events_by_type( $events = [], $types = null, $contexts = null ) {
		// Unserialize event values.
		if ( ! empty( $events ) ) {
			$events = array_map(
				function( $event ) {
					if ( ! empty( $event['value'] ) && is_string( $event['value'] ) ) {
						$event['value'] = json_decode( $event['value'], true );
					}
					return $event;
				},
				$events
			);
		}

		if ( null === $types && null === $contexts ) {
			return $events;
		}

		if ( ! is_array( $types ) && null !== $types ) {
			$types = [ $types ];
		}
		if ( ! is_array( $contexts ) && null !== $contexts ) {
			$contexts = [ $contexts ];
		}

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $types, $contexts ) {
					$matches = true;
					if ( null !== $types ) {
						$matches = in_array( $event['type'], $types, true );
					}

					if ( null !== $contexts ) {
						$matches = $matches && in_array( $event['context'], $contexts, true );
					}
					return $matches;
				}
			)
		);
	}

	/**
	 * Given an array of values, build a SQL statement to query on those values.
	 *
	 * @param string $column_name Name of the column to query.
	 * @param array  $values Possible values to query for.
	 *
	 * @return string Partial query string to use in a SQL query.
	 */
	public static function build_partial_query_filter( $column_name, $values ) {
		global $wpdb;

		// If only one value to query on, use the = operator. Otherwise, use the IN operator.
		if ( 1 === count( $values ) ) {
			return $wpdb->prepare( "$column_name = %s", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		$placeholders = [];
		foreach ( $values as $value ) {
			$placeholders[] = '%s';
		}
		$placeholders = implode( ',', $placeholders );

		return $wpdb->prepare( "$column_name IN ( $placeholders )", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}

	/**
	 * Get all reader data from the DB.
	 *
	 * @param string            $client_ids Client ID or IDs associated with the reader.
	 * @param string|array|null $type Type or types of data to retrieve.
	 * @param string|array|null $context Context or contexts of data to retrieve.
	 *
	 * @return array Array of reader data for the given client ID.
	 */
	public static function get_reader_events_from_db( $client_ids, $type = null, $context = null ) {
		global $wpdb;

		$client_filter  = self::build_partial_query_filter( 'client_id', $client_ids );
		$type_filter    = '';
		$context_filter = '';

		if ( is_array( $type ) ) {
			$type_filter .= 'AND ' . self::build_partial_query_filter( 'type', $type );
		}
		if ( is_array( $context ) ) {
			$context_filter .= 'AND ' . self::build_partial_query_filter( 'context', $context );
		}


		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$events_sql               = "SELECT id, client_id, date_created, type, context, value from $reader_events_table_name WHERE $client_filter $type_filter $context_filter ORDER BY date_created DESC LIMIT 1000";
		$events                   = $wpdb->get_results( $events_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $events ) ) {
			$events = array_map(
				function( $event ) {
					$event = (array) $event;

					if ( ! empty( $event['value'] ) && is_string( $event['value'] ) ) {
						$event['value'] = json_decode( $event['value'], true );
					}

					return $event;
				},
				$events
			);
		}

		return $events;
	}

	/**
	 * Get reader events from the persistent cache, if available.
	 *
	 * @param string $client_id Single client ID associated with the current reader.
	 *
	 * @return array Array of reader events for the given client ID.
	 */
	public static function get_reader_events_from_cache( $client_id ) {
		$events = wp_cache_get( 'reader_events', $client_id );
		if ( ! $events ) {
			$events = [];
		}

		return $events;
	}

	/**
	 * Retrieve data for a specific reader from the cache or DB.
	 *
	 * @param string|array      $client_ids Client IDs of the reader.
	 * @param string|array|null $type Type or types of data to retrieve.
	 *                                If not given, only return temporary data types.
	 * @param string|array|null $context Context or contexts of data to retrieve.
	 * @param boolean           $ignore_cache If true, skip cache and get events directly from DB.
	 *
	 * @return array Array of reader data, optionally filtered by $type and $context.
	 */
	public static function get_reader_events( $client_ids, $type = null, $context = null, $ignore_cache = false ) {
		if ( ! is_array( $client_ids ) ) {
			$client_ids = [ $client_ids ];
		}
		if ( ! is_array( $type ) && null !== $type ) {
			$type = [ $type ];
		}
		if ( ! is_array( $context ) && null !== $context ) {
			$context = [ $context ];
		}

		$events = [];

		// Check the cache first.
		if ( ! $ignore_cache ) {
			$events = self::get_reader_events_from_cache( $client_ids[0] );

			if ( ! empty( $events ) ) {
				$get_cached_events = true;

				// If cached events are missing events of any type/context, fetch from the DB so we don't miss anything.
				foreach ( $type as $single_type ) {
					$filtered_events = self::filter_events_by_type( $events, $single_type );

					if ( empty( $filtered_events ) ) {
						$get_cached_events = false;
					}
				}

				if ( $get_cached_events ) {
					return self::filter_events_by_type( $events, $type, $context );
				}
			}
		}

		$events_from_db  = self::get_reader_events_from_db( $client_ids, $type, $context );
		$unique_ids      = [];
		$filtered_events = array_values(
			array_filter(
				$events_from_db,
				function( $event ) use ( $events ) {
					// If the event is coming from the persistent cache, fashion a faux-unique ID using the timestamp.
					if ( ! isset( $event['id'] ) ) {
						$event['id'] = $event['type'] . $event['context'] . $event['date_created'];
					}

					// Dedupe events from cache.
					foreach ( $events as $existing_event ) {
						if ( $event['id'] === $existing_event['id'] ) {
							return false;
						}
					}

					return true;
				}
			)
		);

		return $filtered_events;
	}

	/**
	 * Get view events from the past hour that match the given view event.
	 * Lets us determine whether a view has already occurred and how many times.
	 *
	 * @param string $client_id Client ID for the reader.
	 * @param string $context Context of view event to check.
	 * @param string $value Value of view event to check.
	 *
	 * @return boolean True if the page has already been visited by this client in the past hour.
	 */
	public static function is_repeat_visit( $client_id, $context, $value ) {
		$already_read = array_values(
			array_filter(
				self::get_reader_events( $client_id, 'view', $context ),
				function( $event ) use ( $value ) {
					if ( isset( $event['value'] ) ) {
						if ( isset( $event['value']['post_id'] ) ) {
							return (int) $event['value']['post_id'] === $value;
						}
						if ( isset( $event['value']['request'] ) ) {
							return wp_json_encode( $event['value']['request'] ) === $value;
						}
					}
					return false;
				}
			)
		);

		return 0 < count( $already_read );
	}
}
