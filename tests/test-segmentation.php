<?php
/**
 * Class Segmentation Test
 *
 * @package Newspack_Popups
 */

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;

/**
 * Segmentation test case.
 */
class SegmentationTest extends WP_UnitTestCase {

	use ArraySubsetAsserts;

	private static $post_read_payload = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	private static $request = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public function set_up() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		global $wpdb;
		$reader_events_table_name = Segmentation::get_reader_events_table_name();
		$readers_table_name       = Segmentation::get_readers_table_name();
		$wpdb->query( "DELETE FROM $reader_events_table_name;" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM $readers_table_name;" ); // phpcs:ignore
		if ( file_exists( Segmentation::get_log_file_path() ) ) {
			unlink( Segmentation::get_log_file_path() ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
		self::$post_read_payload = [
			'client_id'    => 'test-' . uniqid(),
			'date_created' => gmdate( 'Y-m-d H:i:s' ),
			'type'         => 'view',
			'context'      => 'post',
			'value'        => [
				'post_id'    => 42,
				'categories' => '5,6',
			],
		];
		self::$request           = [
			'cid'      => self::$post_read_payload['client_id'],
			'popups'   => wp_json_encode( [] ),
			'settings' => wp_json_encode( [] ),
			'visit'    => wp_json_encode(
				[
					'post_id'    => self::$post_read_payload['value']['post_id'],
					'post_type'  => 'post',
					'categories' => self::$post_read_payload['value']['categories'],
					'date'       => gmdate( 'Y-m-d', time() ),
				]
			),
		];
	}

	/**
	 * Log file updating with a post_read event.
	 */
	public function test_log_visit() {
		$api = new Lightweight_API();
		Segmentation_Report::log_reader_events( [ self::$post_read_payload ] );

		$expected_log_line = implode(
			'|',
			[
				self::$post_read_payload['client_id'],
				self::$post_read_payload['date_created'],
				self::$post_read_payload['type'],
				self::$post_read_payload['context'],
				wp_json_encode( self::$post_read_payload['value'] ),
			]
		);

		self::assertEquals(
			$expected_log_line . "\n",
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file contains the expected line.'
		);

		$second_event    = [
			'client_id' => self::$post_read_payload['client_id'],
			'type'      => 'view',
			'context'   => 'post',
			'value'     => [
				'post_id'    => 43,
				'categories' => '7,8',
			],
		];
		$legacy_log_line = implode(
			';',
			[
				'post_read',
				self::$post_read_payload['client_id'],
				gmdate( 'Y-m-d', strtotime( '-1 day', time() ) ),
				$second_event['value']['post_id'],
				$second_event['value']['categories'],
			]
		) . "\n";

		Newspack_Popups_Parse_Logs::parse_events_logs();

		self::assertEquals(
			'',
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file is emptied after parsing logs.'
		);

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			Segmentation::get_log_file_path(),
			$legacy_log_line,
			FILE_APPEND
		);

		Newspack_Popups_Parse_Logs::parse_events_logs();

		self::assertArraySubset(
			[
				self::$post_read_payload,
				$second_event,
			],
			$api->get_reader_events( self::$post_read_payload['client_id'] ),
			'Both new and legacy formats are parsed into events.'
		);
	}

	/**
	 * Reporting visits while checking popups visibility.
	 */
	public function test_visit_reporting() {
		$_REQUEST = self::$request;

		// Log an article read event.
		// Checking campaign visibility will log a visit, this way there are less API requests.
		$maybe_show_campaign = new Maybe_Show_Campaign();

		// And a duplicate.
		$maybe_show_campaign = new Maybe_Show_Campaign();
		$date                = gmdate( 'Y-m-d', strtotime( '+1 week' ) );

		// Duplicate article read, but on a different date.
		$_REQUEST            = self::$request;
		$_REQUEST['visit']   = wp_json_encode(
			array_merge(
				(array) json_decode( self::$request['visit'] ),
				[
					'date' => $date,
				]
			)
		);
		$maybe_show_campaign = new Maybe_Show_Campaign();
		$read_posts          = $maybe_show_campaign->get_reader_events( self::$post_read_payload['client_id'], 'view', 'post' );

		self::assertArraySubset(
			self::$post_read_payload,
			$read_posts[0],
			'The read posts array contains the reported post.'
		);

		// Now a visit with a different post id.
		$_REQUEST            = self::$request;
		$_REQUEST['visit']   = wp_json_encode(
			array_merge(
				(array) json_decode( self::$request['visit'] ),
				[
					'post_id' => '23',
					'date'    => $date,
				]
			)
		);
		$maybe_show_campaign = new Maybe_Show_Campaign();
		$read_posts          = $maybe_show_campaign->get_reader_events( self::$post_read_payload['client_id'], 'view', 'post' );

		self::assertEquals(
			4,
			count( $read_posts ),
			'The read posts array is of expected length after reading another post.'
		);

		// Now a non-post visit.
		$_REQUEST          = self::$request;
		$_REQUEST['visit'] = wp_json_encode(
			array_merge(
				(array) json_decode( self::$request['visit'] ),
				[
					'post_id'   => '12345',
					'post_type' => 'page',
				]
			)
		);

		$maybe_show_campaign = new Maybe_Show_Campaign();
		$read_posts          = $maybe_show_campaign->get_reader_events( self::$post_read_payload['client_id'], 'view', 'post' );
		$page_views          = $maybe_show_campaign->get_reader_events( self::$post_read_payload['client_id'], 'view', 'page' );

		self::assertEquals(
			4,
			count( $read_posts ),
			'The read posts array is not updated after a non-post visit was made.'
		);

		self::assertArraySubset(
			[
				'client_id' => self::$post_read_payload['client_id'],
				'type'      => 'view',
				'context'   => 'page',
				'value'     => [
					'post_id'    => '12345',
					'categories' => '5,6',
				],
			],
			$page_views[0],
			'Non-article visits are logged as page views.'
		);
	}

	/**
	 * Parse a "view as" spec.
	 */
	public function test_parse_view_as() {
		self::assertEquals(
			Segmentation::parse_view_as( 'groups:one,two;segment:123' ),
			[
				'groups'  => 'one,two',
				'segment' => '123',
			],
			'Spec is parsed.'
		);

		self::assertEquals(
			Segmentation::parse_view_as( 'all' ),
			[
				'all' => true,
			],
			'Spec is parsed with the "all" value'
		);

		self::assertEquals(
			Segmentation::parse_view_as( '' ),
			[],
			'Empty array is returned if there is no spec.'
		);
	}

	/**
	 * Prune the data.
	 */
	public function test_data_pruning() {
		global $wpdb;
		$api_campaign_handler = new Maybe_Show_Campaign();
		$readers_table_name   = Segmentation::get_readers_table_name();
		$wpdb->query( "DELETE FROM $readers_table_name;" ); // phpcs:ignore

		// Add the donor client data.
		$api_campaign_handler->save_reader( 'test-donor' );
		$api_campaign_handler->save_reader_events(
			'test-donor',
			[
				[
					'client_id' => 'test-donor',
					'type'      => 'donation',
					'value'     => [
						'amount' => 100,
					],
				],
			]
		);

		// Add and backdate the subscriber client data.
		$api_campaign_handler->save_reader( 'test-subscriber' );
		$api_campaign_handler->save_reader_events(
			'test-subscriber',
			[
				[
					'client_id' => 'test-subscriber',
					'type'      => 'subscription',
					'context'   => 'test@testing.com',
				],
			]
		);
		$wpdb->query( "UPDATE $readers_table_name SET `date_modified` = '2020-04-29 15:39:13' WHERE `client_id` = 'test-subscriber';" ); // phpcs:ignore

		// Add the one time reader client data.
		$api_campaign_handler->save_reader( 'test-one-time-reader' );
		$api_campaign_handler->save_reader_events(
			'test-one-time-reader',
			[
				[
					'client_id' => 'test-one-time-reader',
					'type'      => 'view',
					'context'   => 'post',
					'value'     => [
						'post_id'    => '142',
						'categories' => '',
					],
				],
			]
		);

		// Add and backdate the one time reader client data.
		$api_campaign_handler->save_reader( 'test-one-time-reader-backdated' );
		$api_campaign_handler->save_reader_events(
			'test-one-time-reader-backdated',
			[
				[
					'client_id' => 'test-one-time-reader-backdated',
					'type'      => 'view',
					'context'   => 'post',
					'value'     => [
						'post_id'    => '142',
						'categories' => '',
					],
				],
			]
		);
		$wpdb->query( "UPDATE $readers_table_name SET `date_modified` = '2020-04-29 15:39:13' WHERE `client_id` = 'test-one-time-reader-backdated';" ); // phpcs:ignore

		$all_readers_rows = $wpdb->get_results( "SELECT client_id FROM $readers_table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		self::assertEquals(
			4,
			count( $all_readers_rows ),
			'All reader data is present before pruning.'
		);

		// Prune the data.
		Newspack_Popups_Segmentation::prune_data();

		$all_readers_client_ids = array_column( $wpdb->get_results( "SELECT client_id FROM $readers_table_name" ), 'client_id' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertEquals(
			[
				'test-donor', // Donor was not removed.
				'test-one-time-reader', // One time reader was not removed, since they visited in the last 30 days.
				'test-subscriber', // Subscriber was not removed, despite not visiting since >30 days.
			],
			$all_readers_client_ids,
			'After pruning, expected rows are still there.'
		);
	}
}
