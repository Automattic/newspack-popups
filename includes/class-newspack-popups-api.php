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
		\register_rest_route(
			'newspack-popups/v1',
			'analytics-config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_custom_analytics_configuration' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Get custom Analytics config, with segmentation-related custom dimensions assigned.
	 * The pageviews will be reported using this configuration, so it's important
	 * to include the custom dimensions set up by the Newspack Plugin, too.
	 *
	 * @param WP_REST_Request $request Request object.
	 */
	public static function get_custom_analytics_configuration( $request ) {
		$client_id   = $request['client_id'];
		$ga_settings = get_option( 'googlesitekit_analytics_settings' );
		if ( ! $client_id || ! $ga_settings || ! isset( $ga_settings['propertyID'] ) ) {
			return [];
		}

		$custom_dimensions = [];
		if ( class_exists( 'Newspack\Analytics_Wizard' ) ) {
			$custom_dimensions = Newspack\Analytics_Wizard::list_configured_custom_dimensions();
		}

		// Tracking ID from Site Kit.
		$gtag_id = $ga_settings['propertyID'];

		$custom_dimensions_values = [];

		require_once dirname( __FILE__ ) . '/../api/classes/class-lightweight-api.php';
		require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';
		$api         = new Lightweight_API();
		$client_data = $api->get_client_data( $client_id );

		foreach ( $custom_dimensions as $custom_dimension ) {
			// Strip the `ga:` prefix from gaID.
			$dimension_id = substr( $custom_dimension['gaID'], 3 );
			switch ( $custom_dimension['role'] ) {
				case Newspack_Popups_Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY:
					$read_count = count( $client_data['posts_read'] );
					// Tiers mimick NCI's – https://news-consumer-insights.appspot.com.
					$read_count_tier = 'casual';
					if ( $read_count > 1 && $read_count <= 14 ) {
						$read_count_tier = 'loyal';
					} elseif ( $read_count > 14 ) {
						$read_count_tier = 'brand_lover';
					}
					$custom_dimensions_values[ $dimension_id ] = $read_count_tier;
					break;
				case Newspack_Popups_Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER:
					$custom_dimensions_values[ $dimension_id ] = Campaign_Data_Utils::is_subscriber( $client_data, wp_get_referer() );
					break;
				case Newspack_Popups_Segmentation::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR:
					$custom_dimensions_values[ $dimension_id ] = Campaign_Data_Utils::is_donor( $client_data );
					break;
			}
		}

		$custom_dimensions_existing_values = [];
		if ( class_exists( 'Newspack\Analytics' ) ) {
			$custom_dimensions_existing_values = Newspack\Analytics::get_custom_dimensions_values( $request['post_id'] );
		}

		// This is an AMP Analytics-compliant configuration, which on non-AMP pages will be
		// processed by this plugin's amp-analytics polyfill (src/view).
		return [
			'vars'            => [
				'gtag_id' => $gtag_id,
				'config'  => [
					$gtag_id => array_merge(
						[
							'groups' => 'default',
						],
						$custom_dimensions_values,
						$custom_dimensions_existing_values
					),
				],
			],
			'optoutElementId' => '__gaOptOutExtension',
		];
	}

	/**
	 * Validate settings option key.
	 *
	 * @param String $key Meta key.
	 */
	public static function validate_settings_option_name( $key ) {
		return in_array(
			$key,
			array_map(
				function ( $setting ) {
					return $setting['key'];
				},
				\Newspack_Popups_Settings::get_settings()
			)
		);
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
		return \Newspack_Popups_Settings::set_settings(
			[
				'option_name'  => $request['option_name'],
				'option_value' => $request['option_value'],
			]
		);
	}

	/**
	 * Set sitewide default.
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
}
$newspack_popups_api = new Newspack_Popups_API();
