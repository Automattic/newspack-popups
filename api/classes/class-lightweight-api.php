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
	 * @var rid
	 */
	public $rid;

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
		$this->rid             = ! empty( $_REQUEST['rid'] ) ? // phpcs:ignore
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
		$config = $_SERVER['DOCUMENT_ROOT'] . '/newspack-popups-config.php'; //phpcs:ignore
		if ( file_exists( $config ) ) {
			require $config;
			return [
				'db_host'     => DB_HOST,
				'db_name'     => DB_NAME,
				'db_user'     => DB_USER,
				'db_password' => DB_PASSWORD,
			];
		}
		return null;
	}

	/**
	 * Get transient name.
	 */
	public function transient_name() {
		return sprintf( '_transient_%s-%s-popup', $this->rid, $this->popup_id );
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
			$this->rid
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
}
