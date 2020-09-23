<?php
/**
 * Class Model Test
 *
 * @package Newspack_Popups
 */

/**
 * Model test case.
 */
class ModelTest extends WP_UnitTestCase {
	private static $popup_id = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
				'post_title'   => 'Platea fames',
				'post_content' => 'Faucibus placerat senectus.',
			]
		);
	}

	/**
	 * Test popup object creation.
	 */
	public function test_popup_object_creation() {
		$popup_object_default = Newspack_Popups_Model::create_popup_object( get_post( self::$popup_id ) );
		self::assertEquals(
			$popup_object_default['options'],
			[
				'background_color'        => '#FFFFFF',
				'display_title'           => false,
				'dismiss_text'            => Newspack_Popups::get_default_dismiss_text(),
				'frequency'               => 'once',
				'overlay_color'           => '#000000',
				'overlay_opacity'         => '30',
				'placement'               => 'center',
				'trigger_type'            => 'time',
				'trigger_delay'           => '3',
				'trigger_scroll_progress' => 0,
				'utm_suppression'         => null,
			],
			'Default options are as expected.'
		);

		$popup_object = Newspack_Popups_Model::create_popup_object(
			get_post( self::$popup_id ),
			false,
			[
				'trigger_type'            => 'scroll',
				'trigger_scroll_progress' => '42',
			]
		);
		self::assertEquals(
			$popup_object['options']['trigger_scroll_progress'],
			'42',
			'Sets options when passed as argument.'
		);
	}

	/**
	 * Test popup markup generation.
	 */
	public function test_markup_generation() {
		$popup_object_default = Newspack_Popups_Model::create_popup_object( get_post( self::$popup_id ) );

		$dom = new DomDocument();
		@$dom->loadHTML( Newspack_Popups_Model::generate_popup( $popup_object_default ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new DOMXpath( $dom );

		self::assertContains(
			'top: 0%',
			$xpath->query( '//*[@id="page-position-marker"]' )->item( 0 )->getAttribute( 'style' ),
			'The position marker is set at 0% by default.'
		);

		$popup_object = Newspack_Popups_Model::create_popup_object(
			get_post( self::$popup_id ),
			false,
			[
				'trigger_type'            => 'scroll',
				'trigger_scroll_progress' => 42,
			]
		);

		$dom = new DomDocument();
		@$dom->loadHTML( Newspack_Popups_Model::generate_popup( $popup_object ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath = new DOMXpath( $dom );

		self::assertContains(
			'top: 42%',
			$xpath->query( '//*[@id="page-position-marker"]' )->item( 0 )->getAttribute( 'style' ),
			'The position marker is set at position passed in options.'
		);
	}
}
