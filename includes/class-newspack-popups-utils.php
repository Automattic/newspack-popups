<?php
/**
 * Newspack Popups sutility functions.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Utility functions.
 */
final class Newspack_Popups_Utils {

	/**
	 * Update campaign options.
	 * 1. Create "inactive" and "active" campaign groups.
	 * 2. Assign all Test Mode campaigns to inactive, all others to active.
	 * 3. Test mode campaign frequencies change to "always" or "daily" depending on type.
	 * 4. Campaigns with frequency "never" are unpublished and frequencies are set to "always" or "daily".
	 * 5. "Active" campaign group become default, if none is already set.
	 */
	public function update_campaigns() {
		$active   = wp_create_term( 'Active', Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
		$inactive = wp_create_term( 'Inactive', Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );
		$popups   = Newspack_Popups_Model::retrieve_popups( true );
		foreach ( $popups as $popup ) {
			$frequency = $popup['options']['frequency'];
			$placement = $popup['options']['placement'];
			$id        = $popup['id'];
			wp_set_post_terms(
				$id,
				'test' === $frequency ? $inactive : $active,
				Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY,
				true
			);
			if ( 'never' === $frequency ) {
				wp_update_post(
					[
						'ID'          => $id,
						'post_status' => 'draft',
					]
				);
			}
			if ( in_array( [ 'test', 'never' ], $frequency ) ) {
				$options = [];
				if ( in_array( [ 'inline', 'above_header' ], $placement ) ) {
					$options['frequency'] = 'always';
				} else {
					$options['frequency'] = 'daily';
				}
				Newspack_Popups_Model::set_popup_options( $id, $options );
			}
		}
	}
}
