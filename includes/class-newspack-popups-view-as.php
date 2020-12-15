<?php
/**
 * Newspack Popups View As
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Popups View As Class.
 */
final class Newspack_Popups_View_As {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_View_As
	 */
	protected static $instance = null;

	/**
	 * Cookie name for the "view as" feature.
	 */
	const COOKIE_NAME = 'newspack_popups_view_as';

	/**
	 * Main Newspack Popups View As Instance.
	 * Ensures only one instance of Newspack Popups View As Instance is loaded or can be loaded.
	 *
	 * @return Newspack Popups View As Instance - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register the query param.
		add_filter(
			'query_vars',
			function ( $vars ) {
				$vars[] = 'view_as';
				return $vars;
			}
		);
	}

	/**
	 * Get the "view as" feature specification.
	 *
	 * @return string "View as" specification.
	 */
	public static function viewing_as_spec() {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_others_pages' ) ) {
			return false;
		}
		if ( get_query_var( 'view_as' ) ) {
			return get_query_var( 'view_as' );
		}
	}
}

Newspack_Popups_View_As::instance();
