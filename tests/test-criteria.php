<?php
/**
 * Class Criteria Test
 *
 * @package Newspack_Popups
 */

/**
 * Model test case.
 */
class CriteriaTest extends WP_UnitTestCase {
	/**
	 * Test register_criteria() with defaults.
	 */
	public function test_register_criteria() {
		Newspack_Popups_Criteria::register_criteria( 'test_criteria' );

		$all_criteria = Newspack_Popups_Criteria::get_registered_criteria();

		$this->assertNotEmpty( $all_criteria );
		$this->assertArrayHasKey( 'test_criteria', $all_criteria );
		$this->assertEquals( 'test_criteria', $all_criteria['test_criteria']['id'] );
		$this->assertEquals( 'Test Criteria', $all_criteria['test_criteria']['name'] );
		$this->assertEquals( 'reader_activity', $all_criteria['test_criteria']['category'] );
		$this->assertEquals( 'test_criteria', $all_criteria['test_criteria']['matching_attribute'] );
		$this->assertEquals( 'default', $all_criteria['test_criteria']['matching_function'] );
	}

	/**
	 * Test register_criteria() with config.
	 */
	public function test_register_criteria_with_config() {
		$config = [
			'name'               => 'Criteria Name',
			'category'           => 'reader_engagement',
			'help'               => 'Help text',
			'description'        => 'Criteria description',
			'options'            => [
				[
					'name'  => 'Nothing',
					'value' => '',
				],
				[
					'name'  => 'Option 1',
					'value' => '1',
				],
				[
					'name'  => 'Option 2',
					'value' => '2',
				],
			],
			'matching_function'  => 'list__in',
			'matching_attribute' => 'criteria_attribute',
		];

		Newspack_Popups_Criteria::register_criteria( 'criteria_with_config', $config );

		$all_criteria = Newspack_Popups_Criteria::get_registered_criteria();

		$this->assertArrayHasKey( 'criteria_with_config', $all_criteria );
		$this->assertEquals( 'criteria_with_config', $all_criteria['criteria_with_config']['id'] );
		$this->assertEquals( $config['name'], $all_criteria['criteria_with_config']['name'] );
		$this->assertEquals( $config['category'], $all_criteria['criteria_with_config']['category'] );
		$this->assertEquals( $config['help'], $all_criteria['criteria_with_config']['help'] );
		$this->assertEquals( $config['description'], $all_criteria['criteria_with_config']['description'] );
		$this->assertEquals( $config['options'], $all_criteria['criteria_with_config']['options'] );
		$this->assertEquals( $config['matching_attribute'], $all_criteria['criteria_with_config']['matching_attribute'] );
		$this->assertEquals( $config['matching_function'], $all_criteria['criteria_with_config']['matching_function'] );
	}
}
