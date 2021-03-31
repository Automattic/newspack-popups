<?php
/**
 * Class for test
 *
 * @package Newspack_Popups
 */

/**
 * WP_UnitTestCase which renders a page with popups.
 */
class WP_UnitTestCase_PageWithPopups extends WP_UnitTestCase {
	protected static $post_id             = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $popup_content       = 'The popup content.'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $popup_id            = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $raw_post_content    = 'The post content.'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $post_content        = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $post_head           = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $dom_xpath           = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	protected static $post_head_dom_xpath = false; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

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
	 * @param string      $url_query Query to append to URL.
	 * @param null|string $content Raw string to render as post content.
	 */
	protected function renderPost( $url_query = '', $content = null ) {
		// Navigate to post.
		self::go_to( get_permalink( self::$post_id ) . '&' . $url_query );
		global $wp_query, $post;
		$wp_query->in_the_loop = true;
		setup_postdata( $post );

		// Reset internal duplicate-prevention.
		Newspack_Popups_Inserter::$the_content_has_rendered = false;

		if ( ! $content ) {
			$content = get_post( self::$post_id )->post_content;
		}

		// Save post content.
		self::$post_content = apply_filters( 'the_content', $content );
		$dom                = new DomDocument();
		@$dom->loadHTML( self::$post_content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		self::$dom_xpath = new DOMXpath( $dom );

		// Save page head.
		ob_start();
		wp_head();
		self::$post_head = ob_get_clean();
		$post_head_dom   = new DomDocument();
		@$post_head_dom->loadHTML( self::$post_head ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		self::$post_head_dom_xpath = new DOMXpath( $post_head_dom );
	}

	/**
	 * Get the amp-access query.
	 *
	 * @return object amp-access query.
	 */
	protected function getAMPAccessQuery() {
		$amp_access_content = json_decode( self::$post_head_dom_xpath->query( '//*[@id="amp-access"]' )->item( 0 )->textContent );
		parse_str( wp_parse_url( $amp_access_content->authorization )['query'], $amp_access_query );
		$amp_access_query['popups']   = json_decode( $amp_access_query['popups'] );
		$amp_access_query['settings'] = json_decode( $amp_access_query['settings'] );
		$amp_access_query['visit']    = json_decode( $amp_access_query['visit'] );
		return $amp_access_query;
	}
}
