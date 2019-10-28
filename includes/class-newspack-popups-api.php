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
			array(
				'methods'  => \WP_REST_Server::READABLE,
				'callback' => [ $this, 'reader_get_endpoint' ],
			)
		);
		\register_rest_route(
			'newspack-popups/v1/',
			'reader',
			array(
				'methods'  => \WP_REST_Server::CREATABLE,
				'callback' => [ $this, 'reader_post_endpoint' ],
			)
		);
	}

	/**
	 * Handle GET requests to the reader endpoint.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return WP_REST_Response with info about reader.
	 */
	public function reader_get_endpoint( $request ) {
		$reader   = isset( $request['rid'] ) ? $request['rid'] : false;
		$response = array(
			'currentViews' => 0,
			'displayPopup' => true,
		);
		if ( ! $reader ) {
			return rest_ensure_response( $response );
		}
		$response['currentViews'] = (int) get_transient( $reader . '-currentViews' );
		$response['displayPopup'] = $response['currentViews'] < self::NEWSPACK_POPUPS_VIEW_LIMIT;
		return rest_ensure_response( $response );
	}

	/**
	 * Handle POST requests to the reader endpoint.
	 *
	 * @param WP_REST_Request $request amp-access request.
	 * @return WP_REST_Response with updated info about reader.
	 */
	public function reader_post_endpoint( $request ) {
		$reader = isset( $request['rid'] ) ? sanitize_title( $request['rid'] ) : false;
		$url    = isset( $request['url'] ) ? esc_url_raw( $request['url'] ) : false;
		if ( $reader && $url ) {
			$post_id = url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			if ( $post_id && 'post' === get_post_type( $post_id ) ) {
				$current_views = (int) get_transient( $reader . '-currentViews' );
				set_transient( $reader . '-currentViews', $current_views + 1, WEEK_IN_SECONDS );
			}
		}
		return $this->reader_get_endpoint( $request );
	}
}
$newspack_popups_api = new Newspack_Popups_API();
