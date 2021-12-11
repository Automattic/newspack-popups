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
	public function test_insertion_on_post() {
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
	 * Test popup insertion into a page.
	 */
	public function test_insertion_on_page() {
		self::renderPost( '', null, [], [], 'page' );
		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );

		self::assertEquals(
			0,
			$amp_layout_elements->length,
			'There are no popups, since the only one available is an inline one, and this is a page.'
		);
		self::assertContains(
			self::$raw_post_content,
			self::$post_content,
			'Includes the original post content.'
		);

		$overlay_content     = 'Hello, world';
		$overlay_id          = self::createPopup( $overlay_content, [ 'placement' => 'center' ] );
		$page_with_shortcode = '[newspack-popups id="' . $overlay_id . '"]';
		self::renderPost( '', $page_with_shortcode, [], [], 'page' );
		$overlay_text_content = self::$dom_xpath->query( '//amp-layout' )->item( 0 )->textContent;

		self::assertContains(
			$overlay_content,
			$overlay_text_content,
			'Inserts the popup content on a page, since this is an overlay prompt.'
		);
	}

	/**
	 * Shortcode handling.
	 */
	public function test_shortcode() {
		// Remove the default popup that would be programmatically inserted.
		wp_delete_post( self::$popup_id );

		$popup_content       = 'Hello, world';
		$popup_id            = self::createPopup( $popup_content );
		$post_with_shortcode = '[newspack-popups id="' . $popup_id . '"]';
		self::renderPost( '', $post_with_shortcode );
		$popup_text_content = self::$dom_xpath->query( '//amp-layout' )->item( 0 )->textContent;

		self::assertContains(
			$popup_content,
			$popup_text_content,
			'Shortcode inserts the popup content.'
		);
		$amp_access_config = self::getAMPAccessConfig();
		self::assertEquals(
			count( $amp_access_config['popups'] ),
			1,
			'AMP access has one popup in the config.'
		);
		self::assertEquals(
			$amp_access_config['popups'][0]->id,
			'id_' . $popup_id,
			'The popup id in the config matches the shortcoded popup id.'
		);
	}

	/**
	 * Shortcode along with programmatically placed popups handling.
	 */
	public function test_shortcode_and_programmatic() {
		$shortcode_popup_content = 'Hello, world';
		$shortcoded_popup_id     = self::createPopup( $shortcode_popup_content );

		self::renderPost( '', '[newspack-popups id="' . $shortcoded_popup_id . '"]' );

		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Post contains the programatically inserted popup content.'
		);
		self::assertContains(
			$shortcode_popup_content,
			self::$post_content,
			'Post contains the shortcode-inserted popup content.'
		);

		$amp_access_config = self::getAMPAccessConfig();
		$amp_access_ids    = array_map(
			function( $popup ) {
				return $popup->id;
			},
			$amp_access_config['popups']
		);
		self::assertEquals(
			count( $amp_access_config['popups'] ),
			2,
			'AMP access has both popups in the config.'
		);
		self::assertContains(
			'id_' . $shortcoded_popup_id,
			$amp_access_ids,
			'AMP access has correct popup id.'
		);
		self::assertContains(
			'id_' . self::$popup_id,
			$amp_access_ids,
			'AMP access has correct popup id.'
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
	 * Single popup preview.
	 */
	public function test_insertion_single_preview() {
		// Remove the default popup that would be programmatically inserted.
		wp_delete_post( self::$popup_id );

		$popup_content = 'Hello, world';
		$popup_id      = self::createPopup( $popup_content, [], [ 'post_status' => 'draft' ] );
		$preview_param = 'pid=' . $popup_id;

		self::renderPost( $preview_param );
		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );

		self::assertEquals(
			0,
			$amp_layout_elements->length,
			'There are no popups, the previewed popup should only be displayed if user is admin.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::renderPost( $preview_param );

		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );
		$popup_text_content  = $amp_layout_elements->item( 0 )->textContent;

		self::assertContains(
			$popup_content,
			$popup_text_content,
			'Includes the previewed popup content for a logged-in user.'
		);
	}

	/**
	 * As an admin.
	 */
	public function test_insertion_admin() {
		self::renderPost();
		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );
		self::assertContains(
			self::$popup_content,
			$amp_layout_elements->item( 0 )->textContent,
			'Includes the popup content.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::renderPost();
		$amp_layout_elements = self::$dom_xpath->query( '//amp-layout' );
		self::assertEquals(
			0,
			$amp_layout_elements->length,
			'Does not include popups when the page is loaded by an admin.'
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
	 * Test manual placement popups and single prompt block.
	 */
	public function test_prompt_block() {
		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'placement' => 'manual',
				'frequency' => 'always',
			]
		);

		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since it is a manual-only placement popup.'
		);

		self::renderPost( '', '<!-- wp:newspack-popups/single-prompt {"promptId":' . self::$popup_id . '} /-->' );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the prompt is placed in post content via the Single Prompt block.'
		);
	}

	/**
	 * Category criterion.
	 */
	public function test_criterion_category() {
		self::renderPost();
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does include the popup content if neither post nor popup have a category.'
		);

		$category_1_id = $this->factory->term->create(
			[
				'name'     => 'Events',
				'taxonomy' => 'category',
				'slug'     => 'events',
			]
		);

		self::renderPost( '', null, [ $category_1_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the popup does not have a category, but post has.'
		);

		wp_set_post_terms( self::$popup_id, [ $category_1_id ], 'category' );

		self::renderPost();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content when popup does have a category, but post does not.'
		);

		self::renderPost( '', null, [ $category_1_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Includes the popup content when the categories match.'
		);

		$category_2_id = $this->factory->term->create(
			[
				'name'     => 'Health',
				'taxonomy' => 'category',
				'slug'     => 'health',
			]
		);
		self::renderPost( '', null, [ $category_2_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content when popup and post have different categories.'
		);
	}

	/**
	 * Tag criterion.
	 */
	public function test_criterion_tag() {
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

	/**
	 * Ordering of amp-access array.
	 */
	public function test_amp_access_ordering() {
		$segments           = Newspack_Popups_Segmentation::create_segment(
			[
				'name'          => 'Some Segment',
				'configuration' => [],
			]
		);
		$segmented_popup_id = self::createPopup();
		Newspack_Popups_Model::set_popup_options(
			$segmented_popup_id,
			[
				'selected_segment_id' => $segments[0]['id'],
			]
		);
		sleep( 1 ); // Ensure the another popup has the most newest date.
		$another_popup_id = self::createPopup();

		self::renderPost();
		$amp_access_config = self::getAMPAccessConfig();
		$popup_ids_ordered = array_map(
			function( $item ) {
				return $item->id;
			},
			$amp_access_config['popups']
		);
		self::assertEquals(
			$popup_ids_ordered,
			[ 'id_' . $segmented_popup_id, 'id_' . self::$popup_id, 'id_' . $another_popup_id ],
			'The popup with the segment comes first in the array.'
		);
	}

	/**
	 * Test amp-access when previewing.
	 */
	public function test_amp_access_when_previewing() {
		self::remove_all_popups();

		$published_popup_id = self::createPopup();
		$draft_popup_id     = self::createPopup( 'rafraf', null, [ 'post_status' => 'draft' ] );

		self::renderPost();
		$amp_access_config    = self::getAMPAccessConfig();
		$popups_in_amp_access = $amp_access_config['popups'];
		self::assertEquals(
			count( $popups_in_amp_access ),
			1,
			'Just one popup in amp-access'
		);
		self::assertEquals(
			$popups_in_amp_access[0]->id,
			'id_' . $published_popup_id,
			'And it is the published one.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::renderPost( 'view_as=all' );
		$amp_access_config    = self::getAMPAccessConfig();
		$popups_in_amp_access = $amp_access_config['popups'];
		self::assertEquals(
			count( $popups_in_amp_access ),
			2,
			'Two popups are in amp-access'
		);
		self::assertEquals(
			$popups_in_amp_access[1]->id,
			'id_' . $draft_popup_id,
			'The second one is the draft popup.'
		);
	}

	/**
	 * Account related page handling.
	 */
	public function test_account_related_posts() {
		$woo_commerce_account_shortcode = 'woocommerce_my_account';
		$post_with_account_details      = "<!-- wp:shortcode -->[$woo_commerce_account_shortcode]<!-- /wp:shortcode -->";

		// Register WooCommerce shortcode.
		add_shortcode(
			$woo_commerce_account_shortcode,
			function() use ( $post_with_account_details ) {
				return $post_with_account_details;
			}
		);
		self::renderPost( '', $post_with_account_details );

		self::assertFalse( strpos( self::$post_content, self::$popup_content ), 'Popup content not rendered in account-related posts.' );
	}

	/**
	 * Test categories exclusion.
	 */
	public function test_categories_exclusion() {
		$category_to_exclude_id = $this->factory->term->create(
			[
				'name'     => 'Sport',
				'taxonomy' => 'category',
				'slug'     => 'sport',
			]
		);

		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'excluded_categories' => [ $category_to_exclude_id ],
			]
		);

		self::renderPost( '', null, [ $category_to_exclude_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since the post category is excluded on this popup.'
		);
	}

	/**
	 * Test categories exclusion has priority over inclusion.
	 */
	public function test_categories_exclusion_priority_over_inclusion() {
		$category_to_exclude_id = $this->factory->term->create(
			[
				'name'     => 'Arts',
				'taxonomy' => 'category',
				'slug'     => 'arts',
			]
		);

		wp_set_post_terms( self::$popup_id, [ $category_to_exclude_id ], 'category' );

		self::renderPost( '', null, [ $category_to_exclude_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does contain the popup content, since both post and popup have the same category.'
		);

		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'excluded_categories' => [ $category_to_exclude_id ],
			]
		);

		self::renderPost( '', null, [ $category_to_exclude_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since the post category is excluded on this popup.'
		);
	}

	/**
	 * Test tags exclusion.
	 */
	public function test_tags_exclusion() {
		$tag_to_exclude_id = $this->factory->term->create(
			[
				'name'     => 'No Prompt',
				'taxonomy' => 'post_tag',
				'slug'     => 'no-prompt',
			]
		);

		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'excluded_tags' => [ $tag_to_exclude_id ],
			]
		);

		self::renderPost( '', null, [], [ $tag_to_exclude_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since the post tag is excluded on this popup.'
		);
	}

	/**
	 * Test tags exclusion has priority over inclusion.
	 */
	public function test_tags_exclusion_priority_over_inclusion() {
		$tag_to_exclude_id = $this->factory->term->create(
			[
				'name'     => 'Excluded Tag',
				'taxonomy' => 'post_tag',
				'slug'     => 'excluded-tag',
			]
		);

		wp_set_post_terms( self::$popup_id, [ $tag_to_exclude_id ], 'tag' );

		self::renderPost( '', null, [], [ $tag_to_exclude_id ] );
		self::assertContains(
			self::$popup_content,
			self::$post_content,
			'Does contain the popup content, since both post and popup have the same tag.'
		);

		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'excluded_tags' => [ $tag_to_exclude_id ],
			]
		);

		self::renderPost( '', null, [], [ $tag_to_exclude_id ] );
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since the post tag is excluded on this popup.'
		);
	}
}
