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
	private static $post_id          = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $popup_content    = 'The popup content.'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $popup_id         = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $raw_post_content = 'The post content.'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $post_content     = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $dom_xpath        = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		// Remove any popups (from previous tests).
		foreach ( Newspack_Popups_Model::retrieve_popups() as $popup ) {
			wp_delete_post( $popup['id'] );
		}

		self::$post_id  = self::factory()->post->create(
			[
				'post_content' => self::$raw_post_content,
			]
		);
		self::$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_title'   => 'Popup title',
				'post_content' => self::$popup_content,
			]
		);

		Newspack_Popups_Model::set_popup_options(
			self::$popup_id,
			[
				'frequency'    => 'daily',
				'dismiss_text' => Newspack_Popups::get_default_dismiss_text(),
			]
		);
	}

	/**
	 * Trigger post rendering with popups in it.
	 *
	 * @param string $url_query Query to append to URL.
	 */
	public function render_post( $url_query = '' ) {
		// Navigate to post.
		self::go_to( get_permalink( self::$post_id ) . '&' . $url_query );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		// Reset internal duplicate-prevention.
		Newspack_Popups_Inserter::$the_content_has_rendered = false;

		self::$post_content = apply_filters( 'the_content', get_post( self::$post_id )->post_content );
		$dom                = new DomDocument();
		@$dom->loadHTML( self::$post_content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		self::$dom_xpath = new DOMXpath( $dom );
	}

	/**
	 * Test popup insertion into a post.
	 */
	public function test_insertion() {
		self::render_post();
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
		self::render_post();
		$amp_analytics_elements = self::$dom_xpath->query( '//amp-analytics' );

		self::assertEquals(
			$amp_analytics_elements->length,
			1,
			'Includes tracking by default.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::render_post( 'view_as=all' );
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
		self::render_post( 'view_as=all' );
		self::assertEquals(
			self::$dom_xpath->query( '//amp-analytics' )->length,
			1,
			'Includes tracking with "view as", since there is no logged in user.'
		);

		$user_id = $this->factory->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		self::render_post( 'view_as=all' );
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
		self::render_post();
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
		self::render_post();
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

		self::render_post();
		self::assertNotContains(
			self::$popup_content,
			self::$post_content,
			'Does not include the popup content, since it is a custom placement campaign.'
		);
	}
}
