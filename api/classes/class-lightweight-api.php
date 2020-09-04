<?php
/**
 * Newspack Campaigns lightweight API
 *
 * @package Newspack
 */

/**
 * Require some useful utility functions from WordPress without needing to load all of WP.
 */
require_once 'utils/wp-functions.php';

/**
 * API endpoints
 */
class Lightweight_API {

	/**
	 * Database object.
	 *
	 * @var db
	 */
	public $db;

	/**
	 * Unique client ID.
	 *
	 * @var client_id
	 */
	public $client_id;

	/**
	 * Campaign ID.
	 *
	 * @var popup_id
	 */
	public $popup_id;

	/**
	 * Campaign frequency.
	 *
	 * @var frequency
	 */
	public $frequency;

	/**
	 * Campaign placement.
	 *
	 * @var placement
	 */
	public $placement;

	/**
	 * Campaign UTM Suppression.
	 *
	 * @var utm_suppression
	 */
	public $utm_suppression;

	/**
	 * Suppress Newsletter Campaigns setting.
	 *
	 * @var suppress_newsletter_campaigns
	 */
	public $suppress_newsletter_campaigns;

	/**
	 * Suppress all newsletter campaigns if one dismissed setting.
	 *
	 * @var suppress_all_newsletter_campaigns_if_one_dismissed
	 */
	public $suppress_all_newsletter_campaigns_if_one_dismissed;

	/**
	 * Campaign has Newsletter prompt.
	 *
	 * @var has_newsletter_prompt
	 */
	public $has_newsletter_prompt;

	/**
	 * Response object.
	 *
	 * @var response
	 */
	public $response = [];

	/**
	 * The referer URL.
	 *
	 * @var referer_url
	 */
	public $referer_url;

