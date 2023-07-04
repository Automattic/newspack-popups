<?php
/**
 * Register default segmentation criteria.
 *
 * @package Newspack
 */

namespace Newspack_Popups;

defined( 'ABSPATH' ) || exit;

$criteria = [
	/**
	 * Reader Engagement.
	 */
	'articles_read'            => [
		'name'              => __( 'Articles read', 'newspack-popups' ),
		'description'       => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
		'category'          => 'reader_engagement',
		'matching_function' => 'range',
	],
	'articles_read_in_session' => [
		'name'              => __( 'Articles read in session', 'newspack-popups' ),
		'description'       => __( 'Number of articles read in the last 30 day period.', 'newspack-popups' ),
		'category'          => 'reader_engagement',
		'matching_function' => 'range',
	],
	'favorite_categories'      => [
		'name'              => __( 'Favorite Categories', 'newspack-popups' ),
		'description'       => __( 'Most read categories of reader.', 'newspack-popups' ),
		'category'          => 'reader_engagement',
		'matching_function' => 'list__in',
	],
	/**
	 * Reader Activity.
	 */
	'newsletter'               => [
		'name'     => __( 'Newsletter', 'newspack-popups' ),
		'category' => 'reader_activity',
		'options'  => [
			[
				'name'  => __( 'Subscribers and non-subscribers', 'newspack-popups' ),
				'value' => 0,
			],
			[
				'name'  => __( 'Subscribers', 'newspack-popups' ),
				'value' => 1,
			],
			[
				'name'  => __( 'Non-subscribers', 'newspack-popups' ),
				'value' => 2,
			],
		],
	],
	'donation'                 => [
		'name'        => __( 'Donation', 'newspack-popups' ),
		'description' => __( '(if checkout happens on-site)', 'newspack-popups' ),
		'category'    => 'reader_activity',
		'options'     => [
			[
				'name'  => __( 'Donors and non-donors', 'newspack-popups' ),
				'value' => 0,
			],
			[
				'name'  => __( 'Donors', 'newspack-popups' ),
				'value' => 1,
			],
			[
				'name'  => __( 'Non-donors', 'newspack-popups' ),
				'value' => 2,
			],
			[
				'name'  => __( 'Former donors (who cancelled a recurring donation)', 'newspack-popups' ),
				'value' => 3,
			],
		],
	],
	'user_account'             => [
		'name'     => __( 'User Account', 'newspack-popups' ),
		'category' => 'reader_activity',
		'options'  => [
			[
				'name'  => __( 'All users', 'newspack-popups' ),
				'value' => 0,
			],
			[
				'name'  => __( 'Has user account', 'newspack-popups' ),
				'value' => 1,
			],
			[
				'name'  => __( 'Does not have user account', 'newspack-popups' ),
				'value' => 2,
			],
		],
	],
	/**
	 * Referrer Sources.
	 */
	'sources_to_match'         => [
		'name'              => __( 'Sources to match', 'newspack-popups' ),
		'description'       => __( 'Segment based on traffic source', 'newspack-popups' ),
		'help'              => __( 'A comma-separated list of domains.', 'newspack-popups' ),
		'placeholder'       => 'google.com, facebook.com',
		'category'          => 'referrer_sources',
		'matching_function' => 'list__in',
	],
	'sources_to_exclude'       => [
		'name'              => __( 'Sources to exclude', 'newspack-popups' ),
		'description'       => __( 'Segment based on traffic source - hide campaigns for visitors coming from specific sources.', 'newspack-popups' ),
		'help'              => __( 'A comma-separated list of domains.', 'newspack-popups' ),
		'placeholder'       => 'twitter.com, instagram.com',
		'category'          => 'referrer_sources',
		'matching_function' => 'list__not_in',
	],
];

foreach ( $criteria as $criteria_id => $config ) {
	\Newspack_Popups_Criteria::register_criteria( $criteria_id, $config );
}
