<?php
/**
 * Class Segmentation Test
 *
 * @package Newspack_Popups
 */

/**
 * Segmentation test case.
 */
class SegmentationTest extends WP_UnitTestCase {
	private static $post_read_payload = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	private static $request = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		global $wpdb;
		$events_table_name  = Segmentation::get_events_table_name();
		$readers_table_name = Segmentation::get_readers_table_name();
		$wpdb->query( "DELETE FROM $events_table_name;" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM $readers_table_name;" ); // phpcs:ignore
		if ( file_exists( Segmentation::get_log_file_path() ) ) {
			unlink( Segmentation::get_log_file_path() ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
		self::$post_read_payload = [
			'is_post'    => true,
			'clientId'   => 'test-' . uniqid(),
			'post_id'    => '42',
			'categories' => '5,6',
			'created_at' => gmdate( 'Y-m-d H:i:s' ),
		];
		self::$request           = [
			'cid'      => self::$post_read_payload['clientId'],
			'popups'   => wp_json_encode( [] ),
			'settings' => wp_json_encode( [] ),
			'visit'    => wp_json_encode(
				[
					'post_id'    => self::$post_read_payload['post_id'],
					'categories' => self::$post_read_payload['categories'],
					'is_post'    => true,
					'date'       => gmdate( 'Y-m-d', time() ),
				]
			),
		];
	}

	/**
	 * Log file updating with a post_read event.
	 */
	public function test_log_visit() {
		Segmentation_Report::log_single_visit( self::$post_read_payload );

		$expected_log_line = implode(
			';',
			[
				'post_read',
				self::$post_read_payload['clientId'],
				gmdate( 'Y-m-d H:i:s', time() ),
				self::$post_read_payload['post_id'],
				self::$post_read_payload['categories'],
			]
		) . "\n";

		self::assertEquals(
			$expected_log_line,
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file contains the expected line.'
		);

		Segmentation_Report::log_single_visit(
			array_merge(
				self::$post_read_payload,
				[
					'is_post' => false,
				]
			)
		);

		self::assertEquals(
			$expected_log_line,
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file is not updated after a non-post visit is reported.'
		);

		Newspack_Popups_Parse_Logs::parse_events_logs();

		self::assertEquals(
			'',
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file is emptied after parsing logs.'
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

		$date = gmdate( 'Y-m-d', strtotime( '+1 week' ) );

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

		$read_posts = $maybe_show_campaign->get_client_data( self::$post_read_payload['clientId'] )['posts_read'];

		self::assertEquals(
			1,
			count( $read_posts ),
			'The read posts array is of expected length – the duplicates were not inserted.'
		);

		self::assertEquals(
			[
				'post_id'      => self::$post_read_payload['post_id'],
				'category_ids' => self::$post_read_payload['categories'],
				'created_at'   => self::$post_read_payload['created_at'],
			],
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

		$read_posts = $maybe_show_campaign->get_client_data( self::$post_read_payload['clientId'] )['posts_read'];

		self::assertEquals(
			2,
			count( $read_posts ),
			'The read posts array is of expected length after reading another post.'
		);

		// Now a non-post visit.
		$_REQUEST            = self::$request;
		$_REQUEST['visit']   = wp_json_encode(
			array_merge(
				(array) json_decode( self::$request['visit'] ),
				[
					'post_id' => '12345',
					'is_post' => false,
				]
			)
		);
		$maybe_show_campaign = new Maybe_Show_Campaign();
		$read_posts          = $maybe_show_campaign->get_client_data( self::$post_read_payload['clientId'] )['posts_read'];

		self::assertEquals(
			2,
			count( $read_posts ),
			'The read posts array is not updated after a non-post visit was made.'
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
		$readers_table_name = Segmentation::get_readers_table_name();

		$api_campaign_handler = new Maybe_Show_Campaign();

		// Add the donor client data.
		$api_campaign_handler->save_client_data(
			'test-donor',
			[
				'donation' => [ 'amount' => 100 ],
			]
		);

		// Add and backdate the subscriber client data.
		$api_campaign_handler->save_client_data(
			'test-subscriber',
			[
				'email_subscriptions' => [ 'address' => 'test@testing.com' ],
			]
		);
		$wpdb->query( "UPDATE $readers_table_name SET `date` = '2020-04-29 15:39:13' WHERE `option_name` = '_transient_test-subcriber';" ); // phpcs:ignore

		// Add and backdate the one time reader client data.
		$api_campaign_handler->save_client_data(
			'test-one-time-reader',
			[
				'posts_read' => [
					[
						'post_id'      => '142',
						'category_ids' => '',
					],
				],
			]
		);

		// Add and backdate the one time reader client data.
		$api_campaign_handler->save_client_data(
			'test-one-time-reader-backdated',
			[
				'posts_read' => [
					[
						'post_id'      => '142',
						'category_ids' => '',
					],
				],
			]
		);
		$wpdb->query( "UPDATE $readers_table_name SET `date` = '2020-04-29 15:39:13' WHERE `option_name` = '_transient_test-one-time-reader-backdated';" ); // phpcs:ignore

		$all_readers_rows = $wpdb->get_results( "SELECT option_name FROM $readers_table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertEquals(
			4,
			count( $all_readers_rows ),
			'All reader data is present before pruning.'
		);

		// Prune the data.
		Newspack_Popups_Segmentation::prune_data();

		$all_readers_rows = $wpdb->get_results( "SELECT option_name FROM $readers_table_name" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::assertEquals(
			[
				// Donor was not removed.
				(object) [
					'option_name' => '_transient_test-donor',
				],
				// One time reader was not removed, since they visited in the last 30 days.
				(object) [
					'option_name' => '_transient_test-one-time-reader',
				],
				// Subscriber was not removed, despite not visiting since >30 days.
				(object) [
					'option_name' => '_transient_test-subscriber',
				],
			],
			$all_readers_rows,
			'After pruning, expected rows are still there.'
		);
	}
}
