<?php
/**
 * Newspack Popups Newsletters
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

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
		add_action( 'newspack_newsletters_add_contact', [ __CLASS__, 'handle_newsletter_subscription' ], 10, 4 );
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
		if ( ! is_wp_error( $result ) && $result ) {
			$subscription_event = [
				'type'    => 'subscription',
				'context' => $contact['email'],
				'value'   => [
					'esp'   => $provider,
					'lists' => $lists,
				],
			];

			Newspack_Popups_Segmentation::update_client_data(
				Newspack_Popups_Segmentation::get_client_id(),
				[
					'reader_events' => [ $subscription_event ],
				]
			);
		}
	}
}

Newspack_Popups_Newsletters::instance();
