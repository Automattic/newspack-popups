<?php
/**
 * Newspack Campaigns lightweight API
 *
 * @package Newspack
 * @phpcs:disable WordPress.DB.RestrictedClasses.mysql__PDO
 */

/**
 * API endpoints
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
	private $reader_data_blueprint = [
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
			'read_query_count'       => 0,
			'write_query_count'      => 0,
			'delete_query_count'     => 0,
			'cache_count'            => 0,
			'read_empty_transients'  => 0,
			'write_read_query_count' => 0,
			'start_time'             => microtime( true ),
			'end_time'               => null,
			'duration'               => null,
			'suppression'            => [],
			'events'                 => [],
		];

		// If we don't have a persistent object cache, we can't rely on it across page views.
		if ( ! class_exists( 'Memcache' ) ) {
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
	public function get_transient( $name ) {
		global $wpdb;
		$name       = '_transient_' . $name;
		$table_name = Segmentation::get_readers_table_name_legacy();
		$value      = wp_cache_get( $name, 'newspack-popups' );

		if ( false === $value ) {
			$this->debug['read_query_count'] += 1;

			$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM `$table_name` WHERE option_name = %s LIMIT 1", $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( $value ) {
				// Transient found; cache value.
				wp_cache_set( $name, $value, 'newspack-popups' );
			} else {
				// No transient and no DB entry found.
				return null;
			}
		} else {
			$this->debug['cache_count'] += 1;
		}

		return maybe_unserialize( $value );
	}

	/**
	 * Upsert transient.
	 *
	 * @param string $name The transient's name.
	 * @param string $value THe transient's value.
	 */
	public function set_transient( $name, $value ) {
		global $wpdb;
		$table_name       = Segmentation::get_readers_table_name_legacy();
		$name             = '_transient_' . $name;
		$serialized_value = maybe_serialize( $value );
		wp_cache_set( $name, $serialized_value, 'newspack-popups' );
		$result           = $wpdb->query( $wpdb->prepare( "INSERT INTO `$table_name` (`option_name`, `option_value`, `date`) VALUES (%s, %s, current_timestamp()) ON DUPLICATE KEY UPDATE `option_name` = VALUES(`option_name`), `option_value` = VALUES(`option_value`), `date` = VALUES(`date`)", $name, $serialized_value ) ); // phpcs:ignore

		$this->debug['write_query_count'] += 1;
	}

	/**
	 * Delete transient.
	 *
	 * @param string $name The transient's name.
	 */
	public function delete_transient( $name ) {
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
	 * Retrieve prompt data.
	 *
	 * @param string $client_id Client ID.
	 * @param string $campaign_id Prompt ID.
	 * @return object Prompt data.
	 */
	public function get_campaign_data( $client_id, $campaign_id ) {
		$prompt = [];
		$data   = $this->get_transient( $client_id );

		if ( $data && isset( $data['prompts'] ) && isset( $data['prompts'][ $campaign_id ] ) ) {
			// NEW FORMAT - one row per reader.
			$prompt = $data['prompts'][ $campaign_id ];
		}

		return [
			'count'       => ! empty( $prompt['count'] ) ? (int) $prompt['count'] : 0,
			'last_viewed' => ! empty( $prompt['last_viewed'] ) ? (int) $prompt['last_viewed'] : 0,
		];
	}

	/**
	 * Given an array of reader events, only return those with the matching $event_type.
	 * If $event_type is null, simply return the events array as-is.
	 *
	 * @param array       $events Array of reader events.
	 * @param string|null $event_type Type of event to filter by.
	 *
	 * @return array Filtered array of events.
	 */
	public function filter_events_by_type( $events, $event_type = null ) {
		if ( null === $event_type ) {
			return $events;
		}

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $event_type ) {
					return $event_type === $event['type'];
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

		// If there's no reader data in the DB, create it.
		if ( empty( $reader_data_from_db ) ) {
			$this->save_reader_data( $client_id, [] );
		} else {
			$reader_data_from_db = reset( $reader_data_from_db );
			$reader_data_from_db = (array) $reader_data_from_db;

			// Unserialize data.
			foreach ( $reader_data_from_db as $column => $value ) {
				$reader_data_from_db[ $column ] = maybe_unserialize( $value );
			}

			$reader_data = wp_parse_args( $reader_data_from_db, $reader_data );
		}

		$this->debug['reader'] = $reader_data_from_db;

		// Rebuild cache.
		wp_cache_set( 'reader_data', $reader_data, $client_id );

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
				"SELECT * from $events_table_name WHERE client_id = %s ORDER BY date_created DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		if ( $events_from_db ) {
			$events_from_db = array_map(
				function( $item ) {
					$item                = (array) $item;
					$item['event_value'] = (array) maybe_unserialize( $item['event_value'] );
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
		$existing_events = $this->get_reader_events( $client_id );
		$is_preview      = $this->is_preview( $client_id );

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

		// Rebuild cache.
		$existing_events = $this->get_reader_events( $client_id );
		$all_events      = array_merge( $existing_events, $events );

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
	public function save_reader_data( $client_id, $reader_data ) {
		global $wpdb;
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
				"INSERT INTO $readers_table_name ($columns_to_update, date_created) VALUES ($placeholders, current_timestamp()) ON DUPLICATE KEY UPDATE $duplicate_placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$values_to_update
			)
		);

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
	 * Retrieve client data.
	 * TODO: Retrieve client data and events.
	 *
	 * @param string $client_id Client ID.
	 * @param bool   $do_not_rebuild Whether to rebuild cache if not found.
	 */
	public function get_client_data_legacy( $client_id, $do_not_rebuild = false ) {
		$data = $this->get_transient( $client_id );
		if ( $data ) {
			// Handle legacy data which might not have some default keys.
			return array_merge(
				$this->client_data_blueprint,
				$data
			);
		}

		if ( $do_not_rebuild ) {
			return $this->client_data_blueprint;
		}

		// Rebuild cache, it might've been purged or it's the first time.
		global $wpdb;
		$events_table_name       = Segmentation::get_events_table_name_legacy();
		$client_post_read_events = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT * FROM $events_table_name WHERE client_id = %s AND type = 'post_read'", $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// If there is no relevant client data in the events table, do not save any data.
		if ( 0 === count( $client_post_read_events ) ) {
			return $this->client_data_blueprint;
		}

		$this->save_client_data_legacy(
			$client_id,
			[
				'posts_read' => array_map(
					function ( $item ) {
						return [
							'post_id'      => $item->post_id,
							'category_ids' => $item->category_ids,
							'created_at'   => $item->created_at,
						];
					},
					$client_post_read_events
				),
			]
		);

		return $this->get_transient( $client_id );
	}

	/**
	 * Retrieve all clients' data.
	 *
	 * @return array All clients' data.
	 */
	public function get_all_clients_data_legacy() {
		global $wpdb;
		$events_table_name = Segmentation::get_events_table_name();

		// Results are limited to the 1000 most recent rows for performance reasons.
		$all_client_ids_rows = $wpdb->get_results( "SELECT DISTINCT client_id,id FROM $events_table_name ORDER BY id DESC LIMIT 1000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$api                 = new Lightweight_API();
		return array_reduce(
			$all_client_ids_rows,
			function ( $acc, $row ) use ( $api ) {
				// Disregard client data created during previewing.
				if ( false === strpos( $row->client_id, 'preview' ) ) {
					$acc[] = $api->get_client_data_legacy( $row->client_id, true );
				}
				return $acc;
			},
			[]
		);
	}

	/**
	 * Save client data.
	 * TODO: Update client data and events.
	 *
	 * @param string $client_id Client ID.
	 * @param string $client_data_update Client data.
	 */
	public function save_client_data_legacy( $client_id, $client_data_update ) {
		$existing_client_data = $this->get_client_data_legacy( $client_id, true );

		// Update prompts data: new data should replace existing data.
		if ( isset( $client_data_update['prompts'] ) && ! empty( $existing_client_data['prompts'] ) ) {
			$client_data_update['prompts']   = array_replace_recursive(
				$existing_client_data['prompts'],
				$client_data_update['prompts']
			);
			$existing_client_data['prompts'] = []; // Unset old client data to avoid data duplication on array_merge_recursive below.
		}

		// Update client data: merge data so we don't lose historical read, subscription, or donation data.
		$updated_client_data                        = array_merge_recursive(
			$existing_client_data,
			$client_data_update
		);
		$updated_client_data['posts_read']          = array_unique( $updated_client_data['posts_read'], SORT_REGULAR );
		$updated_client_data['email_subscriptions'] = array_unique( $updated_client_data['email_subscriptions'], SORT_REGULAR );
		$updated_client_data['donations']           = array_unique( $updated_client_data['donations'], SORT_REGULAR );
		if ( isset( $client_data_update['user_id'] ) ) {
			$updated_client_data['user_id'] = $client_data_update['user_id'];
		}
		return $this->set_transient(
			$client_id,
			$updated_client_data
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
