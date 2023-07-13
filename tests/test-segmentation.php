<?php
/**
 * Class Segmentation Test
 *
 * @package Newspack_Popups
 */

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

/**
 * Segmentation test case.
 */
class SegmentationTest extends WP_UnitTestCase {

	use ArraySubsetAsserts;

	private static $post_read_payload = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	private static $request = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public function set_up() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		global $wpdb;
		self::$request = [
			'popups'   => wp_json_encode( [] ),
			'settings' => wp_json_encode( [] ),
			'visit'    => wp_json_encode(
				[
					'post_id'    => self::$post_read_payload['value']['post_id'],
					'post_type'  => 'post',
					'categories' => self::$post_read_payload['value']['categories'],
					'date'       => gmdate( 'Y-m-d', time() ),
				]
			),
		];
	}

	/**
	 * Parse a "view as" spec.
	 */
	public function test_parse_view_as() {
		self::assertEquals(
			Newspack_Popups_View_As::parse_view_as( 'groups:one,two;segment:123' ),
			[
				'groups'  => 'one,two',
				'segment' => '123',
			],
			'Spec is parsed.'
		);

		self::assertEquals(
			Newspack_Popups_View_As::parse_view_as( 'all' ),
			[
				'all' => true,
			],
			'Spec is parsed with the "all" value'
		);

		self::assertEquals(
			Newspack_Popups_View_As::parse_view_as( '' ),
			[],
			'Empty array is returned if there is no spec.'
		);
	}
}
