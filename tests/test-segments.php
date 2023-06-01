<?php
/**
 * Class Segments Test
 *
 * @package Newspack_Popups
 */

/**
 * Segments test case.
 */
class SegmentsTest extends WP_UnitTestCase {

	/**
	 * A complete and valid segment.
	 *
	 * @var array
	 */
	public $complete_and_valid = [
		'name'          => 'Complete and valid',
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
	];

	/**
	 * A valid segment, without all the properties.
	 *
	 * @var array
	 */
	public $valid = [
		'name'          => 'Valid',
		'priority'      => 20,
		'configuration' => [
			'max_posts' => 1,
		],
	];

	/**
	 * A segment missing required properties.
	 *
	 * @var array
	 */
	public $missing_required = [
		'priority'      => 30,
		'configuration' => [
			'max_posts' => 1,
		],
	];

	/**
	 * A segment with additional unknown properties.
	 *
	 * @var array
	 */
	public $additional_properties = [
		'name'          => 'Additional properties',
		'priority'      => 40,
		'configuration' => [
			'max_posts' => 1,
			'unknown'   => 'invalid',
		],
	];

	/**
	 * A segment with a string in a property that expects an integer.
	 *
	 * @var array
	 */
	public $invalid_int = [
		'name'          => 'Invalid Int',
		'priority'      => 10,
		'configuration' => [
			'max_posts' => 'string',
		],
	];

	/**
	 * Make sure we have a clear environment
	 */
	public static function set_up_before_class() {
		$segments = Newspack_Popups_Segmentation::get_segments();
		foreach ( $segments as $segment ) {
			Newspack_Popups_Segmentation::delete_segment( $segment['id'] );
		}
	}

	/**
	 * Data provider for test_create_segment
	 *
	 * @return array
	 */
	public function create_segment_data() {
		return [
			'complete_and_valid'    => [ $this->complete_and_valid ],
			'valid'                 => [ $this->valid ],
			'missing_required'      => [ $this->missing_required ],
			'additional_properties' => [ $this->additional_properties ],
			'invalid_int'           => [ $this->invalid_int ],
		];
	}

	/**
	 * Test create_segment
	 *
	 * @param array $segment The segment.
	 * @dataProvider create_segment_data
	 */
	public function test_create_segment( $segment ) {
		$result = Newspack_Popups_Segmentation::create_segment( $segment );
		$this->assertSame( 1, count( $result ) );

		// Assert everything passed in was stored.
		// As of today, any arbitrary properties are allowed.
		foreach ( $segment as $key => $value ) {
			$this->assertSame( $value, $result[0][ $key ] );
		}

		$created_properties = [ 'id', 'created_at', 'updated_at' ];
		foreach ( $created_properties as $property ) {
			$this->assertArrayHasKey( $property, $result[0] );
		}
	}

	/**
	 * Test create_segment throws an error when passed a string.
	 */
	public function test_create_segment_throws() {
		$this->expectException( TypeError::class );
		Newspack_Popups_Segmentation::create_segment( 'string' );
	}

	/**
	 * Test get_segments
	 */
	public function test_get_segments() {
		$this->assertSame( [], Newspack_Popups_Segmentation::get_segments() );
		Newspack_Popups_Segmentation::create_segment( $this->complete_and_valid );
		Newspack_Popups_Segmentation::create_segment( $this->valid );

		$segments = Newspack_Popups_Segmentation::get_segments();

		$this->assertSame( 2, count( $segments ) );
		$this->assertSame( $this->complete_and_valid['name'], $segments[0]['name'] );
		$this->assertSame( $this->valid['name'], $segments[1]['name'] );
	}

	/**
	 * Test get_segments fill in empty priorities.
	 */
	public function test_get_segments_reindex_priorities() {
		$modified = $this->complete_and_valid;
		unset( $modified['priority'] );
		Newspack_Popups_Segmentation::create_segment( $this->valid );
		Newspack_Popups_Segmentation::create_segment( $modified );

		$segments = Newspack_Popups_Segmentation::get_segments();

		$this->assertSame( 2, count( $segments ) );
		$this->assertSame( $this->valid['name'], $segments[0]['name'] );
		$this->assertSame( $this->complete_and_valid['name'], $segments[1]['name'] );
		$this->assertSame( 0, $segments[0]['priority'] );
		$this->assertSame( 1, $segments[1]['priority'] );
	}

