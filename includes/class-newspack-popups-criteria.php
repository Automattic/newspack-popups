<?php
/**
 * Newspack Popups Criteria System
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Criteria Class
 */
final class Newspack_Popups_Criteria {

	const SCRIPT_HANDLE = 'newspack-popups-criteria';

	/**
	 * Registered criteria.
	 *
	 * @var array
	 */
	protected static $registered_criteria = [];

	/**
	 * Default criteria config.
	 *
	 * @var array
	 */
	protected static $default_config = [
		'category'          => 'reader_activity',
		'matching_function' => 'default',
	];

	/**
	 * Initialize the hooks.
	 */
	public static function init() {
		require_once dirname( __FILE__ ) . '/../src/criteria/default/index.php';
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts() {
		if ( defined( 'IS_TEST_ENV' ) && IS_TEST_ENV ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( '../dist/criteria.js', __FILE__ ),
			[],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/criteria.js' ),
			true
		);
		wp_script_add_data( self::SCRIPT_HANDLE, 'defer', true );
		wp_localize_script(
			self::SCRIPT_HANDLE,
			'newspackPopupsCriteria',
			[
				'config' => self::get_criteria_config(),
			]
		);
		wp_enqueue_script(
			'newspack-popups-default-criteria',
			plugins_url( '../dist/defaultCriteria.js', __FILE__ ),
			[ self::SCRIPT_HANDLE ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/defaultCriteria.js' ),
			true
		);
		wp_script_add_data( 'newspack-popups-default-criteria', 'defer', true );
		wp_enqueue_script(
			'newspack-popups-segments-example',
			plugins_url( '../dist/segmentsExample.js', __FILE__ ),
			[ self::SCRIPT_HANDLE ],
			filemtime( dirname( NEWSPACK_POPUPS_PLUGIN_FILE ) . '/dist/segmentsExample.js' ),
			true
		);
		wp_localize_script(
			'newspack-popups-segments-example',
			'newspackPopupsSegmentsExample',
			[
				'segments' => array_reduce(
					Newspack_Popups_Segmentation::get_segments( false ),
					function( $segments, $item ) {
						$segments[ $item['id'] ] = $item['criteria'] ?? [];
						return $segments;
					},
					[]
				),
			]
		);
		wp_script_add_data( 'newspack-popups-segments-example', 'defer', true );
	}

	/**
	 * Get registered criteria.
	 *
	 * @return array
	 */
	public static function get_registered_criteria() {
		$criteria = [];
		foreach ( self::$registered_criteria as $id => $config ) {
			$criteria[] = array_merge( [ 'id' => $id ], $config );
		}
		/**
		 * Filter the registered criteria.
		 *
		 * @param array $criteria The registered criteria.
		 */
		return apply_filters( 'newspack_popups_registered_criteria', $criteria );
	}

	/**
	 * Get registered criteria config to be used in the front-end.
	 *
	 * @return array
	 */
	public static function get_criteria_config() {
		$config = [];
		foreach ( self::get_registered_criteria() as $criteria ) {
			$config[ $criteria['id'] ] = [
				'matchingFunction'  => $criteria['matching_function'],
				'matchingAttribute' => $criteria['matching_attribute'],
			];
		}
		return $config;
	}

	/**
	 * Register a new criteria.
	 *
	 * @param string $id     The criteria id.
	 * @param array  $config The criteria config.
	 *
	 * @return void|WP_Error
	 */
	public static function register_criteria( $id, $config = [] ) {
		if ( isset( self::$registered_criteria[ $id ] ) ) {
			return new WP_Error( 'newspack_popups_criteria_already_registered', __( 'Criteria already registered.', 'newspack-popups' ) );
		}
		$criteria = wp_parse_args( $config, self::$default_config );
		if ( empty( $criteria['name'] ) ) {
			$criteria['name'] = ucwords( str_replace( '_', ' ', $id ) );
		}
		if ( empty( $criteria['matching_attribute'] ) ) {
			$criteria['matching_attribute'] = $id;
		}
		self::$registered_criteria[ $id ] = $criteria;
	}
}
Newspack_Popups_Criteria::init();
