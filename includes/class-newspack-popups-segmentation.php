<?php
/**
 * Newspack Segmentation Plugin
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

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
	 * Names of custom dimensions options.
	 */
	const CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY = 'newspack_popups_cd_reader_frequency';
	const CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER    = 'newspack_popups_cd_is_subscriber';
	const CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR         = 'newspack_popups_cd_is_donor';

	/**
	 * Name of the option to store segments under.
	 */
	const SEGMENTS_OPTION_NAME = 'newspack_popups_segments';

	/**
	 * Installed version number of the custom table.
	 */
	const TABLE_VERSION = '1.0';

	/**
	 * Option name for the installed version number of the custom table.
	 */
	const TABLE_VERSION_OPTION = '_newspack_popups_table_versions';

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
		add_action( 'init', [ __CLASS__, 'check_update_version' ] );

		// Remove legacy pruning CRON job.
		add_action( 'init', [ __CLASS__, 'cron_deactivate' ] );

		add_filter( 'newspack_custom_dimensions', [ __CLASS__, 'register_custom_dimensions' ] );
		add_filter( 'newspack_custom_dimensions_values', [ __CLASS__, 'report_custom_dimensions' ] );
	}

	/**
	 * Clear the cron job when this plugin is deactivated.
	 */
	public static function cron_deactivate() {
		wp_clear_scheduled_hook( 'newspack_popups_segmentation_data_prune' );
	}

	/**
	 * Add custom custom dimensions to Newspack Plugin's Analytics Wizard.
	 *
	 * @param array $default_dimensions Default custom dimensions.
	 */
	public static function register_custom_dimensions( $default_dimensions ) {
		$default_dimensions = array_merge(
			$default_dimensions,
			[
				[
					'role'   => self::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
					'option' => [
						'value' => self::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
						'label' => __( 'Reader frequency', 'newspack' ),
					],
				],
				[
					'role'   => self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
					'option' => [
						'value' => self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
						'label' => __( 'Is a subcriber', 'newspack' ),
					],
				],
				[
					'role'   => self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
					'option' => [
						'value' => self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
						'label' => __( 'Is a donor', 'newspack' ),
					],
				],
			]
		);
		return $default_dimensions;
	}

	/**
	 * Add custom custom dimensions to Newspack Plugin's Analytics reporting.
	 *
	 * @param array $custom_dimensions_values Existing custom dimensions payload.
	 */
	public static function report_custom_dimensions( $custom_dimensions_values ) {
		$custom_dimensions = [];
		if ( class_exists( 'Newspack\Analytics_Wizard' ) ) {
			$custom_dimensions = Newspack\Analytics_Wizard::list_configured_custom_dimensions();
		}
		if ( empty( $custom_dimensions ) ) {
			return $custom_dimensions_values;
		}

		$campaigns_custom_dimensions = [
			self::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY,
			self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER,
			self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR,
		];
		$all_campaign_dimensions     = array_values(
			array_map(
				function( $custom_dimension ) {
					return $custom_dimension['role'];
				},
				$custom_dimensions
			)
		);

		// No need to proceed if the configured custom dimensions do not include any Campaigns data.
		if ( 0 === count( array_intersect( $campaigns_custom_dimensions, $all_campaign_dimensions ) ) ) {
			return $custom_dimensions_values;
		}

		foreach ( $custom_dimensions as $custom_dimension ) {
			// Strip the `ga:` prefix from gaID.
			$dimension_id = substr( $custom_dimension['gaID'], 3 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			switch ( $custom_dimension['role'] ) {
				case self::CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY:
					$read_count = 0; // TODO: get article view count from user meta/reader data
					// Tiers mimick NCI's – https://news-consumer-insights.appspot.com.
					$read_count_tier = 'casual';
					if ( $read_count > 1 && $read_count <= 14 ) {
						$read_count_tier = 'loyal';
					} elseif ( $read_count > 14 ) {
						$read_count_tier = 'brand_lover';
					}
					$custom_dimensions_values[ $dimension_id ] = $read_count_tier;
					break;
				case self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER:
					$custom_dimensions_values[ $dimension_id ] = false; // TODO: get is_subscriber from reader data.
					break;
				case self::CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR:
					$custom_dimensions_values[ $dimension_id ] = false; // TODO: get is_donor from reader data.
					break;
			}
		}

		return $custom_dimensions_values;
	}

	/**
	 * Permission callback for the API calls.
	 */
	public static function is_admin_user() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Checks if the custom table has been created and is up-to-date.
	 * If not, run the create_database_tables method.
	 * See: https://codex.wordpress.org/Creating_Tables_with_Plugins
	 */
	public static function check_update_version() {
		$current_version = get_option( self::TABLE_VERSION_OPTION, false );

		if ( self::TABLE_VERSION !== $current_version ) {
			self::create_database_tables();
			update_option( self::TABLE_VERSION_OPTION, self::TABLE_VERSION );
		}
	}

	/**
	 * Get all configured segments.
	 *
	 * @param boolean $include_inactive If true, fetch both inactive and active segments. If false, only fetch active segments.
	 *
	 * @return array Array of segments.
	 */
	public static function get_segments( $include_inactive = true ) {
		return Newspack_Segments_Model::get_segments( $include_inactive );
	}

	/**
	 * Get a single segment by ID.
	 *
	 * @param string $id A segment id.
	 * @return object|null The single segment object with matching ID, or null.
	 */
	public static function get_segment( $id ) {
		return Newspack_Segments_Model::get_segment( $id );
	}

	/**
	 * Get segment IDs.
	 */
	public static function get_segment_ids() {
		return Newspack_Segments_Model::get_segment_ids();
	}

	/**
	 * Create a segment.
	 *
	 * @param object $segment A segment.
	 * @deprecated
	 */
	public static function create_segment( $segment ) {
		return Newspack_Segments_Model::create_segment( $segment );
	}

	/**
	 * Delete a segment.
	 *
	 * @param string $id A segment id.
	 */
	public static function delete_segment( $id ) {
		return Newspack_Segments_Model::delete_segment( $id );
	}

	/**
	 * Update a segment.
	 *
	 * @param object $segment A segment.
	 */
	public static function update_segment( $segment ) {
		return Newspack_Segments_Model::update_segment( $segment );
	}

	/**
	 * Sort all segments by relative priority.
	 *
	 * @param array $segment_ids Array of segment IDs, in order of desired priority.
	 * @return array Array of sorted segments.
	 * @deprecated
	 */
	public static function sort_segments( $segment_ids ) {
		return Newspack_Segments_Model::sort_segments( $segment_ids );
	}

	/**
	 * Validate an array of segment IDs against the existing segment IDs in the options table.
	 * When re-sorting segments, the IDs passed should all exist, albeit in a different order,
	 * so if there are any differences, validation will fail.
	 *
	 * @param array $segment_ids Array of segment IDs to validate.
	 * @param array $segments    Array of existing segments to validate against.
	 * @return boolean Whether $segment_ids is valid.
	 * @deprecated
	 */
	public static function validate_segment_ids( $segment_ids, $segments ) {
		return Newspack_Segments_Model::validate_segment_ids( $segment_ids, $segments );
	}

	/**
	 * Reindex segment priorities based on current position in array.
	 *
	 * @param object $segments Array of segments.
	 * @deprecated
	 */
	public static function reindex_segments( $segments ) {
		return Newspack_Segments_Model::reindex_segments( $segments );
	}
}
Newspack_Popups_Segmentation::instance();
