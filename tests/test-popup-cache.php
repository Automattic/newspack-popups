<?php
/**
 * Class Popup Cache Test
 *
 * @package Newspack_Popups
 */

/**
 * Popup cache test case.
 */
class PopupCacheTest extends WP_UnitTestCase {

	/**
	 * Remove all popups between tests.
	 */
	public function set_up() {
		parent::set_up();
		foreach ( Newspack_Popups_Model::retrieve_popups( true ) as $popup ) {
			wp_delete_post( $popup['id'] );
		}
		Newspack_Popups_Model::clear_popup_cache();
	}

	/**
	 * Test that eligible popups are cached on second call.
	 */
	public function test_eligible_popups_are_cached() {
		self::factory()->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'publish',
			]
		);

		// First call populates cache.
		$result1 = Newspack_Popups_Model::retrieve_eligible_popups();

		// Verify cache is populated.
		$cached = wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP );
		self::assertNotFalse( $cached, 'Cache should be populated after first call.' );
		self::assertEquals( $result1, $cached, 'Cached value should match first call result.' );

		// Second call should return same result.
		$result2 = Newspack_Popups_Model::retrieve_eligible_popups();
		self::assertEquals( $result1, $result2, 'Second call should return cached result.' );
	}

	/**
	 * Test that cache is invalidated when a popup is saved.
	 */
	public function test_cache_invalidated_on_save() {
		$popup_id = self::factory()->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'publish',
			]
		);

		// Populate cache.
		Newspack_Popups_Model::retrieve_eligible_popups();
		self::assertNotFalse(
			wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP ),
			'Cache should be populated.'
		);

		// Trigger save.
		wp_update_post(
			[
				'ID'         => $popup_id,
				'post_title' => 'Updated',
			]
		);

		// Cache should be cleared.
		self::assertFalse(
			wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP ),
			'Cache should be cleared after popup save.'
		);
	}

	/**
	 * Test that cache is not used for unpublished or campaign-filtered queries.
	 */
	public function test_cache_bypassed_for_preview_queries() {
		self::factory()->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'publish',
			]
		);

		// Populate default cache.
		Newspack_Popups_Model::retrieve_eligible_popups();

		// Call with include_unpublished — should NOT use cache.
		Newspack_Popups_Model::retrieve_eligible_popups( true );

		// Default cache should still be intact (not overwritten).
		$cached = wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP );
		self::assertNotFalse( $cached, 'Default cache should not be affected by preview queries.' );
	}

	/**
	 * Test that cache is invalidated when popup meta is updated.
	 */
	public function test_cache_invalidated_on_meta_update() {
		$popup_id = self::factory()->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'publish',
			]
		);

		// Populate cache.
		Newspack_Popups_Model::retrieve_eligible_popups();

		// Update placement meta.
		update_post_meta( $popup_id, 'placement', 'center' );

		// Cache should be cleared.
		self::assertFalse(
			wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP ),
			'Cache should be cleared after meta update.'
		);
	}

	/**
	 * Test that cache is invalidated when terms are set on a popup.
	 */
	public function test_cache_invalidated_on_term_change() {
		$popup_id = self::factory()->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'publish',
			]
		);

		// Populate cache.
		Newspack_Popups_Model::retrieve_eligible_popups();

		// Assign a category.
		$cat_id = self::factory()->category->create();
		wp_set_object_terms( $popup_id, [ $cat_id ], 'category' );

		// Cache should be cleared.
		self::assertFalse(
			wp_cache_get( Newspack_Popups_Model::CACHE_KEY_ELIGIBLE, Newspack_Popups_Model::CACHE_GROUP ),
			'Cache should be cleared after term assignment.'
		);
	}

	/**
	 * Test that parsed blocks are cached by content hash.
	 */
	public function test_parsed_blocks_are_cached() {
		$content   = '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->';
		$cache_key = 'parsed_blocks_' . md5( $content );

		// Ensure cache is empty.
		wp_cache_delete( $cache_key, Newspack_Popups_Model::CACHE_GROUP );

		// Use reflection to call the private method.
		$method = new ReflectionMethod( 'Newspack_Popups_Inserter', 'get_parsed_blocks' );
		$method->setAccessible( true );

		$result1 = $method->invoke( null, $content );

		// Cache should now be populated.
		$cached = wp_cache_get( $cache_key, Newspack_Popups_Model::CACHE_GROUP );
		self::assertNotFalse( $cached, 'Parsed blocks should be cached.' );
		self::assertEquals( $result1, $cached, 'Cached parsed blocks should match.' );

		// Second call with same content should return identical result.
		$result2 = $method->invoke( null, $content );
		self::assertEquals( $result1, $result2, 'Same content should return same parsed blocks.' );
	}

	/**
	 * Test that different content produces different cache entries.
	 */
	public function test_parsed_blocks_different_content() {
		$content_a = '<!-- wp:paragraph --><p>Content A</p><!-- /wp:paragraph -->';
		$content_b = '<!-- wp:paragraph --><p>Content B</p><!-- /wp:paragraph -->';

		$method = new ReflectionMethod( 'Newspack_Popups_Inserter', 'get_parsed_blocks' );
		$method->setAccessible( true );

		$result_a = $method->invoke( null, $content_a );
		$result_b = $method->invoke( null, $content_b );

		self::assertNotEquals( $result_a, $result_b, 'Different content should produce different parsed blocks.' );

		// Each should be independently cached.
		$cached_a = wp_cache_get( 'parsed_blocks_' . md5( $content_a ), Newspack_Popups_Model::CACHE_GROUP );
		$cached_b = wp_cache_get( 'parsed_blocks_' . md5( $content_b ), Newspack_Popups_Model::CACHE_GROUP );
		self::assertEquals( $result_a, $cached_a );
		self::assertEquals( $result_b, $cached_b );
	}
}
