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
		self::register_default_criteria();
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
	}

	/**
	 * Register default criteria.
	 */
	private static function register_default_criteria() {
		$criteria = [
			/**
			 * Reader Engagement.
			 */
			'articles_read'            => [
				'name'              => __( 'Articles read', 'newspack-popups' ),
				'description'       => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'range',
			],
			'articles_read_in_session' => [
				'name'              => __( 'Articles read in session', 'newspack-popups' ),
				'description'       => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'range',
			],
			'favorite_categories'      => [
				'name'              => __( 'Favorite Categories', 'newspack-popups' ),
				'description'       => __( 'Most read categories of reader.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'list__in',
			],
			/**
			 * Reader Activity.
			 */
			'newsletter'               => [
				'name'     => __( 'Newsletter', 'newspack-popups' ),
				'category' => 'reader_activity',
				'options'  => [
					[
						'name'  => __( 'Subscribers and non-subscribers', 'newspack-popups' ),
						'value' => 0,
					],
					[
						'name'  => __( 'Subscribers', 'newspack-popups' ),
						'value' => 1,
					],
					[
						'name'  => __( 'Non-subscribers', 'newspack-popups' ),
						'value' => 2,
					],
				],
			],
			'donation'                 => [
				'name'        => __( 'Donation', 'newspack-popups' ),
				'description' => __( '(if checkout happens on-site)', 'newspack-popups' ),
				'category'    => 'reader_activity',
				'options'     => [
					[
						'name'  => __( 'Donors and non-donors', 'newspack-popups' ),
						'value' => 0,
					],
					[
						'name'  => __( 'Donors', 'newspack-popups' ),
						'value' => 1,
					],
					[
						'name'  => __( 'Non-donors', 'newspack-popups' ),
						'value' => 2,
					],
					[
						'name'  => __( 'Former donors (who cancelled a recurring donation)', 'newspack-popups' ),
						'value' => 3,
					],
				],
			],
			'user_account'             => [
				'name'     => __( 'User Account', 'newspack-popups' ),
				'category' => 'reader_activity',
				'options'  => [
					[
						'name'  => __( 'All users', 'newspack-popups' ),
						'value' => 0,
					],
					[
						'name'  => __( 'Has user account', 'newspack-popups' ),
						'value' => 1,
					],
					[
						'name'  => __( 'Does not have user account', 'newspack-popups' ),
						'value' => 2,
					],
				],
			],
			/**
			 * Referrer Sources.
			 */
			'sources_to_match'         => [
				'name'              => __( 'Sources to match', 'newspack-popups' ),
				'description'       => __( 'Segment based on traffic source', 'newspack-popups' ),
				'help'              => __( 'A comma-separated list of domains.', 'newspack-popups' ),
				'placeholder'       => 'google.com, facebook.com',
				'category'          => 'referrer_sources',
				'matching_function' => 'list__in',
			],
			'sources_to_exclude'       => [
				'name'              => __( 'Sources to exclude', 'newspack-popups' ),
				'description'       => __( 'Segment based on traffic source - hide campaigns for visitors coming from specific sources.', 'newspack-popups' ),
				'help'              => __( 'A comma-separated list of domains.', 'newspack-popups' ),
				'placeholder'       => 'twitter.com, instagram.com',
				'category'          => 'referrer_sources',
				'matching_function' => 'list__not_in',
			],
		];
		foreach ( $criteria as $id => $config ) {
			self::register_criteria( $id, $config );
		}
	}

	/**
	 * Enqueue scripts.
	 */
	public static function enqueue_scripts() {
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
