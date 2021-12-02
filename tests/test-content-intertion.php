<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class ContentInsertion Test
 *
 * @package Newspack_Popups
 */

/**
 * ContentInsertion test case.
 */
class ContentInsertionTest extends WP_UnitTestCase {
	/**
	 * Create an inline popup configuration object.
	 *
	 * @param string $id ID.
	 * @param string $placement Placement, as percentage in content.
	 */
	private static function create_inline_popup( $id, $placement ) {
		return [
			'id'      => $id,
			'content' => 'Some content.',
			'options' => [
				'placement'               => 'inline',
				'trigger_scroll_progress' => $placement,
			],
		];
	}

	/**
	 * Get the popup as shortcode - that's how inline popups are inserted into content.
	 *
	 * @param string $id ID.
	 */
	public static function rendered_popup( $id ) {
		return '<!-- wp:shortcode -->[newspack-popup id="' . $id . '"]<!-- /wp:shortcode -->';
	}

	/**
	 * Insertion into block-based post content.
	 */
	public function test_insertion_into_block_content() {
		$post_content        = '
<!-- wp:image {"align":"right"} -->
<div class="wp-block-image">image</div>
<!-- /wp:image -->

<!-- wp:paragraph -->
<p>Paragraph 1</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>A heading</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Paragraph 2</p>
<!-- /wp:paragraph -->
';
		$popups              = [
			// A popup before any content.
			self::create_inline_popup( '1', '0' ),
			// A popup that should not be inserted right after a heading.
			self::create_inline_popup( '2', '70' ),
			// A popup after all content.
			self::create_inline_popup( '3', '100' ),
		];
		$content_with_popups = serialize_blocks(
			Newspack_Popups_Inserter::insert_popups_in_post_content(
				$post_content,
				$popups
			)
		);
		self::assertEquals(
			serialize_blocks(
				self::rendered_popup( '1' ) . '<!-- wp:image {"align":"right"} -->
<div class="wp-block-image">image</div>
<!-- /wp:image --><!-- wp:paragraph -->
<p>Paragraph 1</p>
<!-- /wp:paragraph -->' . self::rendered_popup( '2' ) . '<!-- wp:heading -->
<h2>A heading</h2>
<!-- /wp:heading --><!-- wp:paragraph -->
<p>Paragraph 2</p>
<!-- /wp:paragraph -->' . self::rendered_popup( '3' )
			),
			$content_with_popups,
			'The popups are inserted into the content at expected positions.'
		);
	}

	/**
	 * Insertion into classic (legacy) post content.
	 */
	public function test_insertion_into_classic_content() {
		$post_content        = 'Paragraph 1
<h2>A heading</h2>
Paragraph 2
<blockquote>A quote</blockquote>';
		$popups              = [
			// A popup before any content.
			self::create_inline_popup( '1', '0' ),
			// A popup that should not be inserted right after a heading.
			self::create_inline_popup( '2', '30' ),
			// A popup after all content.
			self::create_inline_popup( '3', '100' ),
		];
		$content_with_popups = serialize_blocks(
			Newspack_Popups_Inserter::insert_popups_in_post_content(
				$post_content,
				$popups
			)
		);
		self::assertEquals(
			serialize_blocks( self::rendered_popup( '1' ) . '<!-- wp:html --><p>Paragraph 1</p><!-- /wp:html -->' . self::rendered_popup( '2' ) . '<!-- wp:heading --><h2>A heading</h2><!-- /wp:heading --><!-- wp:html --><p>Paragraph 2</p><!-- /wp:html --><!-- wp:html --><blockquote><p>A quote</p></blockquote><!-- /wp:html -->' . self::rendered_popup( '3' ) ),
			$content_with_popups,
			'The popups are inserted into the content at expected positions.'
		);
	}
}
