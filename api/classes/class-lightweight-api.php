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
require_once __DIR__ . '/../../vendor/autoload.php';

use \DrewM\MailChimp\MailChimp;

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
	 * If true, get all reader events from the DB instead of the object cache.
	 *
	 * @var ignore_cache
	 */
	public $ignore_cache = false;

	/**
	 * Default reader.
	 *
	 * @var reader_blueprint
	 */
	public $reader_blueprint = [
		'date_created'  => null,
		'date_modified' => null,
		'reader_data'   => [],
		'is_preview'    => false,
	];

	/**
	 * Default reader event item.
	 *
	 * @var reader_events_blueprint
	 */
	public $reader_events_blueprint = [
		'date_created' => null,
		'type'         => null,
		'context'      => null,
		'value'        => null,
	];

	/**
	 * Reader events can be temporary (purged periodically) or persistent depending on the type.
	 * The following `type` values are considered persistent data.
	 *
	 * @var reader_events_persistent_types
	 */
	public $reader_events_persistent_types = [
		'subscription',
		'donation',
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
			'reader'             => null,
			'reader_events'      => [],
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
	 * Upsert multiple rows in one DB transaction.
	 *
	 * @param array $events Array of reader events to save.
	 *
	 * @return boolean Result of the write query: true if successful, otherwise false.
	 */
	public function bulk_db_insert( $events ) {
		$write_result = false;

		if ( 0 === count( $events ) ) {
			return $write_result;
		}

		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$query                    = $this->get_sql( $reader_events_table_name, $events );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $write_result ) {
			$this->debug['write_error'] = "Error writing to $reader_events_table_name.";
		}

		return $write_result;
	}

	/**
	 * Get a specific reader.
	 *
	 * @param string $client_id Client ID.
	 *
	 * @return array Reader object.
	 */
	public function get_reader( $client_id ) {
		$reader              = $this->reader_blueprint;
		$reader['client_id'] = $client_id;

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$cached_reader = wp_cache_get( 'reader', $client_id );
			if ( ! empty( $cached_reader ) ) {
				$this->debug['reader'] = $cached_reader;
				return $cached_reader;
			}
		}

		// If ignoring cache or there's no cached reader, retrieve from the DB.
		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();
		$reader_from_db     = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * from $readers_table_name WHERE client_id = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		$this->debug['read_query_count'] += 1;

		// If there's no reader for this client ID in the DB, create it.
		if ( empty( $reader_from_db ) ) {
			$reader_events = [];

			// Check the legacy transients table for existing reader.
			$legacy_reader = $this->get_transient_legacy( $client_id );

			// If there's data for this reader in the legacy transients table, recreate it in the new table.
			if ( ! empty( $legacy_reader ) ) {
				$this->debug['legacy_reader'] = $legacy_reader;

				// Add posts_read data to views count.
				if ( isset( $legacy_reader['posts_read'] ) && 0 < count( $legacy_reader['posts_read'] ) ) {
					$reader['reader_data']['views'] = [ 'post' => count( $legacy_reader['posts_read'] ) ];

					// Add read categories data.
					$categories_read = ! empty( $reader['reader_data']['category'] ) ? $reader['reader_data']['category'] : [];
					foreach ( $legacy_reader['posts_read'] as $article_view ) {

						// Rebuild recent views as view events.
						if ( isset( $article_view['created_at'] ) ) {
							$hour_ago   = strtotime( '-1 hour', time() );
							$event_time = strtotime( $article_view['created_at'] );

							if ( $event_time > $hour_ago ) {
								$view_event = [
									'date_created' => gmdate( 'Y-m-d H:i:s', $event_time ),
									'type'         => 'view',
									'context'      => 'post',
									'value'        => [],
								];

								if ( isset( $article_view['post_id'] ) ) {
									$view_event['value']['post_id'] = $article_view['post_id'];
								}
								if ( isset( $article_view['categories'] ) ) {
									$view_event['value']['categories'] = $article_view['categories'];
								}

								$reader_events[] = $view_event;
							}
						}

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

					if ( ! empty( $categories_read ) ) {
						$reader['reader_data']['category'] = $categories_read;
					}
				}

				// Add known user accounts.
				if ( ! empty( $legacy_reader['user_id'] ) ) {
					$reader_events[] = [
						'type'    => 'user_account',
						'context' => 'wp',
						'value'   => [ 'user_id' => $legacy_reader['user_id'] ],
					];
				}

				// Add prior donations.
				if ( ! empty( $legacy_reader['donations'] ) ) {
					foreach ( $legacy_reader['donations'] as $donation ) {
						$donation      = (array) $donation;
						$donation_date = isset( $donation['date'] ) ? strtotime( $donation['date'] ) : time();
						$donation_data = [
							'date_created' => gmdate( 'Y-m-d H:i:s', $donation_date ),
							'type'         => 'donation',
							'value'        => $donation,
						];

						if ( isset( $donation['order_id'] ) ) {
							$donation_data['context'] = 'woocommerce';
						}
						if ( isset( $donation['stripe_id'] ) ) {
							$donation_data['context'] = 'stripe';
						}

						$reader_events[] = $donation_data;
					}
				}

				// Add prior newsletter subscriptions.
				if ( ! empty( $legacy_reader['email_subscriptions'] ) ) {
					foreach ( $legacy_reader['email_subscriptions'] as $subscription ) {
						$reader_events[] = [
							'type'  => 'subscription',
							'value' => [
								'email' => $subscription['email'] ?? $subscription['address'],
							],
						];
					}
				}

				if ( ! empty( $reader_events ) ) {
					$this->save_reader_events( $client_id, $reader_events );
				}

				// If we were able to save the legacy data, clean up old transients data.
				$this->delete_transient_legacy( $client_id );
			}

			$this->save_reader( $client_id, $reader['reader_data'] );
		} else {
			// Rebuild the cache.
			$reader_from_db = reset( $reader_from_db );
			$reader_from_db = (array) $reader_from_db;
			$reader         = wp_parse_args( $reader_from_db, $reader );
		}

		// Unserialize reader_data value.
		if ( isset( $reader['reader_data'] ) && ! is_array( $reader['reader_data'] ) ) {
			$reader['reader_data'] = json_decode( $reader['reader_data'], true );
		}

		// Rebuild cache.
		wp_cache_set( 'reader', $reader, $client_id );

		$this->debug['reader'] = $reader;
		return $reader;
	}

	/**
	 * Given an associative array, format the keys and values into columns, values,
	 * and placeholders to be used in an INSERT INTO...ON DUPLICATE KEY SQL query.
	 * All data items in the array should have the same keys.
	 *
	 * @param string $table_name Name of the table to execute the query on.
	 * @param array  $data Associative array of data items to format. Array keys
	 *                     should correspond to table column names.
	 *
	 * @return array Object containing columns, values, placeholders, and duplicate
	 *               placeholders to be used in a SQL query.
	 */
	public function get_sql( $table_name, $data ) {
		global $wpdb;

		// Placeholders for the INSERT INTO statement.
		$placeholders           = [];
		$columns                = array_keys( $data[0] );
		$values                 = [];
		$duplicate_placeholders = [];
		$is_readers_table       = Segmentation::get_readers_table_name() === $table_name;

		// If updating the readers table, add date_modified timestamp.
		if ( $is_readers_table ) {
			$columns[] = 'date_modified';
		}

		foreach ( $data as $item ) {
			if ( $is_readers_table ) {
				$item['date_modified'] = gmdate( 'Y-m-d H:i:s' );
			}

			// Serialize value strings.
			if ( isset( $item['reader_data'] ) ) {
				$item['reader_data'] = wp_json_encode( $item['reader_data'] );
			}
			if ( isset( $item['value'] ) ) {
				$item['value'] = wp_json_encode( $item['value'] );
			}

			$placeholder = [];
			$item_values = array_map(
				function( $value ) use ( &$placeholder ) {
					$placeholder[] = '%s';
					return $value;
				},
				array_values( $item )
			);

			$placeholder    = implode( ',', $placeholder );
			$placeholders[] = "($placeholder)";
			$values         = array_merge( $values, $item_values );
		}

		// Placeholders for the ON DUPLICATE KEY UPDATE statement.
		foreach ( $columns as $column ) {
			$duplicate_placeholders[] = "$column = values($column)";
		}

		$columns                = implode( ', ', $columns );
		$placeholders           = implode( ', ', $placeholders );
		$duplicate_placeholders = implode( ', ', $duplicate_placeholders );

		return $wpdb->prepare(
			"INSERT INTO $table_name ($columns) VALUES $placeholders ON DUPLICATE KEY UPDATE $duplicate_placeholders", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$values
		);
	}

	/**
	 * Upsert reader to DB and save to cache.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $data Data for the reader to be updated.
	 *
	 * @return boolean True if updated., false if not.
	 */
	public function save_reader( $client_id, $data = [] ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) ) {
			return false;
		}

		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();
		$reader             = [ 'client_id' => $client_id ];

		if ( ! empty( $data ) ) {
			$reader['reader_data'] = $data;
		}

		if ( $this->is_preview( $client_id ) ) {
			$reader['is_preview'] = true;
		}

		$query = $this->get_sql( $readers_table_name, [ $reader ] );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		// If DB write was successful, rebuild cache.
		if ( $write_result ) {
			$this->debug['write_query_count'] += 1;
			return wp_cache_set( 'reader', $reader, $client_id );
		}

		$this->debug['write_error'] = "Error writing to $readers_table_name.";
		return false;
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
	public function filter_events_by_type( $events = [], $types = null, $contexts = null ) {
		if ( null === $types && null === $contexts ) {
			return $events;
		}

		if ( ! is_array( $types ) && null !== $types ) {
			$types = [ $types ];
		}
		if ( ! is_array( $contexts ) && null !== $contexts ) {
			$contexts = [ $contexts ];
		}

		$persistent_types = $this->reader_events_persistent_types;

		return array_values(
			array_filter(
				$events,
				function( $event ) use ( $types, $contexts, $persistent_types ) {
					$matches = true;
					if ( null === $types ) {
						$matches = ! in_array( $event['type'], $persistent_types, true );
					} else {
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
	 * Get all reader data from the DB.
	 *
	 * @param string $client_id Client ID of the reader.
	 *
	 * @return array Array of reader data for the given client ID.
	 */
	public function get_reader_events_from_db( $client_id ) {
		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$events                   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT client_id, date_created, type, context, value from $reader_events_table_name WHERE client_id = %s ORDER BY date_created DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		$this->debug['read_query_count'] += 1;

		if ( ! empty( $events ) ) {
			$events = array_map(
				function( $event ) {
					$event = (array) $event;

					// Unserialize event values.
					if ( ! empty( $event['value'] ) && ! is_array( $event['value'] ) ) {
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
	 * Retrieve data for a specific reader from the cache or DB.
	 *
	 * @param string            $client_id Client ID of the reader.
	 * @param string|array|null $type Type or types of data to retrieve.
	 *                                If not given, only return temporary data types.
	 * @param string|array|null $context Context or contexts of data to retrieve.
	 *
	 * @return array Array of reader data, optionally filtered by $type and $context.
	 */
	public function get_reader_events( $client_id, $type = null, $context = null ) {
		$events = [];

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$events = wp_cache_get( 'reader_events', $client_id );
			if ( ! empty( $events ) ) {
				$this->debug['reader_events'] = $events;
				return $this->filter_events_by_type( $events, $type, $context );
			}
		}

		// If no data in the cache, get it from the DB.
		$events = $this->get_reader_events_from_db( $client_id );

		// Rebuild cache.
		if ( ! empty( $events ) ) {
			wp_cache_set( 'reader_events', $events, $client_id );
		}

		$this->debug['reader_events'] = $events;
		return $this->filter_events_by_type( $events, $type, $context );
	}

	/**
	 * Retrieve only persistent data for a specific reader.
	 * Persistent event types are defined in the $get_reader_events_persistent class variable.
	 *
	 * @param string $client_id Client ID of the reader.
	 */
	public function get_reader_events_persistent( $client_id ) {
		$persistent_events = $this->get_reader_events( $client_id, $this->reader_events_persistent_types );

		$this->debug['reader_events_persistent'] = $persistent_events;

		return $persistent_events;
	}

	/**
	 * Save reader data.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $events Array of reader data rows to log.
	 *                       ['date_created'] Timestamp of the data insertion.
	 *                       ['date_modified'] Timestamp of the update.
	 *                       ['type'] Type of data. Required.
	 *                       ['context'] Context of data.
	 *                       ['value'] Value of the data to save. Required.
	 *
	 * @return boolean True if saved, false if not.
	 */
	public function save_reader_events( $client_id, $events ) {
		if ( empty( $client_id ) || empty( $events ) ) {
			return false;
		}

		// Deduplicate views from the past hour.
		$existing_events          = $this->get_reader_events( $client_id );
		$already_read             = $this->filter_events_by_type( $existing_events, 'view' );
		$event_values             = array_column(
			$already_read,
			'value'
		);
		$already_read_singular    = array_column( $event_values, 'post_id' );
		$already_read_nonsingular = array_map(
			function( $request_value ) {
				return wp_json_encode( $request_value );
			},
			array_column( $event_values, 'request' )
		);
		$already_read             = array_merge( $already_read_singular, $already_read_nonsingular );

		// Filter out recently seen views.
		$events = array_values(
			array_filter(
				$events,
				function( $event ) use ( $already_read ) {
					if ( 'view' === $event['type'] && isset( $event['context'] ) && isset( $event['value'] ) ) {
						$current_page = isset( $event['value']['post_id'] ) ? $event['value']['post_id'] : wp_json_encode( $event['value'] ['request'] );
						return ! in_array( $current_page, $already_read, true );
					}

					return ! empty( $event['type'] );
				}
			)
		);

		// If no new items to save, return false.
		if ( empty( $events ) ) {
			$this->debug['already_read'] = true;
			return false;
		}

		// Rebuild reader_data if there were new views.
		$new_views = $this->filter_events_by_type( $events, 'view' );
		if ( ! empty( $new_views ) ) {
			$reader      = $this->get_reader( $client_id );
			$reader_data = isset( $reader['reader_data'] ) ? $reader['reader_data'] : [];

			if ( ! isset( $reader_data['views'] ) ) {
				$reader_data['views'] = [];
			}

			foreach ( $new_views as $view_event ) {
				if ( ! isset( $reader_data['views'][ $view_event['context'] ] ) ) {
					$reader_data['views'][ $view_event['context'] ] = 0;
				}
				$reader_data['views'][ $view_event['context'] ] ++;

				if ( isset( $view_event['value']['categories'] ) ) {
					$categories = explode( ',', $view_event['value']['categories'] );
					if ( ! isset( $reader_data['category'] ) ) {
						$reader_data['category'] = [];
					}
					foreach ( $categories as $term_id ) {
						if ( ! isset( $reader_data['category'][ $term_id ] ) ) {
							$reader_data['category'][ $term_id ] = 0;
						}
						$reader_data['category'][ $term_id ] ++;
					}
				}
			}

			$this->save_reader( $client_id, $reader_data );
		}

		// Ensure client ID exists and keys match across all new events.
		$reader_events_blueprint = $this->reader_events_blueprint;
		$events                  = array_map(
			function( $event ) use ( $client_id, $reader_events_blueprint ) {
				$event['client_id'] = $client_id;

				if ( empty( $event['date_created'] ) ) {
					$event['date_created'] = gmdate( 'Y-m-d H:i:s' );
				}

				return wp_parse_args( $event, $this->reader_events_blueprint );
			},
			$events
		);

		// If ignoring cache, write directly to DB.
		if ( $this->ignore_cache ) {
			$write_result = $this->bulk_db_insert( $events );

			// If DB write was successful, rebuild cache.
			if ( $write_result ) {
				$this->debug['write_query_count'] += 1;
			} else {
				return false;
			}
		} else {
			// Write items to the flat file to be parsed to the DB at a later point.
			Segmentation_Report::log_reader_events( $events );
		}

		// Rebuild cache.
		$all_events = array_merge( $existing_events, $events );

		return wp_cache_set(
			'reader_events',
			$all_events,
			$client_id
		);
	}

	/**
	 * Retrieve a large sample of readers' data.
	 *
	 * @return array Readers' data.
	 */
	public function get_all_readers_data() {
		global $wpdb;
		$readers_table_name = Segmentation::get_readers_table_name();

		// Results are limited to the 1000 most recent rows for performance reasons.
		$all_client_ids_rows = $wpdb->get_results( "SELECT DISTINCT client_id,date_modified FROM $readers_table_name WHERE is_preview IS NULL ORDER BY date_modified DESC LIMIT 1000" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
	 * Get URL query param.
	 *
	 * @param string $param Param name.
	 * @param string $url URL to parse.
	 *
	 * @return string|boolean Value of the param, or false if it's not in the URL.
	 */
	public function get_url_param( $param, $url ) {
		$parsed_url = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		if ( ! empty( $parsed_url['query'] ) ) {
			parse_str( $parsed_url['query'], $query );
			if ( ! empty( $query[ $param ] ) ) {
				return $query[ $param ];
			}
		}

		return false;
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
				$payload = json_decode( $payload, true );
			}
		}
		if ( null == $payload ) {
			// Of all else fails, look for payload in query string.
			return $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended
		}
		return $payload;
	}

	/**
	 * Get Mailchimp client data via API and sync to reader events.
	 *
	 * @param string $client_id Client ID.
	 * @param string $mailchimp_campaign_id Campaign ID extracted from mc_cid param.
	 * @param string $mailchimp_subscriber_id Campaign ID extracted from mc_eid param.
	 */
	public function get_mailchimp_client_data( $client_id, $mailchimp_campaign_id, $mailchimp_subscriber_id ) {
		$reader_events                        = [];
		$mailchimp_api_key_option_name        = 'newspack_mailchimp_api_key';
		$mailchimp_api_key_option_name_legacy = 'newspack_newsletters_mailchimp_api_key';
		global $wpdb;
		$mailchimp_api_key = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name IN (%s,%s) ORDER BY FIELD(%s,%s)", $mailchimp_api_key_option_name, $mailchimp_api_key_option_name_legacy, $mailchimp_api_key_option_name, $mailchimp_api_key_option_name_legacy )
		);
		if ( $mailchimp_api_key ) {
			$mc            = new Mailchimp( $mailchimp_api_key->option_value );
			$campaign_data = $mc->get( "campaigns/$mailchimp_campaign_id" );
			if ( isset( $campaign_data['recipients'], $campaign_data['recipients']['list_id'] ) ) {
				$list_id = $campaign_data['recipients']['list_id'];
				$members = $mc->get( "/lists/$list_id/members", [ 'unique_email_id' => $mailchimp_subscriber_id ] )['members'];

				if ( ! empty( $members ) ) {
					$subscriber      = $members[0];
					$reader_events[] = [
						'type'    => 'subscription',
						'context' => 'mailchimp',
						'value'   => [
							'email' => $subscriber['email_address'],
						],
					];

					if ( isset( $subscriber['merge_fields'] ) ) {
						$donor_merge_field_option_name      = 'newspack_popups_mc_donor_merge_field';
						$donor_merge_fields                 = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
							$wpdb->prepare( "SELECT option_value FROM `$wpdb->options` WHERE option_name = %s LIMIT 1", $donor_merge_field_option_name )
						);
						$donor_merge_fields                 = isset( $donor_merge_fields->option_value ) ? explode( ',', $donor_merge_fields->option_value ) : [ 'DONAT' ];
						$has_donated_according_to_mailchimp = array_reduce(
							// Get all merge fields whose name contains one of the Donor Merge Field option strings.
							array_filter(
								array_keys( $subscriber['merge_fields'] ),
								function ( $merge_field ) use ( $donor_merge_fields ) {
									$matches = false;
									foreach ( $donor_merge_fields as $donor_merge_field ) {
										if ( strpos( $merge_field, trim( $donor_merge_field ) ) !== false ) {
											$matches = true;
										}
									}
									return $matches;
								}
							),
							// If any of these fields is "true", the subscriber has donated.
							function ( $result, $donation_merge_field_name ) use ( $subscriber ) {
								if ( 'true' === $subscriber['merge_fields'][ $donation_merge_field_name ] ) {
									$result = true;
								}
								return $result;
							},
							false
						);

						if ( $has_donated_according_to_mailchimp ) {
							$reader_events[] = [
								'type'    => 'donation',
								'context' => 'mailchimp',
								'value'   => [
									'email' => $subscriber['email_address'],
									'mailchimp_has_donated' => true,
								],
							];
						}
					}
				}
			}
		}

		if ( ! empty( $reader_events ) ) {
			$this->save_reader_events( $client_id, $reader_events );
		}
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
