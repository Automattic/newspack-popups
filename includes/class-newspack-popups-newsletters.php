<?php
/**
 * Newspack Popups Newsletters
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/campaigns/class-campaign-data-utils.php';

/**
 * Main Newspack Popups Newsletters Class.
 */
final class Newspack_Popups_Newsletters {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Newsletters
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Popups Newsletters Instance.
	 * Ensures only one instance of Newspack Popups Newsletters Instance is loaded or can be loaded.
	 *
	 * @return Newspack Popups Newsletters Instance - Main instance.
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
		\add_action( 'init', [ __CLASS__, 'check_login_status' ] );
		\add_action( 'newspack_newsletters_add_contact', [ __CLASS__, 'handle_newsletter_subscription' ], 10, 4 );
		\add_filter( 'newspack_auth_intention', [ __CLASS__, 'check_reader_newsletter_subscription_status' ] );
		\add_action( 'newspack_registered_reader', [ __CLASS__, 'check_reader_newsletter_subscription_status' ] );
	}

	/**
	 * If user is logged in, check their newsletter susbscription status.
	 */
	public static function check_login_status() {
		if ( \is_user_logged_in() && ! \is_admin() && ! Newspack_Popups::is_preview_request() ) {
			$current_user  = \wp_get_current_user();
			$email_address = $current_user->user_email;

			if ( ! empty( $email_address ) ) {
				self::check_reader_newsletter_subscription_status( $email_address );
			}
		}
	}

	/**
	 * Update reader events when the Newspack Newsletters subscribe form adds a contact to a list.
	 *
	 * @param string        $provider The provider name.
	 * @param array         $contact  {
	 *    Contact information.
	 *
	 *    @type string   $email    Contact email address.
	 *    @type string   $name     Contact name. Optional.
	 *    @type string[] $metadata Contact additional metadata. Optional.
	 * }
	 * @param string[]      $lists    Array of list IDs to subscribe the contact to.
	 * @param bool|WP_Error $result   True if the contact was added or error if failed.
	 */
	public static function handle_newsletter_subscription( $provider, $contact, $lists, $result ) {
		if ( ! \is_wp_error( $result ) && $result ) {
			$subscription_event = [
				'type'    => 'subscription',
				'context' => $contact['email'],
				'value'   => [
					'esp'   => $provider,
					'lists' => $lists,
				],
			];


			$client_id = isset( $contact['client_id'] ) ? $contact['client_id'] : \Newspack_Popups_Segmentation::get_client_id();
			$nonce     = \wp_create_nonce( 'newspack_campaigns_lightweight_api' );
			$api       = \Campaign_Data_Utils::get_api( $nonce );

			if ( ! $api || ! $client_id ) {
				return;
			}

			$api->save_reader_events( $client_id, [ $subscription_event ] );
		}
	}

	/**
	 * When a reader provides an email address through the Newspack auth flow and
	 * the reader's newsletter subscription status is unknown, check the status
	 * of the email address with the ESP currently active in Newspack Newsletters.
	 *
	 * @param string|null $email_address Email address or null if not set.
	 *
	 * @return string Email address.
	 */
	public static function check_reader_newsletter_subscription_status( $email_address ) {
		if ( $email_address && class_exists( '\Newspack_Newsletters' ) && class_exists( '\Newspack_Newsletters_Subscription' ) ) {
			$client_id = Newspack_Popups_Segmentation::get_client_id();
			$nonce     = \wp_create_nonce( 'newspack_get_reader_events' );
			$api       = Campaign_Data_Utils::get_api( $nonce );

			if ( ! $api ) {
				return $email_address;
			}

			// If the reader is already known to be a newsletter subscriber, no need to proceed.
			$newsletter_events = $api->get_reader_events( $client_id, 'subscription', $email_address );
			if ( ! empty( $newsletter_events ) ) {
				return $email_address;
			}

			// Look up the email address as a contact with the connected ESP. If not a contact, no need to proceed.
			$subscribed_lists = \Newspack_Newsletters_Subscription::get_contact_lists( $email_address );
			if ( is_wp_error( $subscribed_lists ) || empty( $subscribed_lists ) || ! is_array( $subscribed_lists ) ) {
				return $email_address;
			}

			// The reader is subscribed to one or more lists, so they should be segmented as a subscriber.
			$provider           = \Newspack_Newsletters::get_service_provider();
			$subscription_event = [
				'type'    => 'subscription',
				'context' => $email_address,
				'value'   => [
					'esp'   => $provider->service,
					'lists' => $subscribed_lists,
				],
			];

			$client_id = \Newspack_Popups_Segmentation::get_client_id();
			$nonce     = \wp_create_nonce( 'newspack_campaigns_lightweight_api' );
			$api       = \Campaign_Data_Utils::get_api( $nonce );

			if ( ! $api || ! $client_id ) {
				return $email_address;
			}

			$api->save_reader_events( $client_id, [ $subscription_event ] );
		}

		return $email_address;
	}
}

Newspack_Popups_Newsletters::instance();
