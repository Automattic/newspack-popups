<?php
/**
 * Newspack Popups Donations
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/../api/segmentation/class-segmentation.php';

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
		add_action( 'admin_init', [ __CLASS__, 'delete_legacy_wc_webhook' ] );
		add_filter( 'woocommerce_billing_fields', [ __CLASS__, 'woocommerce_billing_fields' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ __CLASS__, 'woocommerce_checkout_update_order_meta' ] );
		add_action( 'woocommerce_checkout_order_created', [ __CLASS__, 'create_donation_event' ] );
	}

	/**
	 * Delete legacy WooCommerce webhooks, so we don't log duplicate donations.
	 * Previously, donation events were logged as a webhook callback, but this is
	 * no longer needed with the woocommerce_checkout_order_created action.
	 */
	public static function delete_legacy_wc_webhook() {
		if ( ! class_exists( 'WC_Webhook' ) || ! class_exists( 'WC_Data_Store' ) ) {
			return;
		}

		$webhook_endpoint     = 'newspack-popups/v1/woocommerce-sync';
		$webhook_delivery_url = get_rest_url( null, $webhook_endpoint );

		// Find the webhook with matching endpoint. If the delivery URL is not as expected,
		// update the webhook. This might happen after a site is migrated.
		// Otherwise, if such webhook is not found, create it.
		$data_store            = WC_Data_Store::load( 'webhook' );
		$matching_webhooks     = array_values(
			array_filter(
				$data_store->search_webhooks(),
				function( $webhook_id ) use ( $webhook_endpoint ) {
					$webhook = wc_get_webhook( $webhook_id );
					return false !== stripos( $webhook->get_delivery_url(), $webhook_endpoint );
				}
			)
		);
		$should_upsert_webhook = false;
		if ( 0 !== count( $matching_webhooks ) ) {
			$webhook = wc_get_webhook( $matching_webhooks[0] );
			$webhook->delete( true );
		}
	}

	/**
	 * Update WC order with the client id from hidden form field.
	 *
	 * @param String $order_id WC order id.
	 */
	public static function woocommerce_checkout_update_order_meta( $order_id ) {
		if ( ! empty( $_POST[ Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $order_id, Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME, sanitize_text_field( $_POST[ Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Add a hidden billing field with client id.
	 *
	 * @param Array $form_fields WC form fields.
	 */
	public static function woocommerce_billing_fields( $form_fields ) {
		$client_id = Newspack_Popups_Segmentation::get_client_id();

		if ( $client_id ) {
			$form_fields[ Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME ] = [
				'type'    => 'text',
				'default' => $client_id,
				'class'   => [ 'hide' ],
			];
		}

		return $form_fields;
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
			(int) get_option( \Newspack\Donations::DONATION_PRODUCT_ID_OPTION, 0 ) :
			0;
		$newspack_donation_product    = $newspack_donation_product_id ? wc_get_product( $newspack_donation_product_id ) : null;
		$newspack_child_products      = $newspack_donation_product ? $newspack_donation_product->get_children() : [];

		/**
		 * Allows other plugins to designate additional WooCommerce products by ID that should be considered donations.
		 *
		 * @param int[] $product_ids Array of WooCommerce product IDs.
		 */
		$other_donation_products = apply_filters( 'newspack_popups_donation_products', [] );
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

	/**
	 * When a new WooCommerce order is created with the client ID meta,
	 * log a new donation event to that client ID.
	 *
	 * @param WC_Order $order Order object.
	 */
	public static function create_donation_event( $order ) {
		$order_id  = $order->get_id();
		$client_id = get_post_meta( $order_id, Newspack_Popups_Segmentation::NEWSPACK_SEGMENTATION_CID_NAME, true );

		if ( $client_id ) {
			$orders_data     = self::get_wc_orders_data( [ $order ] );
			$donation_events = [];

			foreach ( $orders_data as $order_data ) {
				$donation_events[] = [
					'type'    => 'donation',
					'context' => 'woocommerce',
					'value'   => $order_data,
				];
			}

			if ( 0 < count( $donation_events ) ) {
				Newspack_Popups_Segmentation::update_client_data(
					$client_id,
					[
						'reader_events' => $donation_events,
					]
				);
			}
		}
	}
}
Newspack_Popups_Donations::instance();
