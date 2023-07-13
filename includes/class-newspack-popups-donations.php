<?php
/**
 * Newspack Popups Donations
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack Popups Donations Class.
 */
final class Newspack_Popups_Donations {
	/**
	 * The single instance of the class.
	 *
	 * @var Newspack_Popups_Donations
	 */
	protected static $instance = null;

	/**
	 * Main Newspack Popups Donations Instance.
	 * Ensures only one instance of Newspack Popups Donations Instance is loaded or can be loaded.
	 *
	 * @return Newspack Popups Donations Instance - Main instance.
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
		\add_action( 'admin_init', [ __CLASS__, 'delete_legacy_wc_webhook' ] );
		\add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'create_donation_event_woocommerce' ] );
		\add_action( 'newspack_new_donation_woocommerce', [ __CLASS__, 'create_donation_event_woocommerce' ], 10, 2 );
		\add_action( 'newspack_stripe_new_donation', [ __CLASS__, 'create_donation_event_stripe' ], 10, 3 );
		\add_action( 'newspack_stripe_donation_cancellation', [ __CLASS__, 'create_donation_cancellation_event_stripe' ], 10, 2 );
	}

	/**
	 * Delete legacy WooCommerce webhooks, so we don't log duplicate donations.
	 * Previously, donation events were logged as a webhook callback, but this is
	 * no longer needed with the woocommerce_checkout_order_created action.
	 */
	public static function delete_legacy_wc_webhook() {
		if ( ! class_exists( '\WC_Webhook' ) || ! class_exists( '\WC_Data_Store' ) ) {
			return;
		}

		$webhook_endpoint     = 'newspack-popups/v1/woocommerce-sync';
		$webhook_delivery_url = \get_rest_url( null, $webhook_endpoint );

		// Find the webhook with matching endpoint. If the delivery URL is not as expected,
		// update the webhook. This might happen after a site is migrated.
		// Otherwise, if such webhook is not found, create it.
		$data_store            = \WC_Data_Store::load( 'webhook' );
		$matching_webhooks     = array_values(
			array_filter(
				$data_store->search_webhooks(),
				function( $webhook_id ) use ( $webhook_endpoint ) {
					$webhook = \wc_get_webhook( $webhook_id );
					return false !== stripos( $webhook->get_delivery_url(), $webhook_endpoint );
				}
			)
		);
		$should_upsert_webhook = false;
		if ( 0 !== count( $matching_webhooks ) ) {
			$webhook = \wc_get_webhook( $matching_webhooks[0] );
			$webhook->delete( true );
		}
	}

	/**
	 * Given an array of WC orders, get relevant data to log with reader events.
	 * Only return data for orders that match Newspack donation products.
	 *
	 * @param array $orders Array of orders.
	 *
	 * @return array Formatted order data to save as reader event values.
	 */
	public static function get_wc_orders_data( $orders ) {
		$newspack_donation_product_id = class_exists( '\Newspack\Donations' ) ?
			(int) \get_option( \Newspack\Donations::DONATION_PRODUCT_ID_OPTION, 0 ) :
			0;
		$newspack_donation_product    = $newspack_donation_product_id ? \wc_get_product( $newspack_donation_product_id ) : null;
		$newspack_child_products      = $newspack_donation_product ? $newspack_donation_product->get_children() : [];

		/**
		 * Allows other plugins to designate additional WooCommerce products by ID that should be considered donations.
		 *
		 * @param int[] $product_ids Array of WooCommerce product IDs.
		 */
		$other_donation_products = \apply_filters( 'newspack_popups_donation_products', [] );
		$all_donation_products   = array_values( array_merge( $newspack_child_products, $other_donation_products ) );
		$orders_data             = [];

		foreach ( $orders as $order ) {
			$order_data  = $order->get_data();
			$order_items = array_map(
				function( $item ) {
					return $item->get_product_id();
				},
				array_values( $order->get_items() )
			);

			// Only count orders that include donation products as donations.
			if ( 0 < count( array_intersect( $order_items, $all_donation_products ) ) ) {
				$orders_data[] = [
					'order_id' => $order_data['id'],
					'date'     => date_format( date_create( $order_data['date_created'] ), 'Y-m-d' ),
					'amount'   => $order_data['total'],
				];
			}
		}

		return $orders_data;
	}
}
Newspack_Popups_Donations::instance();
