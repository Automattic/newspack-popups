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
	$content = '<div class="newspack-popups__custom-placement">Custom Placement!!</div>';
	return $content;
}

register_block();
