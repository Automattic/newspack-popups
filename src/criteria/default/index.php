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
		'description'       => __( 'Number of articles recently read before 30 minutes of inactivity.', 'newspack-popups' ),
		'category'          => 'reader_engagement',
		'matching_function' => 'range',
	],
	'favorite_categories'      => [
		'name'              => __( 'Favorite Categories', 'newspack-popups' ),
		'description'       => __( 'Most read categories of reader.', 'newspack-popups' ),
		'category'          => 'reader_engagement',
		'matching_function' => 'list__in',
	],
	'devices'                  => [
		'name'        => __( 'Devices', 'newspack-popups' ),
		'description' => __( 'The device the user is viewing the site on – e.g. mobile, tablet, etc.', 'newspack-popups' ),
		'category'    => 'reader_engagement',
		'options'     => [
			[
				'label'  => __( 'Desktop - 1280 px and above', 'newspack-popups' ),
				'value'  => 'Desktop',
				'params' => [
					'max_width' => PHP_INT_MAX,
					'min_width' => 1280,
				],
			],
			[
				'label'  => __( 'Laptop - between 1024 and 1280 px wide', 'newspack-popups' ),
				'value'  => 'Laptop',
				'params' => [
					'max_width' => 1280,
					'min_width' => 1024,
				],
			],
			[
				'label'  => __( 'Tablet - between 768 and 1024 px wide', 'newspack-popups' ),
				'value'  => 'Tablet',
				'params' => [
					'max_width' => 1024,
					'min_width' => 768,
				],
			],
			[
				'label'  => __( 'Mobile (large phones) - between 480 and 786 px wide', 'newspack-popups' ),
				'value'  => 'Mobile',
				'params' => [
					'max_width' => 768,
					'min_width' => 480,
				],
			],
			[
				'label'  => __( 'Mobile (small phones) - 480 px and below wide', 'newspack-popups' ),
				'value'  => 'Mobile small',
				'params' => [
					'max_width' => 480,
					'min_width' => 0,
				],
			],
		],
	],
	/**
	 * Reader Activity.
	 */
	'newsletter'               => [
		'name'        => __( 'Newsletter', 'newspack-popups' ),
		'description' => __( 'Subscriber status based on any newsletter list.', 'newspack-popups' ),
		'category'    => 'newsletter',
		'options'     => [
			[
				'label' => __( 'Subscribers and non-subscribers', 'newspack-popups' ),
				'value' => '',
			],
			[
				'label' => __( 'Subscribers', 'newspack-popups' ),
				'value' => 'subscribers',
			],
			[
				'label' => __( 'Non-subscribers', 'newspack-popups' ),
				'value' => 'non-subscribers',
			],
		],
	],
	'subscribed_lists'         => [
		'name'               => __( 'Subscribed to newsletter lists', 'newspack-popups' ),
		'description'        => __( 'If the reader is subscribed to any of the selected newsletter lists.', 'newspack-popups' ),
		'category'           => 'newsletter',
		'matching_function'  => 'list__in',
		'matching_attribute' => 'newsletter_subscribed_lists',
	],
	'not_subscribed_lists'     => [
		'name'               => __( 'Not subscribed to newsletter lists', 'newspack-popups' ),
		'description'        => __( 'If the reader is NOT subscribed to any of the selected newsletter lists.', 'newspack-popups' ),
		'category'           => 'newsletter',
		'matching_function'  => 'list__not_in',
		'matching_attribute' => 'newsletter_subscribed_lists',
	],
	/**
	 * Referrer Sources.
	 */
	'sources_to_match'         => [
		'name'               => __( 'Sources to match', 'newspack-popups' ),
		'description'        => __( 'Segment based on traffic source', 'newspack-popups' ),
		'help'               => __( 'A comma-separated list of domains.', 'newspack-popups' ),
		'placeholder'        => 'google.com, facebook.com',
		'category'           => 'referrer_sources',
		'matching_function'  => 'list__in',
		'matching_attribute' => 'referrer',
	],
	'sources_to_exclude'       => [
		'name'               => __( 'Sources to exclude', 'newspack-popups' ),
		'description'        => __( 'Segment based on traffic source - hide campaigns for visitors coming from specific sources.', 'newspack-popups' ),
		'help'               => __( 'A comma-separated list of domains.', 'newspack-popups' ),
		'placeholder'        => 'twitter.com, instagram.com',
		'category'           => 'referrer_sources',
		'matching_function'  => 'list__not_in',
		'matching_attribute' => 'referrer',
	],
];

// Register promoted fields from newspack-plugin as segmentation criteria.
if ( class_exists( '\Newspack\Reader_Activation\Promoted_Fields' ) ) {
	$promoted = \Newspack\Reader_Activation\Promoted_Fields::get_promoted_fields();
	foreach ( $promoted as $key => $field ) {
		if ( empty( $field['is_segment_criteria'] ) ) {
			continue;
		}
		$reader_data_key = $field['reader_data_key'] ?? $key;
		$criteria[ $key ] = [
			'name'               => $field['name'],
			'category'           => $field['category'] ?? 'reader_activity',
			'matching_function'  => $field['matching_function'] ?? 'default',
			'matching_attribute' => $reader_data_key,
			'options'            => $field['options'] ?? [],
			'description'        => $field['description'] ?? '',
		];
	}
}

/**
 * Filters the default criteria to be registered.
 *
 * @param array $criteria The default criteria config keyed by criteria ID.
 */
$criteria = apply_filters( 'newspack_popups_default_criteria', $criteria );

foreach ( $criteria as $criteria_id => $config ) {
	\Newspack_Popups_Criteria::register_criteria( $criteria_id, $config );
}
