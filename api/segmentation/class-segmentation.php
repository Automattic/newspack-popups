<?php
/**
 * Newspack Popups Segmentation
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manages Segmentation.
 */
class Segmentation {
	/**
	 * Names of custom dimensions options.
	 */
	const CUSTOM_DIMENSIONS_OPTION_NAME_READER_FREQUENCY = 'newspack_popups_cd_reader_frequency';
	const CUSTOM_DIMENSIONS_OPTION_NAME_IS_SUBSCRIBER    = 'newspack_popups_cd_is_subscriber';
	const CUSTOM_DIMENSIONS_OPTION_NAME_IS_DONOR         = 'newspack_popups_cd_is_donor';

	/**
	 * Get log file path.
	 */
	public static function get_log_file_path() {
		global $table_prefix;
		return get_temp_dir() . $table_prefix . 'newspack-popups-events.log';
	}

	/**
	 * Get events table name.
	 */
	public static function get_events_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_events';
	}

	/**
	 * Get transients table name.
	 */
	public static function get_transients_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'newspack_campaigns_transients';
	}

	/**
	 * Parse "view as" spec.
	 *
	 * @param string $raw_spec Raw spec.
	 * @return object Parsed spac.
	 */
	public static function parse_view_as( $raw_spec ) {
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
