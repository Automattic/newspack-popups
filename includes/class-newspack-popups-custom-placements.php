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
final class Newspack_Popups_Custom_Placements {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Custom_Placements
	 */
	protected static $instance = null;

	/**
	 * Name of the option to store placements under.
	 */
	const PLACEMENTS_OPTION_NAME = 'newspack_popups_custom_placements';

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
	public static function get_default_custom_placements() {
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
	public static function get_custom_placements() {
		$placements = get_option( self::PLACEMENTS_OPTION_NAME, [] );
		return array_merge( self::get_default_custom_placements(), $placements );
	}

	/**
	 * Get a simple array of placement values.
	 *
	 * @return array Array of placement values.
	 */
	public static function get_custom_placement_values() {
		return array_keys( self::get_custom_placements() );
	}

	/**
	 * Determine whether the given prompt should be displayed via custom placement.
	 *
	 * @param object $prompt The prompt to assess.
	 * @return boolean Whether or not the prompt has a custom placement.
	 */
	public static function is_custom_placement( $prompt ) {
		return (
			'manual' === $prompt['options']['frequency'] ||
			in_array( $prompt['options']['frequency'], self::get_custom_placement_values() )
		);
	}
}
Newspack_Popups_Custom_Placements::instance();
