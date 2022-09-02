<?php
/**
 * Class Segmentation Criteria Test
 *
 * @package Newspack_Popups
 */

/**
 * Segmentation criteria test case.
 */
class SegmentationCriteriaTest extends WP_UnitTestCase {
	private static $api; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$readers_table_name       = Segmentation::get_readers_table_name();
		$wpdb->query( "DELETE FROM $reader_events_table_name;" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM $readers_table_name;" ); // phpcs:ignore
		self::$api = new Maybe_Show_Campaign();
	}

	/**
	 * Check client against a segment configuration.
	 *
	 * @param string $client_id Client ID.
	 * @param array  $segment_config Segment configuration.
	 */
	private static function does_client_match_segment_config( $client_id, $segment_config ) {
		return Campaign_Data_Utils::does_reader_match_segment(
			Campaign_Data_Utils::canonize_segment( $segment_config ),
			self::$api->get_reader( $client_id ),
			self::$api->get_reader_events( $client_id, Campaign_Data_Utils::get_all_events_types() )
		);
	}

	/**
	 * Add data for a client.
	 *
	 * @param string $client_id Client ID.
	 * @param string $type Event type.
	 * @param array  $data Data to add.
	 */
	private static function add_client_event( $client_id, $type, $data = [] ) {
		$data['client_id'] = $client_id;
		$data['type']      = $type;
		self::$api->save_reader_events( $client_id, [ $data ] );
	}

	/**
	 * Test donor-based segmentation criteria.
	 */
	public function test_segment_criteria_donors() {
		$client_id = 'test-donor';
		self::$api->save_reader( $client_id );
		self::add_client_event( $client_id, 'donation' );

		self::assertTrue(
			self::does_client_match_segment_config(
				$client_id,
				[
					'is_donor' => true,
				]
			)
		);
		self::assertFalse(
			self::does_client_match_segment_config(
				$client_id,
				[
					'is_not_donor' => true,
				]
			)
		);

		// Donor cancels their recurring donation.
		sleep( 1 ); // Ensure cancellation is logged after the donation.
		self::add_client_event( $client_id, 'donation_cancelled' );

		self::assertTrue(
			self::does_client_match_segment_config(
				$client_id,
				[
					'is_donor' => true,
				]
			),
			'A former donor is still recognised as a donor.'
		);
		self::assertTrue(
			self::does_client_match_segment_config(
				$client_id,
				[
					'is_former_donor' => true,
				]
			)
		);

		sleep( 1 ); // Ensure donation is logged after the cancellation.
		self::add_client_event( $client_id, 'donation' );
		self::assertFalse(
			self::does_client_match_segment_config(
				$client_id,
				[
					'is_former_donor' => true,
				]
			),
			'A new donation voids the former donor status.'
		);

		// A client with no donations on record.
		$another_client_id = 'another-client';
		self::$api->save_reader( $another_client_id );
		self::assertFalse(
			self::does_client_match_segment_config(
				$another_client_id,
				[
					'is_former_donor' => true,
				]
			)
		);
	}
}

