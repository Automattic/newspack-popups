<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class OverlayQueueing Test
 *
 * Verifies that overlay popups are portaled to wp_footer rather than emitted
 * inside post content, so the rendered DOM node is a direct child of <body>
 * and escapes any ancestor stacking context (transformed wrapper, sticky ad
 * container, isolation:isolate, etc.) that would otherwise trap the popup's
 * z-index below sibling content.
 *
 * @package Newspack_Popups
 */

/**
 * OverlayQueueing test case.
 */
class OverlayQueueingTest extends WP_UnitTestCase {

	public function set_up() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		parent::set_up();
		// Drain any queued overlays carried over from prior tests.
		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		ob_end_clean();
	}

	/**
	 * Build an overlay popup object via the real model so it carries every
	 * default option the markup generator expects.
	 *
	 * @param string $title        Optional title.
	 * @param string $trigger_type 'time' or 'scroll'. Scroll-triggered overlays
	 *                             ship with a page-position marker that must stay
	 *                             inline in the content.
	 * @return array Popup object as consumed by the inserter.
	 */
	private static function create_overlay_popup_object( $title = 'Overlay prompt', $trigger_type = 'time' ) {
		$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_title'   => $title,
				'post_content' => 'Overlay body for ' . $title,
			]
		);
		Newspack_Popups_Model::set_popup_options(
			$popup_id,
			[
				'placement'    => 'center',
				'trigger_type' => $trigger_type,
			]
		);
		return Newspack_Popups_Model::create_popup_object( get_post( $popup_id ) );
	}

	/**
	 * The returned content from insert_popups_in_post_content must NOT include
	 * the overlay markup — it should be queued for wp_footer instead.
	 */
	public function test_overlay_not_inlined_into_returned_content() {
		$overlay_popup = self::create_overlay_popup_object();
		$post_content  = "<!-- wp:paragraph -->\n<p>Body paragraph.</p>\n<!-- /wp:paragraph -->\n";

		$returned_content = Newspack_Popups_Inserter::insert_popups_in_post_content(
			$post_content,
			[ $overlay_popup ]
		);

		self::assertStringNotContainsString(
			'newspack-lightbox',
			$returned_content,
			'Overlay markup must not be inlined into post content; it is queued for wp_footer.'
		);
		self::assertStringNotContainsString(
			'<!-- wp:html -->',
			$returned_content,
			'The legacy wp:html wrapper around inlined overlay markup must no longer appear in returned content.'
		);
		self::assertStringContainsString(
			'Body paragraph.',
			$returned_content,
			'Original post content must be preserved.'
		);
	}

	/**
	 * After insert_popups_in_post_content queues an overlay, print_queued_overlays
	 * emits the overlay markup once.
	 */
	public function test_queued_overlay_is_emitted_at_footer() {
		$overlay_popup = self::create_overlay_popup_object();
		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$footer_output = ob_get_clean();

		self::assertStringContainsString(
			'newspack-lightbox',
			$footer_output,
			'print_queued_overlays must emit the queued overlay markup.'
		);
	}

	/**
	 * Queueing the same overlay popup from multiple call paths (e.g. singular
	 * content + above-header) must result in a single emission, deduped by ID.
	 *
	 * Compared via flushed-output length: queueing N times must produce the same
	 * output length as queueing once. Length is robust against internal markup
	 * structure (the lightbox emits the "newspack-lightbox" substring in
	 * several child class names per popup).
	 */
	public function test_dedupe_by_id_across_multiple_queue_calls() {
		$overlay_popup = self::create_overlay_popup_object();

		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );
		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$single_queue_output = ob_get_clean();

		// Queue the SAME popup multiple times via repeated calls.
		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );
		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );
		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );
		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$repeat_queue_output = ob_get_clean();

		self::assertSame(
			strlen( $single_queue_output ),
			strlen( $repeat_queue_output ),
			'Queueing the same popup multiple times must produce the same output as queueing once.'
		);
		self::assertNotSame(
			'',
			$single_queue_output,
			'Sanity check: the flushed output should not be empty for a queued overlay.'
		);
	}

	/**
	 * Inline placements must still be inlined into post content — only
	 * overlay-typed placements are portaled.
	 */
	public function test_inline_placement_remains_in_returned_content() {
		$inline_popup = [
			'id'      => wp_rand(),
			'content' => 'Inline content.',
			'options' => [
				'placement'               => 'inline',
				'trigger_type'            => 'scroll',
				'trigger_scroll_progress' => '0',
				'trigger_blocks_count'    => '0',
			],
		];
		$returned_content = Newspack_Popups_Inserter::insert_popups_in_post_content(
			"<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->\n",
			[ $inline_popup ]
		);

		self::assertStringContainsString(
			'[newspack-popup id="' . $inline_popup['id'] . '"]',
			$returned_content,
			'Inline popups must still be emitted into post content as the shortcode block.'
		);

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$footer_output = ob_get_clean();

		self::assertSame(
			'',
			$footer_output,
			'Inline popups must never reach the overlay footer queue.'
		);
	}

	/**
	 * Mixed batch: one inline + one overlay. Inline goes inline; overlay goes
	 * to the footer queue.
	 */
	public function test_inline_and_overlay_route_to_their_respective_paths() {
		$overlay_popup = self::create_overlay_popup_object();
		$inline_popup  = [
			'id'      => wp_rand(),
			'content' => 'Inline content.',
			'options' => [
				'placement'               => 'inline',
				'trigger_type'            => 'scroll',
				'trigger_scroll_progress' => '0',
				'trigger_blocks_count'    => '0',
			],
		];

		$returned_content = Newspack_Popups_Inserter::insert_popups_in_post_content(
			"<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->\n",
			[ $inline_popup, $overlay_popup ]
		);

		self::assertStringContainsString(
			'[newspack-popup id="' . $inline_popup['id'] . '"]',
			$returned_content,
			'Inline popup must still be inlined.'
		);
		self::assertStringNotContainsString(
			'newspack-lightbox',
			$returned_content,
			'Overlay popup must not leak into post content when batched with an inline popup.'
		);

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$footer_output = ob_get_clean();

		self::assertStringContainsString(
			'newspack-lightbox',
			$footer_output,
			'Overlay popup must be emitted at the footer.'
		);
		self::assertStringNotContainsString(
			'[newspack-popup id="' . $inline_popup['id'] . '"]',
			$footer_output,
			'Inline popup must never leak into the footer queue output.'
		);
	}

	/**
	 * Scroll-triggered overlays must keep their page-position marker inline in
	 * the post content (the IntersectionObserver mechanism for scroll-trigger
	 * needs the marker positioned against `.entry-content`). The lightbox itself
	 * is still portaled to the footer queue.
	 */
	public function test_scroll_triggered_overlay_marker_stays_inline() {
		$overlay_popup = self::create_overlay_popup_object( 'Scroll overlay', 'scroll' );

		$returned_content = Newspack_Popups_Inserter::insert_popups_in_post_content(
			"<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->\n",
			[ $overlay_popup ]
		);

		self::assertStringContainsString(
			'page-position-marker_',
			$returned_content,
			'Scroll-triggered overlays must emit their page-position marker inline in the post content.'
		);
		self::assertStringNotContainsString(
			'newspack-lightbox',
			$returned_content,
			'The lightbox itself must still be queued for footer rendering, not emitted inline.'
		);

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$footer_output = ob_get_clean();

		self::assertStringContainsString(
			'newspack-lightbox',
			$footer_output,
			'The lightbox should be emitted at wp_footer.'
		);
		self::assertStringNotContainsString(
			'page-position-marker_',
			$footer_output,
			'The footer-emitted lightbox must NOT carry a duplicate page-position marker; the inline one is the one that drives scroll trigger.'
		);
	}

	/**
	 * Time-triggered overlays have no page-position marker. None should be
	 * emitted inline, and the lightbox is queued for footer.
	 */
	public function test_time_triggered_overlay_has_no_inline_marker() {
		$overlay_popup = self::create_overlay_popup_object( 'Time overlay', 'time' );

		$returned_content = Newspack_Popups_Inserter::insert_popups_in_post_content(
			"<!-- wp:paragraph -->\n<p>Body.</p>\n<!-- /wp:paragraph -->\n",
			[ $overlay_popup ]
		);

		self::assertStringNotContainsString(
			'page-position-marker_',
			$returned_content,
			'Time-triggered overlays must not produce a page-position marker.'
		);
	}

	/**
	 * Flushing the queue must clear it: a second flush emits nothing.
	 */
	public function test_flush_clears_the_queue() {
		$overlay_popup = self::create_overlay_popup_object();
		Newspack_Popups_Inserter::insert_popups_in_post_content( '<p>Body.</p>', [ $overlay_popup ] );

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		ob_end_clean();

		ob_start();
		Newspack_Popups_Inserter::print_queued_overlays();
		$second_flush = ob_get_clean();

		self::assertSame(
			'',
			$second_flush,
			'Flushing the queue must drain it; a subsequent flush should be a no-op.'
		);
	}
}
