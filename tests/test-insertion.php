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

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
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
		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'frequency'               => 'once',
				'trigger_type'            => 'scroll', 
				'trigger_scroll_progress' => 0,
			] 
		);
		// Reset internal duplicate-prevention.
		Newspack_Popups_Inserter::$the_content_has_rendered = false;
	}

	/**
	 * Test popup insertion into a post.
	 */
	public function test_insertion() {
		self::go_to( get_permalink( self::$post_id ) );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		$post_content = apply_filters( 'the_content', get_post( self::$post_id )->post_content );

		$dom = new DomDocument();
		@$dom->loadHTML( $post_content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
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
			$post_content,
			'Includes the original post content.'
		);
	}

	/**
	 * Test non-interactive setting for overlay campaigns.
	 */
	public function test_non_interactive_overlay() {
		update_option( 'newspack_newsletters_non_interative_mode', true );

		self::go_to( get_permalink( self::$post_id ) );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		$post_content = apply_filters( 'the_content', get_post( self::$post_id )->post_content );

		self::assertNotContains(
			self::$popup_content,
			$post_content,
			'Does not include the popup content, since it is an overlay campaign.'
		);
	}

	/**
	 * Test non-interactive setting for inline campaigns.
	 */
	public function test_non_interactive_inline() {
		update_option( 'newspack_newsletters_non_interative_mode', true );

		Newspack_Popups_Model::set_popup_options( self::$popup_id, [ 'placement' => 'inline' ] );

		self::go_to( get_permalink( self::$post_id ) );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		$post_content = apply_filters( 'the_content', get_post( self::$post_id )->post_content );

		self::assertContains(
			self::$popup_content,
			$post_content,
			'Does include the popup content.'
		);
		self::assertNotContains(
			Newspack_Popups::get_default_dismiss_text(),
			$post_content,
			'Does not include the dismissal text.'
		);
	}
}
