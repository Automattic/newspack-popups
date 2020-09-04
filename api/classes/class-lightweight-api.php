<?php
/**
 * Newspack Campaigns lightweight API
 *
 * @package Newspack
 */

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
	 * Array of queries, for debugging.
	 *
	 * @var queries
	 */
	public $queries = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
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
		return $prefix . '-' . $this->rid;
	}

	/**
	 * Get data from transient.
	 *
	 * @param string $transient_name The transient's name.
	 */
	public function get_transient( $transient_name ) {
		$query           = sprintf( "SELECT option_value FROM wp_options WHERE option_name = '%s'", $transient_name );
		$this->queries[] = $query;
		$result          = $this->db->query( $query );
		$row             = $result->fetch_assoc();
		$transient       = $this->maybe_unserialize( $row['option_value'] );
		return $transient;
	}

	/**
	 * Insert or update a transient.
	 *
	 * @param string $transient_name THe transient's name.
	 * @param string $transient THe transient's value.
	 */
	public function set_transient( $transient_name, $transient ) {
		$exists          = sprintf( "SELECT option_id FROM wp_options WHERE option_name = '%s'", $transient_name );
		$this->queries[] = $exists;
		$result          = $this->db->query( $exists );
		$row             = $result->fetch_assoc();
		if ( $row ) {
			$update          = sprintf( "UPDATE wp_options SET option_value = '%s' WHERE option_name = '%s'", $transient, $transient_name );
			$this->queries[] = $update;
			$this->db->query( $update );
		} else {
			$insert          = sprintf( "INSERT INTO wp_options ( option_name, option_value ) VALUES ( '%s', '%s' )", $transient_name, $transient );
			$this->queries[] = $insert;
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
		$this->response['queries'] = $this->queries;
		$this->disconnect();
		http_response_code( 200 );
		print json_encode( $this->response ); // phpcs:ignore
		exit;
	}

	/**
	 * Check value to find if it was serialized.
	 *
	 * If $data is not an string, then returned value will always be false.
	 * Serialized data is always a string.
	 *
	 * @param string $data   Value to check to see if was serialized.
	 * @param bool   $strict Optional. Whether to be strict about the end of the string. Default true.
	 * @return bool False if not serialized and true if it was.
	 */
	public function is_serialized( $data, $strict = true ) {
		// If it isn't a string, it isn't serialized.
		if ( ! is_string( $data ) ) {
			return false;
		}
		$data = trim( $data );
		if ( 'N;' === $data ) {
			return true;
		}
		if ( strlen( $data ) < 4 ) {
			return false;
		}
		if ( ':' !== $data[1] ) {
			return false;
		}
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) {
				return false;
			}
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			// Either ; or } must exist.
			if ( false === $semicolon && false === $brace ) {
				return false;
			}
			// But neither must be in the first X characters.
			if ( false !== $semicolon && $semicolon < 3 ) {
				return false;
			}
			if ( false !== $brace && $brace < 4 ) {
				return false;
			}
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) {
						return false;
					}
				} elseif ( false === strpos( $data, '"' ) ) {
					return false;
				}
				// Or else fall through.
			case 'a':
			case 'O':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;$end/", $data );
		}
		return false;
	}

	/**
	 * Unserialize data only if it was serialized.
	 *
	 * @param string $data Data that might be unserialized.
	 * @return mixed Unserialized data can be any type.
	 */
	public function maybe_unserialize( $data ) {
		if ( $this->is_serialized( $data ) ) { // Don't attempt to unserialize data that wasn't serialized going in.
			return @unserialize( trim( $data ) ); // phpcs:ignore
		}

		return $data;
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