	/**
	 * Debugging information.
	 *
	 * @var debug
	 */
	public $debug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->debug = [
			'queries' => [],
		];
		if ( ! $this->verify_referer() ) {
			$this->error( 'invalid_referer' );
		}
		$this->db              = $this->connect();
		$this->client_id       = ! empty( $_REQUEST['rid'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'rid', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->popup_id        = ! empty( $_REQUEST['popup_id'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'popup_id', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->frequency       = ! empty( $_REQUEST['frequency'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'frequency', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->placement       = ! empty( $_REQUEST['placement'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'placement', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->utm_suppression = ! empty( $_REQUEST['utm_suppression'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'utm_suppression', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore

		$this->suppress_newsletter_campaigns                      = ! empty( $_REQUEST['suppress_newsletter_campaigns'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'suppress_newsletter_campaigns', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->suppress_all_newsletter_campaigns_if_one_dismissed = ! empty( $_REQUEST['suppress_all_newsletter_campaigns_if_one_dismissed'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'suppress_all_newsletter_campaigns_if_one_dismissed', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore
		$this->has_newsletter_prompt = ! empty( $_REQUEST['has_newsletter_prompt'] ) ? // phpcs:ignore
			filter_input( INPUT_POST | INPUT_GET, 'has_newsletter_prompt', FILTER_SANITIZE_SPECIAL_CHARS ) :
			null; // phpcs:ignore

		$this->referer_url = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );

		if ( 'inline' !== $this->placement && 'always' === $this->frequency ) {
			$this->frequency = 'once';
		}
	}

	/**
	 * Verify referer is valid.
	 */
	public function verify_referer() {
		$http_referer = ! empty( $_SERVER['HTTP_REFERER'] ) ? parse_url( $_SERVER['HTTP_REFERER'] , PHP_URL_HOST ) : null; // phpcs:ignore
		$valid_referers = [
			$http_referer,
			// TODO: Add AMP Cache.
		];
		$http_host = ! empty( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : null; // phpcs:ignore
		return in_array( strtolower( $http_host ), $valid_referers, true );
	}

	/**
	 * Establish DB connection.
	 */
	public function connect() {
		$credentials = $this->get_credentials();
		if ( ! $credentials ) {
			$this->error( 'no_credentials' );
		}
		$db = mysqli_init(); //phpcs:ignore
		$db->real_connect(
			$credentials['db_host'],
			$credentials['db_user'],
			$credentials['db_password'],
			$credentials['db_name']
		);
		return $db;
	}

	/**
	 * Get database credentials from environment variables (Atomic) or special config file.
	 */
	public function get_credentials() {
		if ( getenv( 'DB_NAME' ) && getenv( 'DB_USER' ) && getenv( 'DB_PASSWORD' ) ) {
			return [
				'db_host'     => 'localhost',
				'db_name'     => getenv( 'DB_NAME' ),
				'db_user'     => getenv( 'DB_USER' ),
				'db_password' => getenv( 'DB_PASSWORD' ),
			];
		}

		$config_path = $_SERVER['DOCUMENT_ROOT'] . '/wp-config.php'; //phpcs:ignore
		if ( file_exists( $config_path ) ) {
			$config_contents = file_get_contents( $config_path ); // phpcs:ignore
			$db_host         = $this->get_defined_constant_value_from_php_source( $config_contents, 'DB_HOST' );
			$db_name         = $this->get_defined_constant_value_from_php_source( $config_contents, 'DB_NAME' );
			$db_user         = $this->get_defined_constant_value_from_php_source( $config_contents, 'DB_USER' );
			$db_password     = $this->get_defined_constant_value_from_php_source( $config_contents, 'DB_PASSWORD' );
			if ( $db_host && $db_name && $db_user && $db_password ) {
				return [
					'db_host'     => $db_host,
					'db_name'     => $db_name,
					'db_user'     => $db_user,
					'db_password' => $db_password,
				];
			}
		}
		return null;
	}

	/**
	 * Get transient name.
	 */
	public function get_transient_name() {
		return sprintf( '_transient_%s-%s-popup', $this->client_id, $this->popup_id );
	}

	/**
	 * Get suppression-related transient value
	 *
	 * @param string $prefix transient prefix.
	 */
	public function get_suppression_data_transient_name( $prefix ) {
		return sprintf(
			'_transient_%s-%s',
			$prefix,
			$this->client_id
		);
	}

	/**
	 * Get data from transient.
	 *
	 * @param string $transient_name The transient's name.
	 */
	public function get_transient( $transient_name ) {
		$query                    = sprintf(
			"SELECT option_value FROM wp_options WHERE option_name = '%s'",
			$this->db->real_escape_string( $transient_name )
		);
		$this->debug['queries'][] = $query;
		$result                   = $this->db->query( $query );
		$row                      = $result->fetch_assoc();
		$transient                = maybe_unserialize( $row['option_value'] );
		return $transient;
	}

	/**
	 * Insert or update a transient.
	 *
	 * @param string $transient_name THe transient's name.
	 * @param string $transient THe transient's value.
	 */
	public function set_transient( $transient_name, $transient ) {
		$exists                   = sprintf( "SELECT option_id FROM wp_options WHERE option_name = '%s'", $transient_name );
		$this->debug['queries'][] = $exists;
		$result                   = $this->db->query( $exists );
		$row                      = $result->fetch_assoc();
		if ( $row ) {
			$update                 = sprintf(
				"UPDATE wp_options SET option_value = '%s' WHERE option_name = '%s'",
				$this->db->real_escape_string( $transient ),
				$this->db->real_escape_string( $transient_name )
			);
			$this->debug->queries[] = $update;
			$this->db->query( $update );
		} else {
			$insert                 = sprintf(
				"INSERT INTO wp_options ( option_name, option_value ) VALUES ( '%s', '%s' )",
				$this->db->real_escape_string( $transient_name ),
				$this->db->real_escape_string( $transient )
			);
			$this->debug->queries[] = $insert;
			$this->db->query( $insert );
		}
	}

	/**
	 * Teardown.
	 */
	public function disconnect() {
		if ( $this->db ) {
			$this->db->close();
		}
	}

	/**
	 * Complete the API and print response.
	 */
	public function respond() {
		$this->debug['query_count'] = count( $this->debug['queries'] );
		if ( defined( 'WP_DEBUG_NEWSPACK_CAMPAIGNS' ) && WP_DEBUG_NEWSPACK_CAMPAIGNS ) {
			$this->response['debug'] = $this->debug;
		}
		$this->disconnect();
		http_response_code( 200 );
		print json_encode( $this->response ); // phpcs:ignore
		exit;
	}

	/**
	 * Return a 500 code error.
	 *
	 * @param string $code The error code.
	 */
	public function error( $code ) {
		$this->disconnect();
		http_response_code( 500 );
		print json_encode( [ 'error' => $code ] ); // phpcs:ignore
		exit;
	}

	/**
	 * Extract DB connection constants from wp-config without creating a WordPress session.
	 *
	 * @param string $config_source The full source of the wp-config file.
	 * @param string $constant_name The name of the constant to extract.
	 */
	public function get_defined_constant_value_from_php_source( $config_source, $constant_name ) {

		// Remove all lines which do not contain the 'define' keyword.
		$config_lines = explode( "\n", $config_source );
		foreach ( $config_lines as $no => $line ) {
			if ( 0 !== strpos( trim( $line ), 'define' ) ) {
				unset( $config_lines[ $no ] );
			}
		}
		$config_lines = array_values( $config_lines );
		if ( empty( $config_lines ) ) {
			return null;
		}

		// Parse the `define( 'NAME', 'value' );` lines.
		foreach ( $config_lines as $no => $line ) {
			// Remove `define`.
			$line = trim( str_replace( 'define', '', trim( $line ) ) );

			// Remove `;` from the end.
			if ( ';' !== $line[ strlen( $line ) - 1 ] ) {
				continue;
			}
			$line = substr( $line, 0, -1 );

			// Remove opening and closing brackets.
			$line = trim( trim( $line, '()' ) );

			// Explode comma separated params.
			$define_params = explode( ',', $line );

			// Remove first and last char from the constant name - these are either a single or a double quote.
			$define_params[0] = substr( trim( $define_params[0] ), 1 );
			$define_params[0] = substr( $define_params[0], 0, -1 );
			$define_params[0] = stripcslashes( $define_params[0] );

			// If this isn't the constant, continue to next line.
			if ( $constant_name != $define_params[0] ) {
				continue;
			}

			// Remove first and last char from the constant name - these are either a single or a double quote.
			$define_params[1] = substr( trim( $define_params[1] ), 1 );
			$define_params[1] = substr( $define_params[1], 0, -1 );
			$define_params[1] = stripcslashes( $define_params[1] );

			return $define_params[1];
		}

		return null;
	}
}
