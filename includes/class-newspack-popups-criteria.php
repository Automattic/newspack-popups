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
				'help'              => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'range',
			],
			'articles_read_in_session' => [
				'name'              => __( 'Articles read in session', 'newspack-popups' ),
				'help'              => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'range',
			],
			'favorite_categories'      => [
				'name'              => __( 'Favorite Categories', 'newspack-popups' ),
				'help'              => __( 'Most read categories of reader.', 'newspack-popups' ),
				'category'          => 'reader_engagement',
				'matching_function' => 'list',
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
				'name'     => __( 'Donation', 'newspack-popups' ),
				'help'     => __( '(if checkout happens on-site)', 'newspack-popups' ),
				'category' => 'reader_activity',
				'options'  => [
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
				'help'              => __( 'Segment based on traffic source', 'newspack-popups' ),
				'description'       => __( 'A comma-separated list of domains.', 'newspack-popups' ),
				'matching_function' => 'list',
			],
			'sources_to_exclude'       => [
				'name'        => __( 'Sources to exclude', 'newspack-popups' ),
				'help'        => __( 'Segment based on traffic source - hide campaigns for visitors coming from specific sources.', 'newspack-popups' ),
				'description' => __( 'A comma-separated list of domains.', 'newspack-popups' ),
			],
		];
		foreach ( $criteria as $slug => $config ) {
			self::register_criteria( $slug, $config );
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
				'config'   => self::get_criteria_config(),
				'segments' => Newspack_Popups_Segmentation::get_segments( false ),
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
	}

	/**
	 * Get registered criteria.
	 *
	 * @return array
	 */
	public static function get_registered_criteria() {
		return self::$registered_criteria;
	}

	/**
	 * Get registered criteria config to be used in the front-end.
	 *
	 * @return array
	 */
	public static function get_criteria_config() {
		$config = [];
		foreach ( self::$registered_criteria as $slug => $criteria ) {
			$config[ $slug ] = [
				'id'                => $slug,
				'matchingFunction'  => $criteria['matching_function'],
				'matchingAttribute' => $criteria['matching_attribute'],
			];
		}
		return array_values( $config );
	}

	/**
	 * Register a new criteria.
	 *
	 * @param string $slug   The criteria slug.
	 * @param array  $config The criteria config.
	 *
	 * @return void|WP_Error
	 */
	public static function register_criteria( $slug, $config = [] ) {
		if ( isset( self::$registered_criteria[ $slug ] ) ) {
			return new WP_Error( 'newspack_popups_criteria_already_registered', __( 'Criteria already registered.', 'newspack-popups' ) );
		}
		$criteria = wp_parse_args( $config, self::$default_config );
		if ( empty( $criteria['name'] ) ) {
			$criteria['name'] = ucwords( str_replace( '_', ' ', $slug ) );
		}
		if ( empty( $criteria['matching_attribute'] ) ) {
			$criteria['matching_attribute'] = $slug;
		}
		self::$registered_criteria[ $slug ] = $criteria;
	}
}
Newspack_Popups_Criteria::init();
