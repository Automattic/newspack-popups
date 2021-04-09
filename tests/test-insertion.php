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
	 * Shortcode handling.
	 */
	public function test_shortcode() {
		$post_with_shortcode = '[newspack-popups id="' . self::$popup_id . '"]';
		self::renderPost( '', $post_with_shortcode );
		$popup_text_content = self::$dom_xpath->query( '//amp-layout' )->item( 0 )->textContent;

		self::assertContains(
			self::$popup_content,
			$popup_text_content,
			'Shortcode inserts the popup content.'
		);
		$amp_access_query = self::getAMPAccessQuery();
		self::assertEquals(
			count( $amp_access_query['popups'] ),
			1,
			'AMP access has one popup in the query.'
		);
		self::assertEquals(
			$amp_access_query['popups'][0]->id,
			'id_' . self::$popup_id,
			'The popup id in the query matches the shortcoded popup id.'
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
	 * Test non-interactive setting for overlay popup.
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
			'Does not include the popup content, since it is an overlay popup.'
		);
		update_option( 'newspack_popups_non_interative_mode', false );
	}

	/**
	 * Test non-interactive setting for inline popups.
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
	 * Test custom placement popups.
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
			'Does not include the popup content, since it is a custom placement popup.'
		);

		self::renderPost( '', '<!-- wp:newspack-popups/custom-placement {"customPlacement":"custom1"} /-->' );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the custom placement is present in post content.'
		);
	}

	/**
	 * Category criterion.
	 */
	public function test_category_criterion() {
		self::renderPost();
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does include the popup content if neither post nor popup have a category.'
		);

		$category_id = $this->factory->term->create(
			[
				'name'     => 'Events',
				'taxonomy' => 'category',
				'slug'     => 'events',
			]
		);
		wp_set_post_terms( self::$popup_id, [ $category_id ], 'category' );

		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since the post category does not match.'
		);

		self::renderPost( '', null, [ $category_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the categories match.'
		);
	}

	/**
	 * Tag criterion.
	 */
	public function test_tag_criterion() {
		self::renderPost();
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does include the popup content if neither post nor popup have tags.'
		);

		$tag_1_id = $this->factory->term->create(
			[
				'name'     => 'Featured',
				'taxonomy' => 'post_tag',
				'slug'     => 'featured',
			]
		);
		self::renderPost( '', null, [], [ $tag_1_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when popup does not have tags, but post has.'
		);

		// Set tag on the popup.
		wp_set_post_terms( self::$popup_id, [ $tag_1_id ], 'post_tag' );

		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content when the post has no tags, but popup has.'
		);

		$tag_2_id = $this->factory->term->create(
			[
				'name'     => 'Events',
				'taxonomy' => 'post_tag',
				'slug'     => 'events',
			]
		);
		self::renderPost( '', null, [], [ $tag_2_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content when the post tag has a different tag than the popup.'
		);

		self::renderPost( '', null, [], [ $tag_1_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the tags match.'
		);
	}
}