	/**
	 * Test get_segments fill in empty priorities.
	 */
	public function test_get_segments_rremove_non_existent_categories() {
		$cat_1 = $this->factory()->category->create_and_get( [ 'name' => 'Category 1' ] );
		$cat_2 = $this->factory()->category->create_and_get( [ 'name' => 'Category 2' ] );


		$modified = $this->complete_and_valid;
		$modified['configuration']['favorite_categories'] = [ $cat_1->term_id, $cat_2->term_id, 9999 ];
		Newspack_Popups_Segmentation::create_segment( $modified );

		$modified = $this->valid;
		$modified['configuration']['favorite_categories'] = [ 8888 ];
		Newspack_Popups_Segmentation::create_segment( $modified );

		$segments = Newspack_Popups_Segmentation::get_segments();

		$this->assertSame( 2, count( $segments ) );
		$this->assertSame( [ $cat_1->term_id, $cat_2->term_id ], $segments[0]['configuration']['favorite_categories'] );
		$this->assertSame( [], $segments[1]['configuration']['favorite_categories'] );
	}

	/**
	 * Test get_segment_ids
	 */
	public function test_get_segment_ids() {
		$this->assertSame( [], Newspack_Popups_Segmentation::get_segment_ids() );
		Newspack_Popups_Segmentation::create_segment( $this->complete_and_valid );
		Newspack_Popups_Segmentation::create_segment( $this->valid );

		$segments    = Newspack_Popups_Segmentation::get_segments();
		$segment_ids = Newspack_Popups_Segmentation::get_segment_ids();

		$this->assertSame( 2, count( $segment_ids ) );
		$this->assertSame( $segments[0]['id'], $segment_ids[0] );
		$this->assertSame( $segments[1]['id'], $segment_ids[1] );
	}

	/**
	 * Test get_segment
	 */
	public function test_get_segment() {
		$segments_to_create = [
			$this->complete_and_valid,
			$this->valid,
			$this->missing_required,
			$this->additional_properties,
			$this->invalid_int,
		];

		foreach ( $segments_to_create as $segment ) {
			Newspack_Popups_Segmentation::create_segment( $segment );
		}

		$segments = Newspack_Popups_Segmentation::get_segments();

		foreach ( $segments as $segment ) {
			$segment_id      = $segment['id'];
			$segment_from_db = Newspack_Popups_Segmentation::get_segment( $segment_id );
			$this->assertSame( $segment, $segment_from_db );
		}

	}

	/**
	 * Test delete_segment
	 */
	public function test_delete_segment() {
		$segments_to_create = [
			$this->complete_and_valid,
			$this->valid,
			$this->missing_required,
			$this->additional_properties,
			$this->invalid_int,
		];

		foreach ( $segments_to_create as $segment ) {
			Newspack_Popups_Segmentation::create_segment( $segment );
		}

		$segments = Newspack_Popups_Segmentation::get_segments();

		$delete_result = Newspack_Popups_Segmentation::delete_segment( $segments[0]['id'] );
		$this->assertSame( 4, count( $delete_result ) );
		$this->assertSame( $segments[1]['id'], $delete_result[0]['id'] );

		$delete_result = Newspack_Popups_Segmentation::delete_segment( $segments[3]['id'] );
		$this->assertSame( 3, count( $delete_result ) );
		$this->assertSame( $segments[4]['id'], $delete_result[2]['id'] );

		$delete_result2 = Newspack_Popups_Segmentation::delete_segment( 'non-existent' );
		$this->assertSame( $delete_result, $delete_result2 );

	}

	/**
	 * Test update_segment
	 */
	public function test_update_segment() {
		$segments_to_create = [
			$this->complete_and_valid,
			$this->valid,
		];

		foreach ( $segments_to_create as $segment ) {
			Newspack_Popups_Segmentation::create_segment( $segment );
		}

		$segments = Newspack_Popups_Segmentation::get_segments();

		$complete = $segments[0];

		$complete['name']                       = 'Edited';
		$complete['priority']                   = 99;
		$complete['configuration']['min_posts'] = 30;
		$complete['other_properties']           = true;

		$result = Newspack_Popups_Segmentation::update_segment( $complete );

		$this->assertSame( 'Edited', $result[0]['name'] );
		$this->assertSame( 30, $result[0]['configuration']['min_posts'] );
		$this->assertSame( 10, $result[0]['priority'], 'Priority should not be updated' );
		$this->assertNotContains( 'other_properties', $result[0], 'additional properties should not be included' );

		$this->assertSame( $this->valid['name'], $result[1]['name'] );

	}

	/**
	 * Test update_segment throws an error when passed a string.
	 */
	public function test_update_segment_throws() {
		$this->expectException( TypeError::class );
		Newspack_Popups_Segmentation::update_segment( 'string' );
	}

