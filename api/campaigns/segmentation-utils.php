<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing

/**
 * Newspack Campaigns lightweight API Segmentation utils.
 *
 * @package Newspack
 */

/**
 * Given a segment and client data, decide if the campaign should be shown.
 *
 * @param object $campaign_segment Segment data.
 * @param object $client_data Client data.
 * @param bool   $has_utm_medium_in_url If there is UTM medium param in the URL.
 * @return bool Whether the campaign should be shown.
 */
function newspack_segmentation_should_display_campaign( $campaign_segment, $client_data, $has_utm_medium_in_url = false ) {
	$should_display   = true;
	$posts_read_count = count( $client_data['posts_read'] );
	// If coming from email, assume it's a subscriber.
	$is_subscriber = ! empty( $client_data['email_subscriptions'] ) || $has_utm_medium_in_url;
	$is_donor      = ! empty( $client_data['donations'] );

	if ( $campaign_segment->min_posts > 0 && $campaign_segment->min_posts > $posts_read_count ) {
		$should_display = false;
	}
	if ( $campaign_segment->max_posts > 0 && $campaign_segment->max_posts < $posts_read_count ) {
		$should_display = false;
	}
	if ( $campaign_segment->is_subscribed && ! $is_subscriber ) {
		$should_display = false;
	}
	if ( $campaign_segment->is_not_subscribed && $is_subscriber ) {
		$should_display = false;
	}
	if ( $campaign_segment->is_donor && ! $is_donor ) {
		$should_display = false;
	}
	if ( $campaign_segment->is_not_donor && $is_donor ) {
		$should_display = false;
	}
	return $should_display;
}
