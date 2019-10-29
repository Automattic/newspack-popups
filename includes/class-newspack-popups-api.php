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
			'newspack-popups/v1/',
			'reader',
			[
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'reader_get_endpoint' ],
			]
		);
		\register_rest_route(
			'newspack-popups/v1/',
			'reader',
			[
				'methods'  => \WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'reader_post_endpoint' ],
			]
		);
	}

	/**
	 * Handle GET requests to the reader endpoint.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return WP_REST_Response with info about reader.
	 */
	public function reader_get_endpoint( $request ) {
		$popup_id = isset( $request['popup_id'] ) ? $request['popup_id'] : false;
		$popup    = Newspack_Popups_Inserter::retrieve_popup( $popup_id );
		$response = [
			'currentViews' => 0,
			'displayPopup' => false,
		];

		$transient_name = $this->get_transient_name( $request );
		if ( ! $transient_name ) {
			return rest_ensure_response( $response );
		}
		$data = get_transient( $transient_name );

		$response['currentViews'] = (int) $data['count'];

		$frequency = intval( $popup['options']['frequency'] );
		if ( 0 === $frequency && $response['currentViews'] < 1 ) {
			$response['displayPopup'] = true;
		} elseif ( 0 === $response['currentViews'] % $frequency ) {
			$response['displayPopup'] = true;
		}
		return rest_ensure_response( $response );
	}

	/**
	 * Handle POST requests to the reader endpoint.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return WP_REST_Response with updated info about reader.
	 */
	public function reader_post_endpoint( $request ) {
		$transient_name = $this->get_transient_name( $request );
		if ( $transient_name ) {
			$data          = get_transient( $transient_name );
			$data['count'] = (int) $data['count'] + 1;
			$data['time']  = time();
			set_transient( $transient_name, $data, 0 );
		}
		return $this->reader_get_endpoint( $request );
	}

	/**
	 * Get transient name.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return string Transient id.
	 */
	public function get_transient_name( $request ) {
		$reader   = isset( $request['rid'] ) ? sanitize_title( $request['rid'] ) : false;
		$popup_id = isset( $request['popup_id'] ) ? $request['popup_id'] : false;
		$url      = isset( $request['url'] ) ? esc_url_raw( $request['url'] ) : false;
		if ( $reader && $url && $popup_id ) {
			$post_id = url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			if ( $post_id && 'post' === get_post_type( $post_id ) ) {
				return $reader . '-' . $popup_id . '-popup';
			}
		}
		return false;
	}
}
$newspack_popups_api = new Newspack_Popups_API();
