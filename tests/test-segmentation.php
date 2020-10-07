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
	private static $post_read_payload = [ // phpcs:ignore Squiz.Commenting.VariableComment.Missing
		'is_post'    => '1',
		'clientId'   => 'test-1',
		'post_id'    => '42',
		'categories' => '5,6',
	];

	public function setUp() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		global $wpdb;
		$events_table_name = Segmentation::get_events_table_name();
		$wpdb->query( "DELETE FROM $events_table_name;" ); // phpcs:ignore
		if ( file_exists( Segmentation::get_log_file_path() ) ) {
			unlink( Segmentation::get_log_file_path() ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}
	}

	/**
	 * Log file updating with a post_read event.
	 */
	public function test_log_post_read() {
		Segmentation_Report::api_handle_post_read( self::$post_read_payload );

		$expected_log_line = implode(
			';',
			[
				'post_read',
				self::$post_read_payload['clientId'],
				gmdate( 'Y-m-d', time() ),
				self::$post_read_payload['post_id'],
				self::$post_read_payload['categories'],
			]
		) . "\n";

		self::assertEquals(
			$expected_log_line,
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file contains the expected line.'
		);

		Segmentation_Report::api_handle_post_read(
			array_merge(
				self::$post_read_payload,
				[
					'is_post' => '0',
				]
			)
		);

		self::assertEquals(
			$expected_log_line,
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file is not updated after a non-post visit is reported.'
		);
	}

	/**
	 * Log file parsing.
	 */
	public function test_log_parsing() {
		// Duplicate article read, but on a different date.
		Segmentation_Report::api_handle_post_read(
			array_merge(
				self::$post_read_payload,
				[
					'date' => gmdate( 'Y-m-d', strtotime( '-1 week' ) ),
				]
			)
		);
		Segmentation_Report::api_handle_post_read( self::$post_read_payload );
		// Duplicate log entry – to ensure that unique lines are processed.
		Segmentation_Report::api_handle_post_read( self::$post_read_payload );

		Newspack_Popups_Parse_Logs::parse_events_logs();

		$read_posts = Segmentation::get_client_read_posts( self::$post_read_payload['clientId'] );

		self::assertEquals(
			1,
			count( $read_posts ),
			'The read posts array is of expected length.'
		);

		self::assertEquals(
			[
				'post_id'      => self::$post_read_payload['post_id'],
				'category_ids' => self::$post_read_payload['categories'],
			],
			$read_posts[0],
			'The read posts array contains the reported post after logs parsing.'
		);

		self::assertEquals(
			'',
			file_get_contents( Segmentation::get_log_file_path() ),
			'Log file is emptied after parsing logs.'
		);
	}
}
