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
	 * Get the "view as" feature specification.
	 *
	 * @return string|false "View as" specification, or false if not applicable.
	 */
	public static function viewing_as_spec() {
		static $cache = [];
		$raw_view_as = isset( $_GET['view_as'] ) ? $_GET['view_as'] : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Key on the current user ID and the raw view_as value, so a cached
		// result from an earlier user/query context (e.g. after
		// wp_set_current_user() or go_to() in tests) can't leak into a later
		// call within the same PHP process.
		$key = get_current_user_id() . ':' . $raw_view_as;
		if ( array_key_exists( $key, $cache ) ) {
			return $cache[ $key ];
		}

		if ( ! Newspack_Popups::is_user_admin() ) {
			$cache[ $key ] = false;
			return false;
		}
		if ( $raw_view_as ) {
			$cache[ $key ] = sanitize_text_field( $raw_view_as );
			return $cache[ $key ];
		}
		$cache[ $key ] = false;
		return false;
	}

	/**
	 * Parse "view as" spec.
	 *
	 * @param string|null $raw_spec Raw spec. If null, read from $_GET['view_as'].
	 * @return object Parsed spac.
	 */
	public static function parse_view_as( $raw_spec = null ) {
		if ( empty( $raw_spec ) ) {
			$raw_spec = self::viewing_as_spec();
		}

		if ( empty( $raw_spec ) ) {
			return [];
		}

		return array_reduce(
			explode( ';', $raw_spec ),
			function( $acc, $item ) {
				$parts = explode( ':', $item );
				if ( 1 === count( $parts ) ) {
					$acc[ $parts[0] ] = true;
				} else {
					$acc[ $parts[0] ] = $parts[1];
				}
				return $acc;
			},
			[]
		);
	}
}

Newspack_Popups_View_As::instance();
