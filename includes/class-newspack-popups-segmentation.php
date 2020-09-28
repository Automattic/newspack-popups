<?php
/**
 * Newspack Segmentation Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/segmentation/class-segmentation.php';

/**
 * Main Newspack Segmentation Plugin Class.
 */
final class Newspack_Popups_Segmentation {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Segmentation
	 */
	protected static $instance = null;

	/**
	 * Name of the Client ID, to be used by amp-analytics.
	 */
	const NEWSPACK_SEGMENTATION_CID_NAME = 'newspack-cid';

	/**
	 * Query param that will overwrite the cookie value.
	 */
	const NEWSPACK_SEGMENTATION_CID_LINKER_PARAM = 'ref_newspack_cid';

	/**
	 * Main Newspack Segmentation Plugin Instance.
	 * Ensures only one instance of Newspack Segmentation Plugin Instance is loaded or can be loaded.
	 *
	 * @return Newspack Segmentation Plugin Instance - Main instance.
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
		add_action( 'init', [ __CLASS__, 'create_database_table' ] );
		add_action( 'wp_footer', [ __CLASS__, 'insert_amp_analytics' ], 20 );
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'wp_enqueue_scripts' ] );
	}

	/**
	 * Permission callback for the API calls.
	 */
	public static function is_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Should tracking code be inserted?
	 */
	public static function is_tracking() {
		if ( self::is_admin_user() ) {
			return false;
		}
		return true;
	}

	/**
	 * Insert amp-analytics scripts.
	 */
	public static function wp_enqueue_scripts() {
		if ( ! self::is_tracking() ) {
			return;
		}

		// Register AMP scripts explicitly for non-AMP pages.
		if ( ! is_admin() && ! wp_script_is( 'amp-runtime', 'registered' ) ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_register_script(
				'amp-runtime',
				'https://cdn.ampproject.org/v0.js',
				null,
				null,
				true
			);
		}
		$scripts = [ 'amp-analytics' ];
		foreach ( $scripts as $script ) {
			if ( ! wp_script_is( $script, 'registered' ) ) {
				$path = "https://cdn.ampproject.org/v0/{$script}-latest.js";
				// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
				wp_register_script(
					$script,
					$path,
					array( 'amp-runtime' ),
					null,
					true
				);
			}
			wp_enqueue_script( $script );
		}
	}

	/**
	 * Insert amp-analytics tracking code.
	 * Has to be included on every page to set the cookie.
	 */
	public static function insert_amp_analytics() {
		if ( ! self::is_tracking() ) {
			return;
		}

		$endpoint = self::get_segmentation_endpoint();

		$categories   = get_the_category();
		$category_ids = '';
		if ( ! empty( $categories ) ) {
			$category_ids = implode(
				',',
				array_map(
					function( $cat ) {
						return $cat->term_id;
					},
					$categories
				)
			);
		}

		$linker_id            = 'cid';
		$amp_analytics_config = [
			'requests' => [
				// The clientId value will be read from cookie.
				'event' => esc_url( $endpoint ) . '?is_post=' . ( is_single() ? 1 : 0 ) . '&clientId=${clientId(' . esc_attr( self::NEWSPACK_SEGMENTATION_CID_NAME ) . ')}',
			],
			'triggers' => [
				'trackPageview' => [
					'on'             => 'visible',
					'request'        => 'event',
					'extraUrlParams' => [
						'id'         => esc_attr( get_the_ID() ),
						'categories' => esc_attr( $category_ids ),
					],
				],
			],
			// Linker will append a query param to all internal links.
			// This will only be performed on a proxy site (like AMP cache) by default.
			// https://amp.dev/documentation/components/amp-analytics/?format=websites#linkers.
			'linkers'  => [
				'enabled' => true,
				self::NEWSPACK_SEGMENTATION_CID_LINKER_PARAM => [
					'ids' => [
						$linker_id => 'CLIENT_ID(' . self::NEWSPACK_SEGMENTATION_CID_NAME . ')',
					],
				],
			],
			// If the linker parameter is found, the cookie value will be overwritten by it.
			// https://amp.dev/documentation/components/amp-analytics/?format=websites#cookies.
			'cookies'  => [
				'enabled'                            => true,
				self::NEWSPACK_SEGMENTATION_CID_NAME => [
					'value' => 'LINKER_PARAM(' . self::NEWSPACK_SEGMENTATION_CID_LINKER_PARAM . ', ' . $linker_id . ')',
				],
			],
		];

		?>
			<amp-analytics>
				<script type="application/json">
					<?php echo wp_json_encode( $amp_analytics_config ); ?>
				</script>
			</amp-analytics>
		<?php
	}

	/**
	 * Endpoint to handle Pop-up data.
	 *
	 * @return string Endpoint URL.
	 */
	public static function get_segmentation_endpoint() {
		return plugins_url( '../api/segmentation/index.php', __FILE__ );
	}

	/**
	 * Create the clients and events tables.
	 */
	public static function create_database_table() {
		global $wpdb;
		$events_table_name = Segmentation::get_events_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $events_table_name ) ) != $events_table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $events_table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				created_at date NOT NULL,
				-- type of event
				type varchar(20) NOT NULL,
				-- Unique id of a device/browser pair
				client_id varchar(100) NOT NULL,
				-- Article ID
				post_id bigint(20),
				-- Article categories IDs
				category_ids varchar(100),
				UNIQUE KEY client_id_post_id (client_id, post_id),
				PRIMARY KEY  (id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.dbDelta_dbdelta
		}
	}

}
Newspack_Popups_Segmentation::instance();
