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

		foreach ( $all_criteria as $c ) {
			if ( 'test_criteria' === $c['id'] ) {
				$criteria = $c;
				break;
			}
		}
		$this->assertNotEmpty( $criteria );
		$this->assertEquals( 'test_criteria', $criteria['id'] );
		$this->assertEquals( 'Test Criteria', $criteria['name'] );
		$this->assertEquals( 'reader_activity', $criteria['category'] );
		$this->assertEquals( 'test_criteria', $criteria['matching_attribute'] );
		$this->assertEquals( 'default', $criteria['matching_function'] );
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

		foreach ( $all_criteria as $c ) {
			if ( 'test_criteria' === $c['id'] ) {
				$criteria = $c;
				break;
			}
		}
		$this->assertNotEmpty( $criteria );
		$this->assertEquals( 'criteria_with_config', $criteria['id'] );
		$this->assertEquals( $config['name'], $criteria['name'] );
		$this->assertEquals( $config['category'], $criteria['category'] );
		$this->assertEquals( $config['help'], $criteria['help'] );
		$this->assertEquals( $config['description'], $criteria['description'] );
		$this->assertEquals( $config['options'], $criteria['options'] );
		$this->assertEquals( $config['matching_attribute'], $criteria['matching_attribute'] );
		$this->assertEquals( $config['matching_function'], $criteria['matching_function'] );
	}
}
