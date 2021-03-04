<?php
/**
 * Newspack Placements Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Placements Plugin Class.
 */
final class Newspack_Popups_Placements {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Placements
	 */
	protected static $instance = null;

	/**
	 * Name of the option to store placements under.
	 */
	const PLACEMENTS_OPTION_NAME = 'newspack_popups_placements';

	/**
	 * Main Newspack Placements Plugin Instance.
	 * Ensures only one instance of Newspack Placements Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Placements Plugin Instance - Main instance.
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
	}

	/**
	 * Get default placements that exist for all sites.
	 */
	public static function get_default_placements() {
		return [
			'custom1' => __( 'Custom Placement 1' ),
			'custom2' => __( 'Custom Placement 2' ),
			'custom3' => __( 'Custom Placement 3' ),
		];
	}

	/**
	 * Get all configured placements.
	 *
	 * @return array Array of placements.
	 */
	public static function get_placements() {
		$placements = get_option( self::PLACEMENTS_OPTION_NAME, [] );
		return array_merge( $placements, self::get_default_placements() );
	}
}
Newspack_Popups_Placements::instance();
