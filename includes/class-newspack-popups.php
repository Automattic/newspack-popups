<?php
/**
 * Newspack Popups set up
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Popups Class.
 */
final class Newspack_Popups {

	const NEWSPACK_PLUGINS_CPT = 'newspack_popups_cpt';

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups
	 */
	protected static $instance = null;

	/**
	 * The ID of the sitewide default Popup.
	 *
	 * @var integer
	 */
	protected static $sitewide_popup_id = -1; // -1 signifies this has not been set

	/**
	 * Main Newspack Ads Instance.
	 * Ensures only one instance of Newspack Ads is loaded or can be loaded.
	 *
	 * @return Newspack Ads - Main instance.
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
		add_action( 'init', [ __CLASS__, 'register_cpt' ] );
		add_action( 'init', [ __CLASS__, 'register_meta' ] );
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
		add_filter( 'display_post_states', [ __CLASS__, 'display_post_states' ], 10, 2 );

		include_once dirname( __FILE__ ) . '/class-newspack-popups-inserter.php';
		include_once dirname( __FILE__ ) . '/class-newspack-popups-api.php';
	}

	/**
	 * Register the custom post type.
	 */
	public static function register_cpt() {
		$labels = [
			'name'               => _x( 'Pop-ups', 'post type general name', 'newspack-popups' ),
			'singular_name'      => _x( 'Pop-up', 'post type singular name', 'newspack-popups' ),
			'menu_name'          => _x( 'Pop-ups', 'admin menu', 'newspack-popups' ),
			'name_admin_bar'     => _x( 'Pop-up', 'add new on admin bar', 'newspack-popups' ),
			'add_new'            => _x( 'Add New', 'popup', 'newspack-popups' ),
			'add_new_item'       => __( 'Add New Pop-up', 'newspack-popups' ),
			'new_item'           => __( 'New Pop-up', 'newspack-popups' ),
			'edit_item'          => __( 'Edit Pop-up', 'newspack-popups' ),
			'view_item'          => __( 'View Pop-up', 'newspack-popups' ),
			'all_items'          => __( 'All Pop-ups', 'newspack-popups' ),
			'search_items'       => __( 'Search Pop-ups', 'newspack-popups' ),
			'parent_item_colon'  => __( 'Parent Pop-ups:', 'newspack-popups' ),
			'not_found'          => __( 'No pop-ups found.', 'newspack-popups' ),
			'not_found_in_trash' => __( 'No pop-ups found in Trash.', 'newspack-popups' ),
		];

		$cpt_args = [
			'labels'       => $labels,
			'public'       => false,
			'show_ui'      => true,
			'show_in_rest' => true,
			'supports'     => [ 'editor', 'title', 'custom-fields' ],
			'taxonomies'   => [ 'category' ],
			'menu_icon'    => 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSIjYTBhNWFhIiBkPSJNMTEuOTkgMTguNTRsLTcuMzctNS43M0wzIDE0LjA3bDkgNyA5LTctMS42My0xLjI3LTcuMzggNS43NHpNMTIgMTZsNy4zNi01LjczTDIxIDlsLTktNy05IDcgMS42MyAxLjI3TDEyIDE2eiIvPjwvc3ZnPgo=',
		];
		\register_post_type( self::NEWSPACK_PLUGINS_CPT, $cpt_args );
	}

	/**
	 * Register custom fields.
	 */
	public static function register_meta() {
		\register_meta(
			'post',
			'trigger_type',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
		\register_meta(
			'post',
			'trigger_scroll_progress',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'integer',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
		\register_meta(
			'post',
			'trigger_delay',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'integer',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);

		\register_meta(
			'post',
			'frequency',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);

		\register_meta(
			'post',
			'placement',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);

		\register_meta(
			'post',
			'utm_suppression',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);

		\register_meta(
			'post',
			'overlay_color',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'string',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);

		\register_meta(
			'post',
			'overlay_opacity',
			[
				'object_subtype' => self::NEWSPACK_PLUGINS_CPT,
				'show_in_rest'   => true,
				'type'           => 'integer',
				'single'         => true,
				'auth_callback'  => '__return_true',
			]
		);
	}

	/**
	 * Load up common JS/CSS for wizards.
	 */
	public static function enqueue_block_editor_assets() {
		$screen = get_current_screen();
		if ( self::NEWSPACK_PLUGINS_CPT !== $screen->post_type ) {
			return;
		}

		\wp_enqueue_script(
			'newspack-popups',
			plugins_url( '../dist/editor.js', __FILE__ ),
			[ 'wp-components' ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/editor.js' ),
			true
		);

		\wp_register_style(
			'newspack-popups',
			plugins_url( '../dist/editor.css', __FILE__ ),
			[ 'wp-components' ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/editor.css' )
		);
		\wp_style_add_data( 'newspack-popups', 'rtl', 'replace' );
		\wp_enqueue_style( 'newspack-popups' );
	}

	/**
	 * Display 'Sitewide Default' state by the appropriate pop-up.
	 *
	 * @param array   $post_states An array of post display states.
	 * @param WP_Post $post        The current post object.
	 */
	public static function display_post_states( $post_states, $post ) {
		if ( self::NEWSPACK_PLUGINS_CPT !== $post->post_type ) {
			return $post_states;
		}
		if ( -1 === self::$sitewide_popup_id ) {
			self::$sitewide_popup_id = null; // Setting to null indicates the query has been performed once, and needn't be repeated.

			$query = new WP_Query(
				[
					'post_type'        => self::NEWSPACK_PLUGINS_CPT,
					'post_status'      => 'publish',
					'posts_per_page'   => 1,
					'tax_query'        => [ //phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
						[
							'taxonomy' => 'category',
							'operator' => 'NOT EXISTS',
						],
					],


					'category__not_in' => get_terms(
						'category',
						[
							'fields' => 'ids',
						]
					),
				]
			);
			if ( $query->have_posts() ) {
				self::$sitewide_popup_id = $query->posts[0]->ID;
			}
		}
		if ( $post->ID === self::$sitewide_popup_id ) {
			$post_states['newspack_popups_sitewide_default'] = __( 'Sitewide Default', 'newspack-popups' );
		}
		return $post_states;
	}
}
Newspack_Popups::instance();
