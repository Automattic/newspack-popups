<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Class Importer Test
 *
 * @package Newspack_Popups
 */

/**
 * Importer test case.
 */
class ImporterTest extends WP_UnitTestCase_PageWithPopups {

	/**
	 * Call protected/private method of a class.
	 *
	 * @param object $object    Instantiated object that we will run method on.
	 * @param string $method_name Method name to call.
	 * @param array  $parameters Array of parameters to pass into method.
	 *
	 * @return mixed Method return.
	 */
	public function invoke_method( &$object, $method_name, array $parameters = array() ) {
		$reflection = new \ReflectionClass( get_class( $object ) );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $object, $parameters );
	}

	public function process_terms_data() {
		return [
			'all mapped'     => [
				[
					[
						'id'   => 10,
						'name' => 'Test Term 1',
					],
					[
						'id'   => 20,
						'name' => 'Test Term 2',
					],
				],
				[
					[
						'id'   => 11,
						'name' => 'Test Term 1',
					],
					[
						'id'   => 21,
						'name' => 'Test Term 2',
					],
				],
			],
			'one not mapped' => [
				[
					[
						'id'   => 10,
						'name' => 'Test Term 1',
					],
					[
						'id'   => 15,
						'name' => 'Test Term 3',
					],
					[
						'id'   => 20,
						'name' => 'Test Term 2',
					],
				],
				[
					[
						'id'   => 11,
						'name' => 'Test Term 1',
					],
					[
						'id'   => 21,
						'name' => 'Test Term 2',
					],
				],
			],
			'none mapped'    => [
				[
					[
						'id'   => 1,
						'name' => 'Test Term 1',
					],
					[
						'id'   => 2,
						'name' => 'Test Term 3',
					],
					[
						'id'   => 3,
						'name' => 'Test Term 2',
					],
				],
				[],
			],
		];
	}

	/**
	 * Test the pre_process_terms method.
	 *
	 * @param array $input_data The array of terms to process.
	 * @param array $expected The expected result.
	 * @return void
	 * @dataProvider process_terms_data
	 */
	public function test_process_terms( $input_data, $expected ) {
		$importer      = new Newspack_Popups_Importer( [] );
		$r_importer    = new \ReflectionClass( $importer );
		$terms_mapping = [
			10 => 11,
			20 => 21,
		];

		$mapping = $r_importer->getProperty( 'terms_mapping' );
		$mapping->setAccessible( true );
		$mapping->setValue( $importer, $terms_mapping );

		$method = $r_importer->getMethod( 'pre_process_terms' );
		$method->setAccessible( true );
		$result = $method->invokeArgs( $importer, [ $input_data ] );

		$this->assertEquals( $expected, $result );

	}

	/**
	 * Test the full functionality of the Importer class
	 *
	 * @return void
	 */
	public function test_integration() {

		// Clear data created by other tests.
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->posts WHERE post_type = %s", Newspack_Popups::NEWSPACK_POPUPS_CPT ) );
		delete_option( Newspack_Popups_Segmentation::SEGMENTS_OPTION_NAME );

		$campaigns = [
			[
				'id'   => 10,
				'name' => 'Campaign 1',
			],
			[
				'id'   => 20,
				'name' => 'Campaign 2',
			],
		];
		$prompts   = [
			[
				'title'           => 'Test Prompt 1',
				'content'         => 'Test content',
				'status'          => 'publish',
				'campaign_groups' => [
					[
						'id'   => 10,
						'name' => 'Campaign 1',
					],
				],
				'options'         => [
					'background_color'    => '#FFFFFF',
					'display_title'       => false,
					'hide_border'         => false,
					'large_border'        => false,
					'frequency'           => 'once',
					'placement'           => 'inline',
					'selected_segment_id' => 'abc123,abc456',
				],
			],
			[
				'title'           => 'Test Prompt 2',
				'content'         => 'Test content',
				'status'          => 'publish',
				'campaign_groups' => [
					[
						'id'   => 20,
						'name' => 'Campaign 2',
					],
				],
				'options'         => [
					'background_color'    => '#FFFFFF',
					'display_title'       => false,
					'hide_border'         => false,
					'large_border'        => false,
					'frequency'           => 'once',
					'placement'           => 'inline',
					'selected_segment_id' => 'abc123',
				],
			],
		];
		$segments  = [
			[
				'name'          => 'Test Segment',
				'id'            => 'abc123',
				'priority'      => 10,
				'configuration' => [
					'max_posts' => 1,
				],
			],
			[
				'name'          => 'Test Segment 2',
				'id'            => 'abc456',
				'priority'      => 10,
				'configuration' => [
					'max_posts' => 1,
				],
			],
		];

		$package = [
			'campaigns' => $campaigns,
			'prompts'   => $prompts,
			'segments'  => $segments,
		];

		$importer = new Newspack_Popups_Importer( $package );
		$result   = $importer->import();

		$this->assertSame( 2, $result['totals']['campaigns'] );
		$this->assertSame( 2, $result['totals']['segments'] );
		$this->assertSame( 2, $result['totals']['prompts'] );

		// Test 2 groups were created.
		$created_groups = Newspack_Popups::get_groups();
		$this->assertSame( 2, count( $created_groups ) );

		// Test if segmentes were properly created.
		$created_segments = Newspack_Popups_Segmentation::get_segments();
		$this->assertSame( 2, count( $created_segments ) );
		$this->assertSame( 'Test Segment', $created_segments[0]['name'] );
		$this->assertSame( 10, $created_segments[0]['priority'] );
		$this->assertSame( 1, $created_segments[0]['configuration']['max_posts'] );
		$this->assertSame( 'Test Segment 2', $created_segments[1]['name'] );

		// Get the IDs of the created segments to see if they were properly mapped in the prompts.
		$segment_1_id = $created_segments[0]['id'];
		$segment_2_id = $created_segments[1]['id'];

		// Check if 2 prompts were created.
		$created_prompts = Newspack_Popups_Model::retrieve_popups( true );
		$this->assertSame( 2, count( $created_prompts ) );

		// Check if the prompts were properly mapped to the segments.
		$this->assertStringContainsString( $segment_1_id, $created_prompts[0]['options']['selected_segment_id'] );
		$this->assertStringContainsString( $segment_2_id, $created_prompts[0]['options']['selected_segment_id'] );
		$this->assertStringContainsString( $segment_1_id, $created_prompts[1]['options']['selected_segment_id'] );

		// Check if the campaign groups terms were properly applied to the prompts.
		$this->assertSame( 1, count( $created_prompts[0]['campaign_groups'] ) );
		$this->assertSame( 'Campaign 1', $created_prompts[0]['campaign_groups'][0]->name );
		$this->assertSame( 1, count( $created_prompts[1]['campaign_groups'] ) );
		$this->assertSame( 'Campaign 2', $created_prompts[1]['campaign_groups'][0]->name );

	}

	/**
	 * Test the import method with invalid data.
	 *
	 * @return void
	 */
	public function test_integration_invalid_data() {

		$campaigns = [
			[
				'id'   => 10,
				'name' => 'Campaign 1',
			],
		];
		// missing required filed in prompts.
		$prompts = [
			[
				'title'   => 'Test Prompt 1',
				'content' => 'Test content',
				'status'  => 'publish',
				'options' => [
					'selected_segment_id' => 'abc123,abc456',
					'frequency'           => 'once',
				],
			],
		];
		// missing required filed in segments.
		$segments = [
			[
				'name'     => 'Test Segment',
				'id'       => 'abc123',
				'priority' => 10,
			],
		];

		$package = [
			'campaigns' => $campaigns,
			'prompts'   => $prompts,
			'segments'  => $segments,
		];

		$importer = new Newspack_Popups_Importer( $package );
		$result   = $importer->import();

		$this->assertNotEmpty( $result['errors'] );
		$this->assertNotEmpty( $result['errors']['validation'] );

	}

}
