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

		$frequency             = $popup['options']['frequency'];
		$current_views         = (int) $response['currentViews'];
		$last_view             = (int) $data['time'];
		$subscription_status   = $data['subscription_status'];
		$response['frequency'] = $frequency;
		switch ( $frequency ) {
			case 'daily':
				$response['displayPopup'] = $last_view < strtotime( '-1 day' );
				break;
			case 'once':
				$response['displayPopup'] = $current_views < 1;
				break;
			case 'always':
				$response['displayPopup'] = true;
				break;
			case 'never':
			default:
				$response['displayPopup'] = false;
				break;
		}
		if ( 'subscribed' === $subscription_status ) {
			$response['displayPopup'] = false;
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
			$data['subscription_status'] = $request['mailing_list_status'];
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
		$reader_id = isset( $request['rid'] ) ? sanitize_title( $request['rid'] ) : false;
		// TODO: Is retrieving the amp-access cookie the best way to get READER_ID outside the context of amp-access?
		if ( ! $reader_id ) {
			$reader_id = isset( $_COOKIE['amp-access'] ) ? sanitize_text_field( $_COOKIE['amp-access'] ) : false; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE
		}
		$popup_id = isset( $request['popup_id'] ) ? $request['popup_id'] : false;
		$url      = isset( $request['url'] ) ? esc_url_raw( urldecode( $request['url'] ) ) : false;
		if ( $reader_id && $url && $popup_id ) {
			$post_id = url_to_postid( $url ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.url_to_postid_url_to_postid
			if ( $post_id && 'post' === get_post_type( $post_id ) ) {
				return $reader_id . '-' . $popup_id . '-popup';
			}
		}
		return false;
	}
}
$newspack_popups_api = new Newspack_Popups_API();
