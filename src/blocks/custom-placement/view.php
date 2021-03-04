<?php
/**
 * Front-end render functions for the Custom Placement block.
 *
 * @package Newspack_Popups
 */

namespace Newspack_Popups\Custom_Placement_Block;

/**
 * Dynamic block registration.
 */
function register_block() {
	// Listings block attributes.
	$block_json = json_decode(
		file_get_contents( __DIR__ . '/block.json' ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		true
	);

	register_block_type(
		$block_json['name'],
		[
			'attributes'      => $block_json['attributes'],
			'render_callback' => __NAMESPACE__ . '\render_block',
		]
	);
}

/**
 * Block render callback.
 *
 * @param array $attributes Block attributes.
 */
function render_block( $attributes ) {
	$content             = '';
	$custom_placement_id = \Newspack_Popups_Custom_Placements::validate_custom_placement_id( $attributes['customPlacement'] );

	if ( empty( $custom_placement_id ) ) {
		return $content;
	}

	$post_categories = array_map(
		function( $term ) {
			return $term->term_id;
		},
		get_the_category()
	);

	// Get category-matching prompts for the custom placement.
	$prompts = \Newspack_Popups_Custom_Placements::get_prompts_for_custom_placement( [ $custom_placement_id ], 'ids', $post_categories );

	// If no category-matching prompts, get uncategorized prompts.
	if ( empty( $prompts ) ) {
		$prompts = \Newspack_Popups_Custom_Placements::get_prompts_for_custom_placement( [ $custom_placement_id ], 'ids', [] );
	}

	if ( ! empty( $prompts ) ) {
		$content .= '<!-- start custom placement: ' . $custom_placement_id . '-->';
		$segments = [];
		foreach ( $prompts as $prompt_id ) {
			$segment_ids = explode( ',', get_post_meta( $prompt_id, 'selected_segment_id', true ) );

			// Only render one prompt per segment and category for each custom placement.
			if ( 0 === count( array_intersect( $segments, $segment_ids ) ) ) {
				$segments = array_unique( array_merge( $segments, $segment_ids ) );
				$content .= '<!-- wp:shortcode -->[newspack-popup id="' . $prompt_id . '"]<!-- /wp:shortcode -->';
			}
		}

		$content .= '<!-- end custom placement: ' . $custom_placement_id . '-->';
	}

	return $content;
}

register_block();
