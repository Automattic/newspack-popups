<?php // phpcs:disable Squiz.Commenting.FunctionComment.Missing, WordPress.Files.FileName.InvalidClassFileName
/**
 * Class Prompt Tags Test
 *
 * @package Newspack_Popups
 */

use Newspack\Campaigns\Prompt_Tags;

/**
 * Model test case.
 */
class PromptTagsTest extends WP_UnitTestCase {
	public function test_parse_empty_string() {
		$this->assertEquals( '', Prompt_Tags::parse_tags( '' ) );
	}

	public function test_no_tag() {
		$this->assertEquals( 'Test string', Prompt_Tags::parse_tags( 'Test string' ) );
	}

	public function test_not_found_tag() {
		$this->assertEquals( 'Test string {not_found}', Prompt_Tags::parse_tags( 'Test string {not_found}' ) );
	}

	public function test_single_empty_tag() {
		Prompt_Tags::register_tag( 'test_tag' );

		$this->assertEquals( 'Tag: <span class="prompt-tag" data-tag="test_tag" ></span>', Prompt_Tags::parse_tags( 'Tag: {test_tag}' ) );
	}

	public function test_single_tag() {
		Prompt_Tags::register_tag(
			'test_tag',
			[
				'callback' => function() {
					return 'Test tag';
				},
			]
		);

		$this->assertEquals( '<span class="prompt-tag" data-tag="test_tag" >Test tag</span>', Prompt_Tags::parse_tags( '{test_tag}' ) );
	}

	public function test_multiple_tags() {
		Prompt_Tags::register_tag(
			'test_tag',
			[
				'callback' => function() {
					return 'Test tag';
				},
			]
		);

		Prompt_Tags::register_tag(
			'another_tag',
			[
				'callback' => function() {
					return 'Another tag';
				},
			]
		);

		$this->assertEquals( '<span class="prompt-tag" data-tag="test_tag" >Test tag</span> and <span class="prompt-tag" data-tag="another_tag" >Another tag</span>', Prompt_Tags::parse_tags( '{test_tag} and {another_tag}' ) );
	}

	public function test_repetitive_tag() {
		Prompt_Tags::register_tag(
			'test_tag',
			[
				'callback' => function() {
					return 'Test tag';
				},
			]
		);

		$this->assertEquals( '<span class="prompt-tag" data-tag="test_tag" >Test tag</span> with <span class="prompt-tag" data-tag="test_tag" >Test tag</span>', Prompt_Tags::parse_tags( '{test_tag} with {test_tag}' ) );
	}

	public function test_default_tags() {
		$this->assertEquals( '<span class="prompt-tag" data-tag="site_name" >' . get_bloginfo( 'name' ) . '</span>', Prompt_Tags::parse_tags( '{site_name}' ) );
		$this->assertEquals( '<span class="prompt-tag" data-tag="site_description" >' . get_bloginfo( 'description' ) . '</span>', Prompt_Tags::parse_tags( '{site_description}' ) );
	}
}
