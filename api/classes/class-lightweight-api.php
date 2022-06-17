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
	 * Default reader.
	 *
	 * @var reader_blueprint
	 */
	public $reader_blueprint = [
		'date_created'  => null,
		'date_modified' => null,
		'is_preview'    => false,
	];

	/**
	 * Default reader data item.
	 *
	 * @var reader_data_blueprint
	 */
	public $reader_data_blueprint = [
		'id'           => null,
		'date_created' => null,
		'type'         => null,
		'context'      => null,
		'value'        => null,
	];

	/**
	 * Reader data can be temporary (purged periodically) or persistent depending on the type.
	 * The following `type` values are considered persistent data.
	 *
	 * @var reader_data_persistent_types
	 */
	public $reader_data_persistent_types = [
		'view_count',
		'term_count',
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
			'reader_data'        => [],
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
	 * TODO: Rewrite this to handle a single arg (a simple array of data items) and to both insert and update.
	 *
	 * @param array $data Array of data items to save.
	 *
	 * @return boolean Result of the write query: true if successful, otherwise false.
	 */
	public function bulk_db_insert( $data ) {
		$write_result = false;

		if ( 0 === count( $data ) ) {
			return $write_result;
		}

		$data_to_create = [];
		$data_to_update = [];

		// If the item has an `id` value, it will be used to update an existing row (if exists).
		foreach ( $data as $item ) {
			if ( isset( $item['id'] ) ) {
				$data_to_update[] = $item;
			} else {
				$data_to_create[] = $item;
			}
		}
		$this->debug['create'] = $data_to_create;
		$this->debug['update'] = $data_to_update;

		global $wpdb;
		$reader_data_table_name = Segmentation::get_reader_data_table_name();
		$query                  = $this->get_sql( $reader_data_table_name, $data );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $write_result ) {
			$this->debug['write_error'] = "Error writing to $reader_data_table_name.";
		}

		return $write_result;
	}

	/**
	 * Get data for a specific reader.
	 *
	 * @param string $client_id Client ID.
	 *
	 * @return array Reader data.
	 */
	public function get_reader( $client_id ) {
		$reader              = $this->reader_blueprint;
		$reader['client_id'] = $client_id;

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$cached_reader_data = wp_cache_get( 'reader', $client_id );
			if ( ! empty( $cached_reader_data ) ) {
				return $cached_reader_data;
			}
		}

		// If ignoring cache or there's no cached reader data, retrieve from the DB.
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
			$this->save_reader( $client_id );

			// Check the legacy transients table for existing reader data.
			$legacy_reader_data = $this->get_transient_legacy( $client_id );

			// If there's data for this reader in the legacy transients table, recreate it in the new table.
			if ( ! empty( $legacy_reader_data ) ) {
				$this->debug['legacy_data'] = $legacy_reader_data;
				$reader_data                = [];

				// Add posts_read data to views count.
				if ( isset( $legacy_reader_data['posts_read'] ) && 0 < count( $legacy_reader_data ) ) {
					$reader_data[] = [
						'type'    => 'view_count',
						'context' => 'post',
						'value'   => [ 'count' => count( $legacy_reader_data['posts_read'] ) ],
					];

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

					if ( ! empty( $categories_read ) ) {
						$reader_data[] = [
							'type'    => 'term_count',
							'context' => 'category',
							'value'   => $categories_read,
						];
					}
				}

				// Add known user accounts.
				if ( ! empty( $legacy_reader_data['user_id'] ) ) {
					$reader_data[] = [
						'type'    => 'user_id',
						'context' => 'wp',
						'value'   => [ 'user_id' => $legacy_reader_data['user_id'] ],
					];
				}

				// Add prior donations.
				if ( ! empty( $legacy_reader_data['donations'] ) ) {
					foreach ( $legacy_reader_data['donations'] as $donation ) {
						$donation_date = isset( $donation['date'] ) ? strtotime( $donation['date'] ) : time();
						$donation_data = [
							'type'  => 'donation',
							'value' => $donation,
						];

						if ( isset( $donation['order_id'] ) ) {
							$donation_data['context'] = 'woocommerce';
						}
						if ( isset( $donation['stripe_id'] ) ) {
							$donation_data['context'] = 'stripe';
						}

						$reader_data[] = $donation_data;
					}
				}

				// Add prior newsletter subscriptions.
				if ( ! empty( $legacy_reader_data['email_subscriptions'] ) ) {
					foreach ( $legacy_reader_data['email_subscriptions'] as $subscription ) {
						$reader_data[] = [
							'type'  => 'subscription',
							'value' => [
								'email' => $subscription['email'] ?? $subscription['address'],
							],
						];
					}
				}

				if ( ! empty( $reader_data ) ) {
					$this->save_reader_data( $reader_data );
				}

				// If we were able to save the legacy data, clean up old transients data.
				$this->delete_transient_legacy( $client_id );
			}
		} else {
			// Rebuild the cache.
			$reader_from_db = reset( $reader_from_db );
			$reader_from_db = (array) $reader_from_db;
			$reader         = wp_parse_args( $reader_from_db, $reader );
		}

		// Rebuild cache.
		wp_cache_set( 'reader', $client_id, $client_id );

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
		$columns[]              = 'date_modified';

		foreach ( $data as $item ) {
			$item['date_modified'] = gmdate( 'Y-m-d H:i:s' );

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
	 *
	 * @return boolean True if updated., false if not.
	 */
	public function save_reader( $client_id ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) ) {
			return false;
		}

		global $wpdb;
		$reader             = [ 'client_id' => $client_id ];
		$readers_table_name = Segmentation::get_readers_table_name();

		if ( $this->is_preview( $client_id ) ) {
			$reader['is_preview'] = true;
		}

		$query = $this->get_sql( $readers_table_name, [ $reader ] );

		// Write to the DB.
		$write_result = $wpdb->query( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// If DB write was successful, rebuild cache.
		if ( $write_result ) {
			$this->debug['write_query_count'] += 1;
			return wp_cache_set( 'reader', $client_id, $client_id );
		}

		$this->debug['write_error'] = "Error writing to $readers_table_name.";
		return false;
	}

	/**
	 * Given an array of reader data items, only return those with matching $types and/or $contexts.
	 * If both $types and $contexts are null, simply return the array as-is.
	 *
	 * @param array             $items Array of reader data items.
	 * @param string|array|null $types Data type or array of data types to filter by.
	 *                                 If not given, will only retrieve temporary data types.
	 * @param string|array|null $contexts Data context or array of data contexts to filter by.
	 *
	 * @return array Filtered array of data items.
	 */
	public function filter_data_by_type( $items, $types = null, $contexts = null ) {
		if ( null === $types && null === $contexts ) {
			return $items;
		}

		if ( ! is_array( $types ) && null !== $types ) {
			$types = [ $types ];
		}
		if ( ! is_array( $contexts ) && null !== $contexts ) {
			$contexts = [ $contexts ];
		}

		$persistent_types = $this->reader_data_persistent_types;

		return array_values(
			array_filter(
				$items,
				function( $item ) use ( $types, $contexts, $persistent_types ) {
					$matches = true;
					if ( null === $types ) {
						$matches = ! in_array( $item['type'], $persistent_types, true );
					} else {
						$matches = in_array( $item['type'], $types, true );
					}

					if ( null !== $contexts ) {
						$matches = $matches && in_array( $item['context'], $contexts, true );
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
	public function get_reader_data_from_db( $client_id ) {
		global $wpdb;
		$reader_data_table_name = Segmentation::get_reader_data_table_name();
		$data                   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT * from $reader_data_table_name WHERE client_id = %s ORDER BY date_modified DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$client_id
			)
		);

		$this->debug['read_query_count'] += 1;

		if ( ! empty( $data ) ) {
			$data = array_map(
				function( $item ) {
					$item          = (array) $item;
					$item['value'] = (array) json_decode( $item['value'] );
					return $item;
				},
				$data
			);
		}

		return $data;
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
	public function get_reader_data( $client_id, $type = null, $context = null ) {
		$data = [];

		// Check the cache first.
		if ( ! $this->ignore_cache ) {
			$data = wp_cache_get( 'reader_data', $client_id );
			if ( ! empty( $data ) ) {
				return $this->filter_data_by_type( $data, $type, $context );
			}
		}

		// If no data in the cache, get it from the DB.
		$data = $this->get_reader_data_from_db( $client_id );

		// Rebuild cache.
		if ( ! empty( $data ) ) {
			wp_cache_set( 'reader_data', $data, $client_id );
			$this->debug['reader_data'] = $data;
		}

		return $this->filter_data_by_type( $data, $type, $context );
	}

	/**
	 * Retrieve only persistent data for a specific reader.
	 * Persistent data types are defined in the $get_reader_data_persistent class variable.
	 *
	 * @param string $client_id Client ID of the reader.
	 */
	public function get_reader_data_persistent( $client_id ) {
		$persistent_data = $this->get_reader_data( $client_id, $this->reader_data_persistent_types );

		$this->debug['reader_data_persistent'] = $persistent_data;

		return $persistent_data;
	}

	/**
	 * Save reader data.
	 *
	 * @param string $client_id Client ID of the reader.
	 * @param array  $data Array of reader data rows to log.
	 *                     ['id'] Unique ID of persistent data row to update.
	 *                     ['date_created'] Timestamp of the data insertion.
	 *                     ['date_modified'] Timestamp of the update.
	 *                     ['type'] Type of data. Required.
	 *                     ['context'] Context of data.
	 *                     ['value'] Value of the data to save. Required.
	 *
	 * @return boolean True if saved, false if not.
	 */
	public function save_reader_data( $client_id, $data ) {
		if ( empty( $client_id ) || ! is_string( $client_id ) || empty( $data ) ) {
			return false;
		}

		// Deduplicate views from the past hour.
		$existing_data = $this->get_reader_data( $client_id );
		$already_read  = $this->filter_data_by_type( $existing_data, 'view' );
		$already_read  = array_column(
			array_column(
				$already_read,
				'value'
			),
			'post_id'
		);

		$data = array_values(
			array_filter(
				$data,
				function( $item ) use ( $already_read ) {
					if (
						isset( $item['type'] ) &&
						'view' === $item['type'] &&
						isset( $item['value']['post_id'] )
					) {
						return ! in_array( $item['value']['post_id'], $already_read, true );
					}

					return ! empty( $item['type'] );
				}
			)
		);

		// If no new items to save, return false.
		if ( empty( $data ) ) {
			$this->debug['already_read'] = true;
			return false;
		}

		// Create or update view_count and term_count rows.
		$new_views = $this->filter_data_by_type( $data, 'view' );
		if ( ! empty( $new_views ) ) {
			$view_count_updates = [];
			$term_count_updates = [];

			foreach ( $new_views as $read_event ) {
				$post_type              = $read_event['context'];
				$view_count             = 0;
				$existing_view_count    = $this->get_reader_data( $client_id, 'view_count', $post_type );
				$existing_view_count_id = null;

				if ( ! empty( $existing_view_count ) ) {
					$existing_view_count = reset( $existing_view_count );

					if ( isset( $existing_view_count['value']['count'] ) ) {
						$view_count += (int) $existing_view_count['value']['count'];
					}
					if ( isset( $existing_view_count['id'] ) ) {
						$existing_view_count_id = $existing_view_count['id'];
					}
				}

				// Increment the view count.
				$view_count ++;

				if ( ! isset( $view_count_updates[ $post_type ] ) ) {
					$view_count_updates[ $post_type ] = [
						'type'    => 'view_count',
						'context' => $post_type,
						'value'   => [ 'count' => 0 ],
					];
				}

				$view_count_updates[ $post_type ]['value']['count'] += $view_count;
				if ( ! empty( $existing_view_count_id ) ) {
					$view_count_updates[ $post_type ]['id'] = $existing_view_count_id;
				}

				// Update term views. Eventually this could be extended to other taxonomies, but currently we only handle categories.
				if ( isset( $read_event['value']['categories'] ) ) {
					$taxonomy               = 'category';
					$term_count             = isset( $term_count_updates[ $taxonomy ] ) ? $term_count_updates[ $taxonomy ] : [];
					$existing_term_count    = $this->get_reader_data( $client_id, 'term_count', $taxonomy );
					$existing_term_count_id = null;

					if ( ! empty( $existing_term_count ) ) {
						$existing_term_count = reset( $existing_term_count );

						if ( isset( $existing_term_count['id'] ) ) {
							$existing_term_count_id = $existing_term_count['id'];
						}

						foreach ( $existing_term_count['value'] as $term_id => $count ) {
							if ( ! isset( $term_count[ $term_id ] ) ) {
								$term_count[ $term_id ] = (int) $count;
							} else {
								$term_count[ $term_id ] += (int) $count;
							}
						}
					}

					// Increment taxonomy term counts.
					foreach ( explode( ',', $read_event['value']['categories'] ) as $term_id ) {
						if ( ! isset( $term_count[ $term_id ] ) ) {
							$term_count[ $term_id ] = 0;
						}

						$term_count[ $term_id ] ++;
					}

					if ( ! isset( $term_count_updates[ $taxonomy ] ) ) {
						$term_count_updates[ $taxonomy ] = [
							'type'    => 'term_count',
							'context' => $taxonomy,
						];
					}

					if ( ! empty( $existing_term_count_id ) ) {
						$term_count_updates[ $taxonomy ]['id'] = $existing_term_count_id;
					}

					$term_count_updates[ $taxonomy ]['value'] = $term_count;
				}
			}

			// Add update objects to data to be saved.
			if ( $view_count_updates ) {
				$data = array_merge( $data, array_values( $view_count_updates ) );
			}
			if ( $term_count_updates ) {
				$data = array_merge( $data, array_values( $term_count_updates ) );
			}
		}

		// Ensure client ID exists and keys match across all items.
		$reader_data_blueprint = $this->reader_data_blueprint;
		$data                  = array_map(
			function( $item ) use ( $client_id, $reader_data_blueprint ) {
				$item              = wp_parse_args( $item, $reader_data_blueprint );
				$item['client_id'] = $client_id;

				if ( empty( $item['date_created'] ) ) {
					$item['date_created'] = gmdate( 'Y-m-d H:i:s' );
				}

				if ( isset( $item['value'] ) ) {
					$item['value'] = wp_json_encode( $item['value'] );
				}

				return wp_parse_args( $item, $this->reader_data_blueprint );
			},
			$data
		);

		// Rebuild cache.
		$all_data                   = array_merge( $existing_data, $data );
		$this->debug['reader_data'] = $data;

		// If ignoring cache, write directly to DB.
		if ( $this->ignore_cache ) {
			$write_result = $this->bulk_db_insert( $data );

			// If DB write was successful, rebuild cache.
			if ( $write_result ) {
				$this->debug['write_query_count'] += 1;
			} else {
				return false;
			}
		} else {
			// Write items to the flat file to be parsed to the DB at a later point.
			Segmentation_Report::log_reader_events( $data );
		}

		return wp_cache_set(
			'reader_data',
			$all_data,
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