	/**
	 * Test reindex_segments
	 */
	public function test_reindex_segments() {
		$segments_to_create_in_different_order = [
			$this->complete_and_valid,
			$this->additional_properties,
			$this->invalid_int,
			$this->valid,
		];

		// Remove priority from the first segment. This will be populated with priority 0 and will be persisted when the second segment is created.
		unset( $segments_to_create_in_different_order[0]['priority'] );

		foreach ( $segments_to_create_in_different_order as $segment ) {
			Newspack_Popups_Segmentation::create_segment( $segment );
		}

		$segments = Newspack_Popups_Segmentation::get_segments();

		$reindexed = Newspack_Popups_Segmentation::reindex_segments( $segments );
		$index     = 0;
		foreach ( $reindexed as $segment ) {
			$this->assertSame( $index, $segment['priority'] );
			$this->assertSame( $segments_to_create_in_different_order[ $index ]['name'], $segment['name'] );
			$index++;
		}

		// Assert that the reindexed segments were not persisted.
		$segments = Newspack_Popups_Segmentation::get_segments();
		$index    = 0;
		foreach ( $segments as $segment ) {
			$expected_priority = $segments_to_create_in_different_order[ $index ]['priority'] ?? 0;
			$this->assertSame( $segments_to_create_in_different_order[ $index ]['name'], $segment['name'] );
			$this->assertSame( $expected_priority, $segment['priority'] );
			$index++;
		}

	}

	/**
	 * Test reindex_segments throws an error when passed a string.
	 */
	public function test_reindex_segments_throws() {
		$this->expectException( TypeError::class );
		Newspack_Popups_Segmentation::reindex_segments( 'string' );
	}

	/**
	 * Test sort_segments
	 */
	public function test_sort_segments() {
		$segments_to_create_in_different_order = [
			$this->complete_and_valid,
			$this->additional_properties,
			$this->invalid_int,
			$this->valid,
		];

		foreach ( $segments_to_create_in_different_order as $segment ) {
			Newspack_Popups_Segmentation::create_segment( $segment );
		}

		$segments = Newspack_Popups_Segmentation::get_segments();

		$new_order = [
			$segments[3]['id'],
			$segments[0]['id'],
			$segments[1]['id'],
			$segments[2]['id'],
		];

		$sorted = Newspack_Popups_Segmentation::sort_segments( $new_order );

		$this->assertSame( $segments[3]['name'], $sorted[0]['name'] );
		$this->assertSame( $segments[0]['name'], $sorted[1]['name'] );
		$this->assertSame( $segments[1]['name'], $sorted[2]['name'] );
		$this->assertSame( $segments[2]['name'], $sorted[3]['name'] );

		// Assert that the sorted segments were persisted.
		$this->assertSame( $sorted, Newspack_Popups_Segmentation::get_segments() );

		$this->assertTrue(
			is_wp_error(
				Newspack_Popups_Segmentation::sort_segments( array_merge( $new_order, [ 'asdasd' ] ) )
			),
			'Should return wp error if an invalid id is part of the array'
		);

	}

	/**
	 * Test sort_segments throws an error when passed a string.
	 */
	public function test_sort_segments_throws() {
		$this->expectException( TypeError::class );
		Newspack_Popups_Segmentation::sort_segments( 'string' );
	}

	/**
	 * Data provider for test_validate_segment_ids
	 *
	 * @return array
	 */
	public function data_validate_segment_ids() {
		return [
			[
				[ 1, 2, 3 ],
				[
					[
						'id' => 1,
					],
					[
						'id' => 2,
					],
					[
						'id' => 3,
					],
				],
				true,
			],
			[
				[ 1, 2, 3 ],
				[
					[
						'id' => 1,
					],
					[
						'id' => 2,
					],
				],
				false,
			],
			[
				[ 1, 2, 3 ],
				[
					[
						'id' => 1,
					],
					[
						'id' => 2,
					],
					[
						'id' => 4,
					],
				],
				false,
			],
			[
				[ 1, 2, 3 ],
				[
					[
						'id' => 1,
					],
					[
						'id' => 2,
					],
					[
						'id' => 3,
					],
					[
						'id' => 4,
					],
				],
				false,
			],
			[
				[ 1, 2, 3, 4 ],
				[
					[
						'id' => 1,
					],
					[
						'id' => 2,
					],
					[
						'id' => 4,
					],
				],
				false,
			],
			[
				[ 1, 2 ],
				'string',
				false,
				true,
			],
			[
				'string',
				[
					[
						'id' => 1,
					],
				],
				false,
				true,
			],
		];
	}

	/**
	 * Test validate_segment_ids
	 *
	 * @param array   $segment_ids Array of segment IDs to validate.
	 * @param array   $segments    Array of existing segments to validate against.
	 * @param boolean $expected    Whether $segment_ids is valid.
	 * @param boolean $throw       Whether it will throw Type_Error.
	 * @dataProvider data_validate_segment_ids
	 */
	public function test_validate_segment_ids( $segment_ids, $segments, $expected, $throw = false ) {
		if ( $throw ) {
			$this->expectException( TypeError::class );
		}
		$this->assertSame( $expected, Newspack_Popups_Segmentation::validate_segment_ids( $segment_ids, $segments ) );
	}

}
