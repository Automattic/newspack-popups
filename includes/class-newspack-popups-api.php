<?php
/**
 * Newspack Popups API
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * API endpoints
 */
final class Newspack_Popups_API {

	const NEWSPACK_POPUPS_VIEW_LIMIT = 1;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
	}

	/**
	 * Register the 'reader' endpoint used by amp-access.
	 */
	public function register_api_endpoints() {
		\register_rest_route(
			'newspack-popups/v1',
			'reader',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reader_post_endpoint' ],
				'permission_callback' => '__return_true',
			]
		);
		\register_rest_route(
			'newspack-popups/v1',
			'sitewide_default/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'set_sitewide_default_endpoint' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		\register_rest_route(
			'newspack-popups/v1',
			'sitewide_default/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'unset_sitewide_default_endpoint' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
		\register_rest_route(
			'newspack-popups/v1',
			'settings',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'option_name'  => [
						'validate_callback' => [ __CLASS__, 'validate_settings_option_name' ],
						'sanitize_callback' => 'esc_attr',
					],
					'option_value' => [
						'sanitize_callback' => 'esc_attr',
					],
				],
			]
		);
	}

	/**
	 * Validate settings option key.
	 *
	 * @param String $key Meta key.
	 */
	public static function validate_settings_option_name( $key ) {
		return in_array( $key, array_keys( \Newspack_Popups_Settings::get_settings() ) );
	}

	/**
	 * Permission callback for authenticated requests.
	 *
	 * @return boolean if user can edit stuff.
	 */
	public static function permission_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'newspack_rest_forbidden',
				esc_html__( 'You cannot use this resource.', 'newspack' ),
				[
					'status' => 403,
				]
			);
		}
		return true;
	}

	/**
	 * Handler for API settings update endpoint.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function update_settings( $request ) {
		if ( update_option( $request['option_name'], $request['option_value'] ) ) {
			return \Newspack_Popups_Settings::get_settings();
		} else {
			return new \WP_Error(
				'newspack_popups_settings_error',
				esc_html__( 'Error updating the settings.', 'newspack' )
			);
		}
	}

	/**
	 * Handle POST requests to the reader endpoint.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return WP_REST_Response with updated info about reader.
	 */
	public function reader_post_endpoint( $request ) {
		$transient_name = $this->get_popup_data_transient_name( $request );
		if ( $transient_name ) {
			$data                = get_transient( $transient_name );
			$data['count']       = (int) $data['count'] + 1;
			$data['last_viewed'] = time();
			if ( $request['suppress_forever'] ) {
				$popup_id = self::get_request_param( 'popup_id', $request );
				if ( $popup_id && self::get_request_param( 'is_newsletter_popup', $request ) ) {
					$client_data_transient_name = $this->get_client_data_transient_name( $request );
					$client_data                = get_transient( $client_data_transient_name );
					if ( ! $client_data ) {
						$client_data = [];
					}
					$client_data['suppressed_newsletter_campaign'] = true;
					$this->update_cache( $client_data_transient_name, $client_data );
					set_transient( $client_data_transient_name, $client_data );
				}

				$data['suppress_forever'] = true;
			}
			if ( 'subscribed' === $this->get_request_param( 'mailing_list_status', $request ) ) {
				$data['suppress_forever'] = true;
			}
			$email_address = $this->get_request_param( 'email', $request );
			if ( $email_address ) {
				do_action(
					'newspack_popups_mailing_list_subscription',
					[
						'email' => $email_address,
					]
				);
			}
			$this->update_cache( $transient_name, $data );
		}
	}

	/**
	 * Update the cache after setting a transient.
	 *
	 * @param string $transient_name The transient name.
	 * @param mixed  $data The transient data.
	 */
	public function update_cache( $transient_name, $data ) {
		$full_name = '_transient_' . $transient_name;
		wp_cache_set( $full_name, maybe_serialize( $data ), 'newspack-popups' );
		set_transient( $transient_name, $data, 0 );
	}

	/**
	 * Set sitewide default Popup
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function set_sitewide_default_endpoint( $request ) {
		$response = Newspack_Popups_Model::set_sitewide_popup( $request['id'] );
		return is_wp_error( $response ) ? $response : [ 'success' => true ];
	}

	/**
	 * Unset sitewide default Popup (if it is the specified post)
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public function unset_sitewide_default_endpoint( $request ) {
		$response = Newspack_Popups_Model::unset_sitewide_popup( $request['id'] );
		return is_wp_error( $response ) ? $response : [ 'success' => true ];
	}

	/**
	 * Get transient name.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return string Transient id.
	 */
	public function get_popup_data_transient_name( $request ) {
		$client_id = $this->get_request_param( 'cid', $request );
		$popup_id  = $this->get_request_param( 'popup_id', $request );
		if ( $client_id && $popup_id ) {
			return $client_id . '-' . $popup_id . '-popup';
		}
		return false;
	}

	/**
	 * Get transient name for client data, not related to a specifi campaign.
	 *
	 * @param WP_REST_Request $request A request.
	 * @return string Transient id.
	 */
	public function get_client_data_transient_name( $request ) {
		$client_id = $this->get_request_param( 'cid', $request );
		if ( $client_id ) {
			return $client_id . '-popups';
		}
		return false;
	}

	/**
	 * Get request param.
	 *
	 * @param WP_REST_Request $request A request.
	 */
	public function get_request_param( $param, $request ) {
		$value = isset( $request[ $param ] ) ? esc_attr( $request[ $param ] ) : false;
		if ( ! $value ) {
			$body  = json_decode( $request->get_body(), true );
			$value = isset( $body[ $param ] ) ? $body[ $param ] : false;
		}
		return $value;
	}
}
$newspack_popups_api = new Newspack_Popups_API();
