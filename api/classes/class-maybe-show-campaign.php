<?php
/**
 * Newspack Campaigns maybe display campaign.
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once 'class-lightweight-api.php';

/**
 * GET endpoint to determine if campaign is shown or not.
 */
class Maybe_Show_Campaign extends Lightweight_API {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$data                = $this->get_transient( $this->transient_name() );
		$current_views       = (int) $data['count'];
		$suppress_forever    = ! empty( $data['suppress_forever'] ) ? (int) $data['suppress_forever'] : false;
		$mailing_list_status = ! empty( $data['mailing_list_status'] ) ? (int) $data['mailing_list_status'] : false;
		$last_view           = ! empty( $data['time'] ) ? (int) $data['time'] : 0;
		if ( $suppress_forever || $mailing_list_status ) {
			$this->response['displayPopup'] = false;
		} else {
			switch ( $this->frequency ) {
				case 'daily':
					$this->response['displayPopup'] = $last_view < strtotime( '-1 day' );
					break;
				case 'once':
					$this->response['displayPopup'] = $current_views < 1;
					break;
				case 'test':
				case 'always':
					$this->response['displayPopup'] = true;
					break;
				case 'never':
				default:
					$this->response['displayPopup'] = false;
					break;
			}
		}


		// Suppressing based on UTM Source parameter in the URL.
		// If the visitor came from a campaign with suppressed utm_source, then it should not be displayed.
		$referer_url = filter_input( INPUT_SERVER, 'HTTP_REFERER', FILTER_SANITIZE_STRING );
		if ( $this->utm_suppression ) {
			$utm_source_transient_name = $this->get_suppression_data_transient_name( 'utm_source' );
			$utm_source_transient      = $this->get_transient( $utm_source_transient_name );
			if ( $referer_url && stripos( urldecode( $referer_url ), 'utm_source=' . $utm_suppression ) ) {
				$this->response['displayPopup']                 = false;
				$utm_source_transient[ $this->utm_suppression ] = true;
				$this->set_transient( $utm_source_transient_name, $utm_source_transient );
			}

			if ( ! empty( $utm_source_transient[ $utm_suppression ] ) ) {
				$this->response['displayPopup'] = false;
			}
		}

		// Suppressing based on UTM Medium parameter in the URL. If:
		// - the visitor came from email,
		// - the suppress_newsletter_campaigns setting is on,
		// - the pop-up has a newsletter form,
		// then it should not be displayed.
		$has_utm_medium_in_url     = stripos( $referer_url, 'utm_medium=email' );
		$utm_medium_transient_name = $this->get_suppression_data_transient_name( 'utm_medium' );

		if (
			$this->suppress_newsletter_campaigns &&
			$this->has_newsletter_prompt &&
			( $has_utm_medium_in_url || $this->get_transient( $utm_medium_transient_name ) )
		) {
			$this->response['displayPopup'] = false;
			$this->set_transient( $utm_medium_transient_name, true );
		}

		// Suppressing because user has dismissed the popup permanently, or signed up to the newsletter.
		if ( $suppress_forever || $mailing_list_status ) {
			$this->response['displayPopup'] = false;
		}

		// Suppressing a newsletter campaign if any newsletter campaign was dismissed.
		$name = $this->get_suppression_data_transient_name( '-newsletter-campaign-suppression' );

		if ( $this->suppress_all_newsletter_campaigns_if_one_dismissed && $this->has_newsletter_prompt && $this->get_transient( $name ) ) {
			$this->response['displayPopup'] = false;
		}

		// TODO: Preview handling.

		$this->respond();
	}

	/**
	 * Set the utm_source suppression-related transient.
	 *
	 * @param string $utm_source utm_source param.
	 */
	public function set_utm_source_transient( $utm_source ) {
		if ( ! empty( $utm_source ) ) {
			$transient_name           = $this->get_suppression_data_transient_name( 'utm_source' );
			$transient                = $this->get_transient( $transient_name );
			$transient[ $utm_source ] = true;
			set_transient( $transient_name, true, 0 );
		}
	}
}
new Maybe_Show_Campaign();
