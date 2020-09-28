<?php
/**
 * Class Insertion Test
 *
 * @package Newspack_Popups
 */

/**
 * Insertion test case.
 */
class InsertionTest extends WP_UnitTestCase {
	private static $post_id       = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $popup_content = 'Faucibus placerat senectus metus molestie varius tincidunt.'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $popup_id      = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$post_id  = self::factory()->post->create(
			[
				'post_content' => 'Elit platea a convallis dolor id mollis ultricies sociosqu dapibus.',
			]
		);
		self::$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
				'post_title'   => 'Platea fames',
				'post_content' => self::$popup_content,
			]
		);
		// Set the popup as sitewide default.
		Newspack_Popups_Model::set_sitewide_popup( self::$popup_id );
		// Set popup frequency from default 'test'.
		Newspack_Popups_Model::set_popup_options( self::$popup_id, [ 'frequency' => 'once' ] );
	}

	/**
	 * Test default options.
	 */
	public function test_default_options() {
		$popup_object = Newspack_Popups_Model::create_popup_object( get_post( self::$popup_id ) );
		self::assertEquals(
			$popup_object['options'],
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
	}

	/**
	 * Test popup insertion into a post.
	 */
	public function test_insertion() {
		self::go_to( get_permalink( self::$post_id ) );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		$post_content       = get_post( self::$post_id )->post_content;
		$content_with_popup = Newspack_Popups_Inserter::insert_popups_in_content( $post_content, false );

		$dom = new DomDocument();
		@$dom->loadHTML( $content_with_popup ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$xpath               = new DOMXpath( $dom );
		$amp_layout_elements = $xpath->query( '//amp-layout' );
		$popup_text_content  = $amp_layout_elements->item( 0 )->textContent;

		self::assertContains(
			self::$popup_content,
			$popup_text_content,
			'Includes the popup content.'
		);
		self::assertContains(
			Newspack_Popups::get_default_dismiss_text(),
			$popup_text_content,
			'Includes the dismissal text.'
		);
		self::assertContains(
			$post_content,
			$content_with_popup,
			'Includes the original post content.'
		);
	}
}
