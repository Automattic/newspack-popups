<?php
/**
 * Newspack Campaigns API utils.
 *
 * @package Newspack
 */

/**
 * Utils.
 */
class Campaign_Data_Utils {
	/**
	 * Is the URL from a newsletter?
	 *
	 * @param string $url A URL.
	 */
	public static function is_url_from_email( $url ) {
		return stripos( $url, 'utm_medium=email' );
	}

	/**
	 * Is client a donor?
	 *
	 * @param object $client_data Client data.
	 */
	public static function is_donor( $client_data ) {
		return ! empty( $client_data['donations'] );
	}

	/**
	 * Is client a subscriber?
	 *
	 * @param object $client_data Client data.
	 * @param string $url URL.
	 */
	public static function is_subscriber( $client_data, $url ) {
		// If coming from email, assume it's a subscriber.
		return ! empty( $client_data['email_subscriptions'] ) || self::is_url_from_email( $url );
	}
}
