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
	public function update_campaigns( $reset = false ) {
		$active_campaign_group = get_option( Newspack_Popups::NEWSPACK_POPUPS_ACTIVE_CAMPAIGN_GROUP );
		if ( ! $reset && $active_campaign_group && get_term( $active_campaign_group, Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY ) ) {
			return new \WP_Error(
				'newspack_popups_update_error',
				esc_html__( 'There is already an active campaign group.', 'newspack-popups' )
			);
		}

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
			if ( in_array( $frequency, [ 'test', 'never' ] ) ) {
				$options = [];
				if ( in_array( $placement, [ 'inline', 'above_header' ] ) ) {
					update_post_meta( $id, 'frequency', 'always' );
				} else {
					update_post_meta( $id, 'frequency', 'daily' );
				}
			}
		}
		return true;
	}
}
