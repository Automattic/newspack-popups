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
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'manage_editor_assets' ] );
		add_action( 'init', [ __CLASS__, 'manage_view_assets' ] );
		add_action( 'rest_api_init', [ __CLASS__, 'rest_api_init' ] );
	}

	/**
	 * Enqueue editor assets.
	 */
	public static function manage_editor_assets() {
		\wp_enqueue_script(
			'newspack-popups-blocks',
			plugins_url( '../dist/blocks.js', __FILE__ ),
			[],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/blocks.js' ),
			true
		);

		\wp_localize_script(
			'newspack-popups-blocks',
			'newspack_popups_blocks_data',
			[
				'custom_placements' => self::get_custom_placements(),
			]
		);

		\wp_register_style(
			'newspack-popups-blocks',
			plugins_url( '../dist/blocks.css', __FILE__ ),
			[],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/blocks.css' )
		);
		wp_style_add_data( 'newspack-popups-blocks', 'rtl', 'replace' );
		wp_enqueue_style( 'newspack-popups-blocks' );
	}

	/**
	 * Enqueue front-end assets.
	 */
	public static function manage_view_assets() {
		// Do nothing in editor environment.
		if ( is_admin() ) {
			return;
		}

		$src_directory  = dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/src/blocks/';
		$dist_directory = dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/';
		$iterator       = new \DirectoryIterator( $src_directory );

		foreach ( $iterator as $block_directory ) {
			if ( ! $block_directory->isDir() || $block_directory->isDot() ) {
				continue;
			}
			$type = $block_directory->getFilename();

			/* If view.php is found, include it and use for block rendering. */
			$view_php_path = $src_directory . $type . '/view.php';
			if ( file_exists( $view_php_path ) ) {
				include_once $view_php_path;
				continue;
			}
		}
	}

	/**
	 * Initialise REST API endpoints.
	 */
	public static function rest_api_init() {
		\register_rest_route(
			'newspack-popups/v1',
			'custom-placement',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'api_get_prompts_for_custom_placement' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Get prompts assigned to a given custom placement.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response.
	 */
	public static function api_get_prompts_for_custom_placement( $request ) {
		return [];
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
