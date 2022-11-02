<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class E2E Test
 *
 * @package Newspack_Popups
 */

/**
 * E2E test case.
 * (as e2e as we can get without spinning up a headless browser).
 */
class E2ETest extends WP_UnitTestCase_PageWithPopups {
	/**
	 * Basic E2E test - render popups on a page and check API response.
	 */
	public function test_e2e_basic() {
		$another_popup_id = self::createPopup();
		self::renderPost();

		self::assertEquals(
			[
				'id_' . $another_popup_id => true,
				'id_' . self::$popup_id   => true,
			],
			self::getAPIResponse(),
			'API response has expected shape.'
		);
	}

	/**
	 * Mutual exclusion.
	 * A page should render at most one popup of each condition:
	 * - overlays,
	 * - above-header,
	 * - each of custom placements.
	 */
	public function test_e2e_mutual_exclusion() {
		// Remove the default popup that would be programmatically inserted.
		wp_delete_post( self::$popup_id );

		$popups = [
			// Overlays.
			self::createPopup( null, [ 'placement' => 'center' ] ),
			self::createPopup(
				null,
				[
					'placement'           => 'center',
					'selected_segment_id' => self::$segments[0]['id'],
				]
			),
			// Above-header.
			self::createPopup( null, [ 'placement' => 'above_header' ] ),
			self::createPopup(
				null,
				[
					'placement'           => 'above_header',
					'selected_segment_id' => self::$segments[0]['id'],
				]
			),
			// Custom Placement 1.
			self::createPopup( null, [ 'placement' => 'custom1' ] ),
			self::createPopup(
				null,
				[
					'placement'           => 'custom1',
					'selected_segment_id' => self::$segments[0]['id'],
				]
			),
		];

		self::renderPost( '', '<!-- wp:newspack-popups/custom-placement {"customPlacement":"custom1"} /-->' );

		self::assertEquals(
			[
				'id_' . $popups[0] => false,
				'id_' . $popups[1] => true,
				'id_' . $popups[2] => false,
				'id_' . $popups[3] => true,
				'id_' . $popups[4] => false,
				'id_' . $popups[5] => true,
			],
			self::getAPIResponse(),
			'Only one popup (the one with segment assigned) of each mutually-excluding pair is displayed.'
		);
	}

	/**
	 * Test duplication feature.
	 * Duplicated prompts should have the same content, taxonomy terms, and prompt options as the original.
	 * Duplicated prompt title should have "copy" appended to the original prompt's title.
	 */
	public function test_e2e_duplicate_prompt() {
		$original_popup_id = self::createPopup(
			'Hello world',
			[
				'placement'           => 'center',
				'selected_segment_id' => self::$segments[0]['id'],
			]
		);

		// Set up some taxonomy terms to apply to the prompt.
		$category_id = self::factory()->term->create(
			[
				'name'     => 'Featured',
				'taxonomy' => 'category',
				'slug'     => 'featured',
			]
		);
		$tag_id      = self::factory()->term->create(
			[
				'name'     => 'Best of',
				'taxonomy' => 'post_tag',
				'slug'     => 'best-of',
			]
		);
		$campaign_id = self::factory()->term->create(
			[
				'name'     => 'Everyday',
				'taxonomy' => Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY,
				'slug'     => 'everyday',
			]
		);
		wp_set_post_terms( $original_popup_id, [ $category_id ], 'category' );
		wp_set_post_terms( $original_popup_id, [ $tag_id ], 'post_tag' );
		wp_set_post_terms( $original_popup_id, [ $campaign_id ], Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY );

		$duplicate_popup_id          = Newspack_Popups::duplicate_popup( $original_popup_id );
		$second_duplicate_id         = Newspack_Popups::duplicate_popup( $duplicate_popup_id );
		$duplicate_with_new_title_id = Newspack_Popups::duplicate_popup( $original_popup_id, 'Second popup' );
		$subsequent_duplicate_id     = Newspack_Popups::duplicate_popup( $duplicate_with_new_title_id );

		$original_popup   = get_post( $original_popup_id );
		$duplicate_popup  = get_post( $duplicate_popup_id );
		$second_duplicate = get_post( $second_duplicate_id );

		self::assertEquals(
			$duplicate_popup->post_title,
			'Popup title copy',
			'Duplicated prompt appends "copy" to original post title.'
		);

		self::assertEquals(
			$second_duplicate->post_title,
			'Popup title copy 2',
			'Subsequent duplicates are iterated based on the original’s title.'
		);

		self::assertEquals(
			get_the_title( $subsequent_duplicate_id ),
			'Second popup copy',
			'Subsequent duplicates are iterated based on their parent’s title.'
		);

		self::assertEquals(
			$duplicate_popup->post_content,
			$original_popup->post_content,
			'Duplicated prompt has same content as original prompt.'
		);

		self::assertEquals(
			wp_get_post_terms( $original_popup_id, [ 'category', 'post_tag', Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY ], [ 'fields' => 'ids' ] ),
			wp_get_post_terms( $duplicate_popup_id, [ 'category', 'post_tag', Newspack_Popups::NEWSPACK_POPUPS_TAXONOMY ], [ 'fields' => 'ids' ] ),
			'Duplicated prompt has the same categories, tags, and campaign terms as original prompt.'
		);

		self::assertEquals(
			Newspack_Popups_Model::get_popup_options( $original_popup_id ),
			Newspack_Popups_Model::get_popup_options( $duplicate_popup_id ),
			'Duplicated prompt has the same prompt options as the original prompt.'
		);
	}
}
