<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class Insertion Test
 *
 * @package Newspack_Popups
 */

/**
 * Insertion test case.
 */
class InsertionTest extends WP_UnitTestCase_PageWithPopups {
	/**
	 * Test popup insertion into a post.
	 */
	public function test_insertion() {
		self::renderPost();
		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );
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
			self::$raw_post_content,
			self::$post_content,
			'Includes the original post content.'
		);
	}

	/**
	 * Tracking.
	 */
	public function test_insertion_analytics() {
		self::renderPost();
		$amp_analytics_elements = self::$dom_xpath->query( '//amp-analytics' );

		self::assertEquals(
			$amp_analytics_elements->length,
			1,
			'Includes tracking by default.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::renderPost( 'view_as=all' );
		self::assertEquals(
			self::$dom_xpath->query( '//amp-analytics' )->length,
			0,
			'Does not include tracking when a user is an admin.'
		);
	}

	/**
	 * With view-as feature.
	 */
	public function test_insertion_view_as() {
		self::renderPost( 'view_as=all' );
		self::assertEquals(
			self::$dom_xpath->query( '//amp-analytics' )->length,
			1,
			'Includes tracking with "view as", since there is no logged in user.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::renderPost( 'view_as=all' );
		self::assertEquals(
			self::$dom_xpath->query( '//amp-analytics' )->length,
			0,
			'Does not include tracking when a user is an admin.'
		);
	}

	/**
	 * Test non-interactive setting for overlay campaigns.
	 */
	public function test_non_interactive_overlay() {
		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'placement' => 'center',
				'frequency' => 'once',
			]
		);

		update_option( 'newspack_popups_non_interative_mode', true );
		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since it is an overlay campaign.'
		);
		update_option( 'newspack_popups_non_interative_mode', false );
	}

	/**
	 * Test non-interactive setting for inline campaigns.
	 */
	public function test_non_interactive_inline() {
		update_option( 'newspack_popups_non_interative_mode', true );
		self::renderPost();
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does include the popup content.'
		);
		self::assertNotContains(
			Newspack_Popups::get_default_dismiss_text(),
			self::$post_content,
			'Does not include the dismissal text.'
		);
		update_option( 'newspack_popups_non_interative_mode', false );
	}

	/**
	 * Test custom placement campaigns.
	 */
	public function test_custom_placement_prompt() {
		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'placement' => 'custom1',
				'frequency' => 'always',
			]
		);

		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since it is a custom placement campaign.'
		);

		self::renderPost( '', '<!-- wp:newspack-popups/custom-placement {"customPlacement":"custom1"} /-->' );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the custom placement is present in post content.'
		);
	}
}
