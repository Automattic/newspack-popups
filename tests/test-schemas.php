<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Test Schemas
 *
 * @package Newspack_Popups
 */

/**
 * Test Schemas
 */
class SchemasTest extends WP_UnitTestCase {

	/**
	 * Data provider to test prompts schema.
	 *
	 * @return array
	 */
	public function prompts_data() {
		return [
			'complete and valid' => [
				[
					'title'      => 'Test Campaign',
					'content'    => 'Test content',
					'status'     => 'publish',
					'categories' => [
						[
							'id'   => 1,
							'name' => 'Category 1',
						],
						[
							'id'   => 2,
							'name' => 'Category 2',
						],
					],
					'options'    => [
						'background_color'               => '#FFFFFF',
						'display_title'                  => false,
						'hide_border'                    => false,
						'large_border'                   => false,
						'frequency'                      => 'once',
						'frequency_max'                  => 1,
						'frequency_start'                => 1,
						'frequency_between'              => 1,
						'frequency_reset'                => 'day',
						'overlay_color'                  => '#000000',
						'overlay_opacity'                => 50,
						'overlay_size'                   => 'medium',
						'no_overlay_background'          => false,
						'placement'                      => 'center',
						'trigger_type'                   => 'scroll',
						'trigger_scroll_progress'        => 50,
						'trigger_delay'                  => 1,
						'trigger_blocks_count'           => 1,
						'archive_insertion_posts_count'  => 1,
						'archive_insertion_is_repeating' => false,
						'utm_suppression'                => '',
						'selected_segment_id'            => 'asdasd',
						'post_types'                     => [ 'post' ],
						'archive_page_types'             => [],
						'additional_classes'             => '',
						'excluded_categories'            => [],
						'excluded_tags'                  => [],
						'duplicate_of'                   => 0,
						'newspack_popups_has_disabled_popups' => false,
					],
				],
				true,
			],
			'valid'              => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
					],
				],
				true,
			],
			'missing required'   => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'placement'        => 'inline',
					],
				],
				false,
			],
			'invalid type'       => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => 33,
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
					],
				],
				false,
			],
			'invalid bool'       => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => 'string',
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
					],
				],
				false,
			],
			'invalid format'     => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => 'not a color',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
					],
				],
				false,
			],
			'invalid max'        => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
						'overlay_opacity'  => 200,
					],
				],
				false,
			],
			'invalid enum'       => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'invalid',
						'placement'        => 'inline',
					],
				],
				false,
			],
			'invalid enum 2'     => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
						'overlay_size'     => 'super',
					],
				],
				false,
			],
			'additional prop'    => [
				[
					'title'   => 'Test Campaign',
					'content' => 'Test content',
					'status'  => 'publish',
					'options' => [
						'background_color' => '#FFFFFF',
						'display_title'    => false,
						'hide_border'      => false,
						'large_border'     => false,
						'frequency'        => 'once',
						'placement'        => 'inline',
						'unknown'          => 'invalid',
					],
				],
				false,
			],

		];
	}

	/**
	 * Tests the Prompts Schema
	 *
	 * @param array $value The value to be checked.
	 * @param bool  $expected_result The expected result.
	 * @return void
	 * @dataProvider prompts_data
	 */
	public function test_prompts_schema( $value, $expected_result ) {
		$schema = new Newspack\Campaigns\Schemas\Prompts( $value );
		$this->assertSame( $expected_result, $schema->is_valid() );
	}

	/**
	 * Data provider to test segment schema.
	 *
	 * @return array
	 */
	public function segments_data() {
		// data provider to test Schemas\Segment.
		return [
			'complete and valid'     => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'max_posts'           => 1,
						'min_posts'           => 1,
						'min_session_posts'   => 1,
						'max_session_posts'   => 1,
						'is_subscribed'       => true,
						'is_not_subscribed'   => true,
						'is_donor'            => true,
						'is_not_donor'        => true,
						'is_former_donor'     => true,
						'is_logged_in'        => true,
						'is_not_logged_in'    => true,
						'favorite_categories' => [],
						'referrers'           => '',
						'referrers_not'       => '',
						'is_disabled'         => false,
					],
				],
				true,
			],
			'valid'                  => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'max_posts' => 1,
					],
				],
				true,
			],
			'missing required'       => [
				[
					'name'          => 'Test Segment',
					'priority'      => 10,
					'configuration' => [
						'max_posts' => 1,
					],
				],
				false,
			],
			'additional propertu'    => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'max_posts' => 1,
						'unknown'   => 'invalid',
					],
				],
				false,
			],
			'invalid int'            => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'max_posts' => 'string',
					],
				],
				false,
			],
			'fav categories valid'   => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'favorite_categories' => [
							[
								'id'   => 1,
								'name' => 'Test Category',
							],
						],
					],
				],
				true,
			],
			'fav categories invalid' => [
				[
					'name'          => 'Test Segment',
					'id'            => 'aasdqwe1234',
					'priority'      => 10,
					'configuration' => [
						'favorite_categories' => [ 'string' ],
					],
				],
				false,
			],

		];
	}

	/**
	 * Tests the Segments Schema
	 *
	 * @param array $value The value to be checked.
	 * @param bool  $expected_result The expected result.
	 * @return void
	 * @dataProvider segments_data
	 */
	public function test_segments_schema( $value, $expected_result ) {
		$schema = new Newspack\Campaigns\Schemas\Segments( $value );
		$this->assertSame( $expected_result, $schema->is_valid() );
	}
}
