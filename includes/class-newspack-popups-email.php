<?php
/**
 * Newspack Popups Email handling
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

use \DrewM\MailChimp\MailChimp;

/**
 * Manages Email handling.
 */
class Newspack_Popups_Email {
	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'wp_loaded', [ __CLASS__, 'handle_url_information' ] );
	}

	/**
	 * Use Mailchimp's URL params to retrieve subscriber data and update segmentation table.
	 */
	public static function handle_url_information() {
		if ( isset( $_GET['mc_cid'], $_GET['mc_eid'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$newsletters_mc_api_key = get_option( 'newspack_newsletters_mailchimp_api_key', '' );
			$mc_campaign_id         = sanitize_text_field( $_GET['mc_cid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$mc_recipient_id        = sanitize_text_field( $_GET['mc_eid'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $newsletters_mc_api_key ) ) {
				$mc            = new Mailchimp( $newsletters_mc_api_key );
				$campaign_data = $mc->get( "campaigns/$mc_campaign_id" );
				if ( isset( $campaign_data['recipients'], $campaign_data['recipients']['list_id'] ) ) {
					$list_id = $campaign_data['recipients']['list_id'];
					$members = $mc->get( "/lists/$list_id/members", [ 'unique_email_id' => $mc_recipient_id ] )['members'];

					if ( ! empty( $members ) ) {
						$client             = $members[0];
						$client_data_update = [
							'email_subscription' => [
								'email' => $client['email_address'],
							],
						];
						$revenue            = $client['stats']['ecommerce_data']['total_revenue'];
						if ( $revenue > 0 ) {
							$client_data_update['donation'] = [
								'mailchimp_revenue' => $revenue,
							];
						}
						Newspack_Popups_Segmentation::update_client_data(
							Newspack_Popups_Segmentation::get_client_id(),
							$client_data_update
						);
					}
				}
			}
		}
	}
}

Newspack_Popups_Email::init();
