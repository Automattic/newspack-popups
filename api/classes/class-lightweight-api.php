<?php
/**
 * Newspack Campaigns lightweight API.
 *
 * @package Newspack
 */

/**
 * Create the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../segmentation/class-segmentation-report.php';

/**
 * API endpoints.
 */
class Lightweight_API {

	/**
	 * Response object.
	 *
	 * @var response
	 */
	public $response = [];

	/**
	 * Debugging info.
	 *
	 * @var debug
	 */
	public $debug;

	/**
	 * If true, get all reader data from the DB instead of the object cache.
	 *
	 * @var ignore_cache
	 */
	public $ignore_cache = false;

	/**
	 * Default reader data.
	 *
	 * @var reader_data_blueprint
	 */
	public $reader_data_blueprint = [
		'date_created'    => null,
		'date_modified'   => null,
		'is_preview'      => false,
		'user_id'         => null,
		'article_views'   => 0,
		'page_views'      => 0,
		'categories_read' => [],
	];

	/**
	 * Default client data (LEGACY).
	 *
	 * @var client_data_blueprint
	 */
	private $client_data_blueprint = [
		'posts_read'          => [],
		'donations'           => [],
		'email_subscriptions' => [],
		'user_id'             => false,
		'prompts'             => [],
	];

	/**
	 * Constructor.
	 *
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		if ( $this->is_a_web_crawler() ) {
			header( 'X-Robots-Tag: noindex' );
			exit;
		}
		if ( ! $this->verify_referer() ) {
			$this->error( 'invalid_referer' );
		}
		$this->debug = [
			'read_query_count'   => 0,
			'write_query_count'  => 0,
			'delete_query_count' => 0,
			'cache_count'        => 0,
			'start_time'         => microtime( true ),
			'end_time'           => null,
			'duration'           => null,
			'suppression'        => [],
			'events'             => [],
		];

		// If we don't have a persistent object cache, we can't rely on it across page views.
		if ( ! class_exists( 'Memcache' ) || ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) ) {
			$this->ignore_cache = true;
		}
	}

	/**
	 * Is the request coming from a common web crawler?
	 */
	public function is_a_web_crawler() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
			return false;
		}
		$user_agent = $_SERVER['HTTP_USER_AGENT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__
		// https://www.keycdn.com/blog/web-crawlers.
		$common_web_crawlers_user_agents = [
			'Googlebot',
			'Bingbot',
			'Slurp',
			'DuckDuckBot',
			'Baiduspider',
			'YandexBot',
			'facebot',
			'ia_archiver',
		];
		foreach ( $common_web_crawlers_user_agents as $crawler_agent ) {
			if ( stristr( $user_agent, $crawler_agent ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Verify referer is valid.
	 */
	public function verify_referer() {
		$http_referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? parse_url( $_SERVER['HTTP_REFERER'] , PHP_URL_HOST ) : null; // phpcs:ignore
		$valid_referers = [
			$http_referer,
		];
		$server_name = ! empty( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : null; // phpcs:ignore
		// Enable requests from AMP Cache.
		if ( preg_match( '/\.cdn\.ampproject\.org/', $http_referer ) ) {
			return true;
		}
		return ! empty( $http_referer ) && ! empty( $server_name ) && in_array( strtolower( $server_name ), $valid_referers, true );
	}

	/**
	 * Complete the API and print response.
	 *
	 * @codeCoverageIgnore
	 */
	public function respond() {
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}
		$this->debug['end_time'] = microtime( true );
		$this->debug['duration'] = $this->debug['end_time'] - $this->debug['start_time'];
		if ( isset( $_REQUEST['debug'] ) || ( defined( 'NEWSPACK_POPUPS_DEBUG' ) && NEWSPACK_POPUPS_DEBUG ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->response['debug'] = $this->debug;
		}
		header( 'Access-Control-Allow-Origin: https://' . parse_url( $_SERVER['HTTP_REFERER'] )['host'], false ); // phpcs:ignore
		header( 'Access-Control-Allow-Credentials: true', false );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		http_response_code( 200 );
		print json_encode( $this->response ); // phpcs:ignore
		exit;
	}

	/**
	 * Return a 400 code error.
	 *
	 * @param string $code The error code.
	 */
	public function error( $code ) {
		// Exiting in test env would report the test as passed.
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}

		http_response_code( 400 );
		print json_encode( [ 'error' => $code ] ); // phpcs:ignore
		exit;
	}

	/**
	 * Get data from transient.
	 *
	 * @param string $name The transient's name.
	 */
	public function get_transient_legacy( $name ) {
		global $wpdb;
		$name         = '_transient_' . $name;
		$table_name   = Segmentation::get_readers_table_name_legacy();
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) == $table_name; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Bail if legacy table doesn't exist.
		if ( ! $table_exists ) {
			return null;
		}

		$this->debug['read_query_count'] += 1;

		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$table_name` WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $value ? maybe_unserialize( $value ) : null;
	}

	/**
	 * Delete transient.
	 *
	 * @param string $name The transient's name.
	 */
	public function delete_transient_legacy( $name ) {
		global $wpdb;
		$name       = '_transient_' . $name;
		$table_name = Segmentation::get_readers_table_name_legacy();

		wp_cache_delete( $name );
		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$table_name,
			[ 'option_name' => $name ]
		);

		$this->debug['delete_query_count'] += 1;
	}

	/**
	 * Given an array of reader events, only return those with matching $event_types.
	 * If $event_types is null, simply return the events array as-is.
	 *
	 * @param array             $events Array of reader events.
	 * @param string|array|null $event_types Event type or array of event types to filter by.
	 *
	 * @return array Filtered array of events.
	 */
	public function filter_events_by_type( $events, $event_types = null ) {
		if ( null === $event_types ) {
			return $events;
		}

		if ( ! is_array( $event_types ) ) {
			$event_types = [ $event_types ];
		}

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $event_types ) {
					return in_array( $event['type'], $event_types, true );
				}
			)
		);
	}

	/**
	 * Determine if the given client ID originated in a preview.
	 *
	 * @param string $client_id Client ID to check.
	 *
	 * @return boolean True if a preview.
	 */
	public function is_preview( $client_id ) {
		return 'preview' === substr( $client_id, 0, 7 );
	}

	/**
	 * Get data for a specific reader.
	 *
	 * @param string $client_id Client ID.
	 *
	 * @return array Reader data.
	 */
	public function get_reader_data( $client_id ) {
		$reader_data = $this->reader_data_blueprint;

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$cached_reader_data = wp_cache_get( 'reader_data', $client_id );
			if ( ! empty( $cached_reader_data ) ) {
				return $cached_reader_data;
			}
		}

		// If ignoring cache or there's no cached reader data, retrieve from the DB.
		global $wpdb;
		$readers_table_name  = Segmentation::get_readers_table_name();
		$reader_data_from_db = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * from $readers_table_name WHERE client_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		$this->debug['read_query_count'] += 1;

		// Rebuild the cache.
		if ( ! empty( $reader_data_from_db ) ) {
			$reader_data_from_db = reset( $reader_data_from_db );
			$reader_data_from_db = (array) $reader_data_from_db;

			// Unserialize data.
			foreach ( $reader_data_from_db as $column => $value ) {
				$reader_data_from_db[ $column ] = maybe_unserialize( $value );
			}

			$reader_data = wp_parse_args( $reader_data_from_db, $reader_data );
		}

		// Rebuild cache.
		wp_cache_set( 'reader_data', $reader_data, $client_id );

		$this->debug['reader'] = $reader_data;
		return $reader_data;
	}

	/**
	 * Retrieve events for a specific reader.
	 *
	 * @param string      $client_id Client ID of the reader.
	 * @param string|null $event_type Type of event to retrieve.
	 *                                If null, retrieve all events for the given reader.
	 */
	public function get_reader_events( $client_id, $event_type = null ) {
		$events = [];

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$cached_events = wp_cache_get( 'reader_events', $client_id );
			if ( ! empty( $cached_events ) ) {
				return $this->filter_events_by_type( $cached_events, $event_type );
			}
		}

		// If ignoring cache or there are no cached events, retrieve from the DB.
		global $wpdb;
		$events_table_name = Segmentation::get_events_table_name();
		$events_from_db    = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT client_id, type, date_created, event_value, is_preview from $events_table_name WHERE client_id = %s ORDER BY date_created DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		$this->debug['read_query_count'] += 1;

		if ( $events_from_db ) {
			$events_from_db = array_map(
				function( $item ) {
					$item                = (array) $item;
					$item['event_value'] = (array) maybe_unserialize( $item['event_value'] );
					$item['is_preview']  = (bool) $item['is_preview'];
					return $item;
				},
				$events_from_db
			);
			$events         = array_merge( $events, $events_from_db );
		}

		// Rebuild cache.
		if ( ! empty( $events ) ) {
			wp_cache_set( 'reader_events', $events, $client_id );
		}

		return $this->filter_events_by_type( $events, $event_type );
	}

	/**
	 * Save reader events.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $events Array of reader events to log.
	 *                     ['client_id'] Client ID associated with the event.
	 *                     ['date_created'] Timestamp of the event. Optional.
	 *                     ['type'] Type of event.
	 *                     ['event_value'] Data associated with the event.
	 *
	 * @return boolean True if saved, false if not.
	 */
	public function save_reader_events( $client_id, $events ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) || empty( $events ) ) {
			return false;
		}

		// Ensure client ID exists and matches across all events.
		$events = array_map(
			function( $event ) use ( $client_id ) {
				$event['client_id'] = $client_id;
				return $event;
			},
			$events
		);

		$existing_events = $this->get_reader_events( $client_id );
		$is_preview      = $this->is_preview( $client_id );
		$already_read    = array_column(
			array_column(
				$existing_events,
				'event_value'
			),
			'post_id'
		);

		// Deduplicate article and page views from the past hour.
		$events = array_values(
			array_filter(
				$events,
				function( $event ) use ( $already_read, &$view_events ) {
					if (
						( 'article_view' === $event['type'] || 'page_view' === $event['type'] ) &&
						isset( $event['event_value'] ) &&
						isset( $event['event_value']['post_id'] )
					) {
						return ! in_array( $event['event_value']['post_id'], $already_read, true );
					}

					return true;
				}
			)
		);

		// Add is_preview value for preview requests.
		if ( $is_preview ) {
			$events = array_map(
				function( $event ) {
					$event['is_preview'] = true;
					return $event;
				},
				$events
			);
		}

		// If no new events to save, return false.
		if ( empty( $events ) ) {
			$this->debug['already_read'] = true;
			return false;
		}

		// If ignoring cache, write directly to DB.
		if ( $this->ignore_cache ) {
			global $wpdb;
			$columns = [
				'client_id',
				'date_created',
				'type',
				'event_value',
			];

			if ( $is_preview ) {
				$columns[] = 'is_preview';
			}

			$placeholders = array_reduce(
				$events,
				function( $acc, $event ) {
					$placeholder = array_map(
						function() {
							return '%s';
						},
						array_values( $event )
					);

					$placeholder = implode( ', ', $placeholder );
					$acc[]       = "( $placeholder )";

					return $acc;
				},
				[]
			);

			$values = array_reduce(
				$events,
				function( $acc, $event ) {
					$values = array_values( $event );
					return array_merge(
						$acc,
						array_map(
							function( $value ) {
								return maybe_serialize( $value );
							},
							$values
						)
					);
				},
				[]
			);

			$columns           = implode( ', ', $columns );
			$placeholders      = implode( ', ', $placeholders );
			$events_table_name = Segmentation::get_events_table_name();
			$write_result      = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"INSERT INTO $events_table_name ($columns) VALUES $placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$values
				)
			);

			// If DB write was successful, rebuild cache.
			if ( $write_result ) {
				$this->debug['write_query_count'] += 1;
			} else {
				$this->debug['write_error'] = "Error writing to $events_table_name.";
				return false;
			}
		} else {
			// Write events to the flat file to be parsed to the DB at a later point.
			Segmentation_Report::log_reader_events( $events );
		}

		// Rebuild reader data if there were any article or page views.
		$article_view_events = array_filter(
			$events,
			function( $event ) {
				return 'article_view' === $event['type'];
			}
		);
		$page_view_events    = array_filter(
			$events,
			function( $event ) {
				return 'page_view' === $event['type'];
			}
		);

		if ( 0 < count( $article_view_events ) || 0 < count( $page_view_events ) ) {
			$reader_data        = $this->get_reader_data( $client_id );
			$reader_data_update = [];
			$article_views      = (int) $reader_data['article_views'];
			$page_views         = (int) $reader_data['page_views'];
			$categories_read    = is_array( $reader_data['categories_read'] ) ? $reader_data['categories_read'] : [];
			$viewed_categories  = array_column(
				array_column(
					$article_view_events,
					'event_value'
				),
				'categories'
			);
			$viewed_categories  = array_reduce(
				$viewed_categories,
				function( $acc, $categories ) {
					if ( $categories ) {
						foreach ( explode( ',', $categories ) as $category ) {
							if ( ! isset( $acc[ $category ] ) ) {
								$acc[ $category ] = 0;
							}
							$acc[ $category ] ++;
						}
					}
					return $acc;
				},
				[]
			);

			// Increment article/page view counts and add category data.
			$reader_data_update['article_views'] = $article_views + count( $article_view_events );
			$reader_data_update['page_views']    = $page_views + count( $page_view_events );

			if ( ! empty( $viewed_categories ) ) {
				foreach ( $viewed_categories as $category_id => $view_count ) {
					if ( ! isset( $categories_read[ $category_id ] ) ) {
						$categories_read[ $category_id ] = 0;
					}
					$categories_read[ $category_id ] += $view_count;
				}
				$reader_data_update['categories_read'] = $categories_read;
			}

			if ( ! empty( $reader_data_update ) ) {
				$this->save_reader_data( $client_id, $reader_data_update );
			}
		}

		// Rebuild cache.
		$all_events            = array_merge( $existing_events, $events );
		$this->debug['events'] = array_merge( $this->debug['events'], $all_events );

		return wp_cache_set(
			'reader_events',
			$all_events,
			$client_id
		);
	}

	/**
	 * Upsert reader data to DB and save to cache.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $reader_data Data to save.
	 *
	 * @return boolean True if updated., false if not.
	 */
	public function save_reader_data( $client_id, $reader_data = [] ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) ) {
			return false;
		}

		global $wpdb;

		// Ensure that we're only updating valid columns.
		$valid_columns = array_keys( $this->reader_data_blueprint );
		foreach ( $reader_data as $key => $value ) {
			if ( ! in_array( $key, $valid_columns, true ) ) {
				unset( $reader_data[ $key ] );
			}
		}

		// First, check the legacy transients table for existing reader data.
		$legacy_reader_data = self::get_transient_legacy( $client_id );

		// If there's data for this reader in the legacy transients table, recreate or update it in the new table.
		if ( ! empty( $legacy_reader_data ) ) {
			$this->debug['legacy_data'] = $legacy_reader_data;

			// Add posts_read data to article views count.
			if ( isset( $legacy_reader_data['posts_read'] ) && 0 < count( $legacy_reader_data ) ) {
				if ( ! isset( $reader_data['article_views'] ) ) {
					$reader_data['article_views'] = 0;
				}
				$reader_data['article_views'] += count( $legacy_reader_data['posts_read'] );

				// Add read categories data.
				$categories_read = ! empty( $reader_data['categories_read'] ) ? $reader_data['categories_read'] : [];
				foreach ( $legacy_reader_data['posts_read'] as $article_view ) {
					if ( ! empty( $article_view['category_ids'] ) ) {
						$category_ids = explode( ',', $article_view['category_ids'] );

						foreach ( $category_ids as $category_id ) {
							if ( ! isset( $categories_read[ $category_id ] ) ) {
								$categories_read[ $category_id ] = 0;
							}
							$categories_read[ $category_id ] ++;
						}
					}
				}
				$reader_data['categories_read'] = $categories_read;
			}

			// Add known user accounts.
			if ( empty( $reader_data['user_id'] ) && ! empty( $legacy_reader_data['user_id'] ) ) {
				$reader_data['user_id'] = $legacy_reader_data['user_id'];
			}
		}

		$reader_data['client_id'] = $client_id;
		$readers_table_name       = Segmentation::get_readers_table_name();
		$is_preview               = $this->is_preview( $client_id );

		if ( $is_preview ) {
			$reader_data['is_preview'] = true;
		}

		$placeholders      = [];
		$columns_to_update = array_keys( $reader_data );
		$values_to_update  = array_map(
			function( $value_to_update ) use ( &$placeholders ) {
				$placeholders[] = '%s';
				return maybe_serialize( $value_to_update );
			},
			array_values( $reader_data )
		);
		$columns_to_update = implode( ', ', $columns_to_update );
		$placeholders      = implode( ', ', $placeholders );

		// If a row with this client ID already exists, update the existing row.
		$duplicate_placeholders = [];
		foreach ( $reader_data as $column => $value ) {
			$duplicate_placeholders[] = "$column = %s";
			$values_to_update[]       = maybe_serialize( $value ); // Duplicate the values for the second part of the query.
		}
		$duplicate_placeholders = implode( ', ', $duplicate_placeholders );

		// Write to the DB.
		$write_result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"INSERT INTO $readers_table_name ($columns_to_update, date_created) VALUES ($placeholders, current_timestamp()) ON DUPLICATE KEY UPDATE $duplicate_placeholders, date_modified = current_timestamp()", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$values_to_update
			)
		);

		// If there's data for this reader in the legacy transients table, add events to the new events table.
		// This must be done AFTER a row exists in the readers table to avoid an infinite loop.
		if ( $write_result && $legacy_reader_data ) {
			$legacy_events = [];

			// Add prior donations.
			if ( ! empty( $legacy_reader_data['donations'] ) ) {
				foreach ( $legacy_reader_data['donations'] as $donation ) {
					$donation_date   = isset( $donation['date'] ) ? strtotime( $donation['date'] ) : time();
					$legacy_events[] = [
						'client_id'    => $client_id,
						'date_created' => gmdate( 'Y-m-d H:i:s', $donation_date ),
						'type'         => 'donation',
						'event_value'  => $donation,
					];
				}
			}

			// Add prior newsletter subscriptions.
			if ( ! empty( $legacy_reader_data['email_subscriptions'] ) ) {
				foreach ( $legacy_reader_data['email_subscriptions'] as $subscription ) {
					$legacy_events[] = [
						'client_id'    => $client_id,
						'date_created' => gmdate( 'Y-m-d H:i:s' ),
						'type'         => 'subscription',
						'event_value'  => [
							'email' => $subscription['email'] ?? $subscription['address'],
						],
					];
				}
			}

			// Save legacy events to new events table.
			if ( ! empty( $legacy_events ) ) {
				self::save_reader_events( $client_id, $legacy_events );
			}

			// If we were able to save the legacy data, clean up old transients data.
			self::delete_transient_legacy( $client_id );
		}

		// If DB write was successful, rebuild cache.
		if ( $write_result ) {
			$this->debug['write_query_count'] += 1;
			$cached_reader_data                = $this->get_reader_data( $client_id );

			return wp_cache_set(
				'reader_data',
				wp_parse_args( $reader_data, $cached_reader_data ),
				$client_id
			);
		}

		$this->debug['write_error'] = "Error writing to $readers_table_name.";
		return false;
	}

	/**
	 * Retrieve a large sample of reader' data.
	 *
	 * @return array All clients' data.
	 */
	public function get_all_readers_data() {
		global $wpdb;
		$raders_table_name = Segmentation::get_readers_table_name();

		// Results are limited to the 1000 most recent rows for performance reasons.
		$all_client_ids_rows = $wpdb->get_results( "SELECT DISTINCT client_id,date_modified FROM $readers_table_name ORDER BY date_modified DESC LIMIT 1000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$api                 = new Lightweight_API();
		return array_reduce(
			$all_client_ids_rows,
			function ( $acc, $row ) use ( $api ) {
				// Disregard client data created during previewing.
				if ( ! $this->is_preview( $row->client_id ) ) {
					$acc[] = $row->client_id;
				}
				return $acc;
			},
			[]
		);
	}

	/**
	 * Get request param.
	 *
	 * @param string $param Param name.
	 * @param object $request A POST request.
	 */
	public function get_request_param( $param, $request ) {
		$value = isset( $request[ $param ] ) ? $request[ $param ] : false; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return $value;
	}

	/**
	 * Get POST request payload.
	 */
	public function get_post_payload() {
		$payload = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( null == $payload ) {
			// A POST request made by amp-analytics has to be parsed in this way.
			// $_POST contains the payload if the request has FormData.
			$payload = file_get_contents( 'php://input' ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsRemoteFile
			if ( ! empty( $payload ) ) {
				$payload = (array) json_decode( $payload );
			}
		}
		if ( null == $payload ) {
			// Of all else fails, look for payload in query string.
			return $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		}
		return $payload;
	}

	/**
	 * Get option.
	 *
	 * @param string $name Option name.
	 */
	public function get_option( $name ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM $wpdb->options WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
