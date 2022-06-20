<?php
/**
 * Class API Test
 *
 * @package Newspack_Popups
 */

/**
 * API test case.
 */
class APITest extends WP_UnitTestCase {
	private static $settings             = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $maybe_show_campaign  = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $report_campaign_data = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $report_client_data   = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $client_id            = 'abc-123'; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $segment_ids          = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $category_ids         = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$maybe_show_campaign  = new Maybe_Show_Campaign();
		self::$report_campaign_data = new Report_Campaign_Data();
		self::$report_client_data   = new Segmentation_Client_Data();

		$category_1_id      = self::factory()->term->create(
			[
				'name'     => 'Events',
				'taxonomy' => 'category',
				'slug'     => 'events',
			]
		);
		$category_2_id      = self::factory()->term->create(
			[
				'name'     => 'Health',
				'taxonomy' => 'category',
				'slug'     => 'health',
			]
		);
		self::$category_ids = [ $category_1_id, $category_2_id ];
		$test_segments      = [
			'defaultSegment'                      => [],
			'segmentBetween3And5'                 => [
				'min_posts' => 2,
				'max_posts' => 3,
				'priority'  => 0,
			],
			'segmentSessionReadCountBetween3And5' => [
				'min_session_posts' => 2,
				'max_session_posts' => 3,
				'priority'          => 1,
			],
			'segmentSubscribers'                  => [
				'is_subscribed' => true,
				'priority'      => 2,
			],
			'segmentLoggedIn'                     => [
				'has_user_account' => true,
				'priority'         => 3,
			],
			'segmentNonSubscribers'               => [
				'is_not_subscribed' => true,
				'priority'          => 4,
			],
			'segmentWithReferrers'                => [
				'referrers' => 'foobar.com, newspack.pub',
				'priority'  => 5,
			],
			'anotherSegmentWithReferrers'         => [
				'referrers' => 'bar.com',
				'priority'  => 6,
			],
			'segmentWithNegativeReferrer'         => [
				'referrers_not' => 'baz.com',
				'priority'      => 7,
			],
			'segmentFavCategory42'                => [
				'favorite_categories' => [ $category_1_id ],
				'priority'            => 8,
			],
		];

		foreach ( $test_segments as $key => $value ) {
			$segments = Newspack_Popups_Segmentation::create_segment(
				[
					'name'          => $key,
					'configuration' => $value,
				]
			);

			self::$segment_ids[ $key ] = end( $segments )['id'];
		}

		self::$settings = (object) [ // phpcs:ignore Squiz.Commenting.VariableComment.Missing
			'all_segments' => (object) array_reduce(
				Newspack_Popups_Segmentation::get_segments(),
				function( $acc, $item ) {
					$acc[ $item['id'] ] = $item['configuration'];
					return $acc;
				},
				[]
			),
		];
	}

	public static function create_test_popup( $options, $post_content = 'Faucibus placerat senectus metus molestie varius tincidunt.' ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_title'   => 'Platea fames',
				'post_content' => $post_content,
			]
		);
		Newspack_Popups_Model::set_popup_options( $popup_id, $options );
		$payload = (object) Newspack_Popups_Inserter::create_single_popup_access_payload(
			Newspack_Popups_Model::create_popup_object( get_post( $popup_id ) )
		);
		return [
			'id'      => $popup_id,
			'payload' => $payload,
		];
	}

	public static function create_event( $value = [], $date_created = false, $type = 'view', $context = 'post' ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		if ( false === $date_created ) {
			$date_created = gmdate( 'Y-m-d H:i:s' );
		}
		return [
			'client_id'    => self::$client_id,
			'date_created' => $date_created,
			'type'         => $type,
			'context'      => $context,
			'value'        => $value,
		];
	}

	/**
	 * Test multiple segments assigned per prompt.
	 */
	public function test_multiple_segments() {
		$segments   = [ self::$segment_ids['segmentBetween3And5'], self::$segment_ids['segmentSubscribers'] ];
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => implode( ',', $segments ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown if client matches no assigned segments.'
		);

		// Report 3 articles read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
				self::create_event( [ 'post_id' => 2 ] ),
				self::create_event( [ 'post_id' => 3 ] ),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert shown if client’s highest-priority matching segment is assigned.'
		);
	}

	/**
	 * Test comparing prompts with different-priority segments.
	 */
	public function test_segment_priority() {
		$test_overlay_a = self::create_test_popup(
			[
				'placement'           => 'center',
				'frequency'           => 'daily',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);
		$test_overlay_b = self::create_test_popup(
			[
				'placement'           => 'center',
				'frequency'           => 'daily',
				'selected_segment_id' => self::$segment_ids['segmentNonSubscribers'],
			]
		);

		$higher_priority = self::$maybe_show_campaign->get_higher_priority_item(
			$test_overlay_a['payload'],
			$test_overlay_b['payload'],
			self::$settings->all_segments
		);

		self::assertTrue(
			$higher_priority->id === $test_overlay_a['payload']->id,
			'Assert the prompt with the highest priority is shown.'
		);
	}

	/**
	 * Suppression caused by "once" frequency.
	 */
	public function test_once_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'once' ] );

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => self::$client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);
	}

	/**
	 * Suppression caused by "daily" frequency.
	 */
	public function test_daily_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'daily' ] );

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => self::$client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown(
				self::$client_id,
				$test_popup['payload'],
				self::$settings,
				'',
				'',
				false,
				strtotime( '+1 day 1 hour' )
			),
			'Assert visible after a day has passed.'
		);
	}

	/**
	 * Suppression by UTM source.
	 */
	public function test_utm_source_suppression() {
		$test_popup_a = self::create_test_popup(
			[
				'placement'       => 'inline',
				'frequency'       => 'always',
				'utm_suppression' => 'Our Newsletter',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown(
				self::$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=twitter'
			),
			'Assert shown when a referer is set, but not the one to be suppressed.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown(
				self::$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=Our+Newsletter'
			),
			'Assert not shown when a referer is set, using plus sign as space.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert shown on a subsequent visit, without the UTM source in the URL.'
		);

		$test_popup_b = self::create_test_popup(
			[
				'placement'       => 'inline',
				'frequency'       => 'always',
				'utm_suppression' => 'Our Newsletter',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown(
				self::$client_id,
				$test_popup_b['payload'],
				self::$settings,
				'http://example.com?utm_source=Our%20Newsletter'
			),
			'Assert not shown when a referer is set, using %20 as space.'
		);
	}

	/**
	 * Client data saving and retrieval.
	 */
	public function test_client_data() {
		$api       = new Lightweight_API();
		$blueprint = $api->reader_blueprint;

		$blueprint['client_id'] = self::$client_id;

		self::assertEquals(
			$api->get_reader( self::$client_id ),
			$blueprint,
			'Returns expected data blueprint in absence of saved data.'
		);

		$read_post = [
			'post_id'    => '142',
			'categories' => '12,13',
		];

		$api->save_reader_data(
			self::$client_id,
			[ self::create_event( $read_post ) ]
		);

		$reader_data = $api->get_reader_data_persistent( self::$client_id );

		self::assertArraySubset(
			[
				'client_id' => self::$client_id,
				'type'      => 'view_count',
				'context'   => 'post',
				'value'     => [ 'count' => 1 ],
			],
			$reader_data[0],
			'Returns view count after a post view was reported.'
		);

		self::assertArraySubset(
			[
				'client_id' => self::$client_id,
				'type'      => 'term_count',
				'context'   => 'category',
				'value'     => [
					'12' => 1,
					'13' => 1,
				],
			],
			$reader_data[1],
			'Returns category view count after a post view was reported.'
		);

		$api->save_reader(
			self::$client_id,
			[
				'some_other_data' => 42,
			]
		);

		$reader = $api->get_reader( self::$client_id );

		self::assertArraySubset(
			[
				'client_id'  => self::$client_id,
				'is_preview' => null,
			],
			$reader,
			'Only valid keys are saved and returned.'
		);
	}

	/**
	 * Client data rebuilding.
	 */
	public function test_client_data_rebuild() {
		$api       = new Lightweight_API();
		$client_id = 'client_' . uniqid();

		// Report 3 articles read.
		$api->save_reader_data(
			$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
				self::create_event( [ 'post_id' => 2 ] ),
				self::create_event( [ 'post_id' => 3 ] ),
			]
		);

		$reader_data = $api->get_reader_data( $client_id, 'view_count', 'post' );

		self::assertEquals(
			$reader_data[0]['value']['count'],
			3,
			'Returns expected data after saving new events.'
		);
	}

	/**
	 * Updating prompts in events data.
	 */
	public function test_event_data_prompts() {
		$api       = new Lightweight_API();
		$client_id = 'client_' . uniqid();
		$popup_id  = Newspack_Popups_Model::canonize_popup_id( uniqid() );

		// Report a prompt view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => $popup_id,
			]
		);
		$seen_event = [
			'client_id'     => $client_id,
			'date_created'  => gmdate( 'Y-m-d H:i:s' ),
			'date_modified' => gmdate( 'Y-m-d H:i:s' ),
			'type'          => 'prompt_seen',
			'context'       => $popup_id,
			'value'         => '',
		];

		self::assertArraySubset(
			$seen_event,
			$api->get_reader_data( $client_id, 'prompt_seen' )[0],
			'Returns prompt data after a prompt is reported.'
		);

		// Report another view of the same prompt.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => $popup_id,
			]
		);

		$new_seen_event = [
			'client_id'     => $client_id,
			'date_created'  => gmdate( 'Y-m-d H:i:s' ),
			'date_modified' => gmdate( 'Y-m-d H:i:s' ),
			'type'          => 'prompt_seen',
			'context'       => $popup_id,
			'value'         => '',
		];

		self::assertArraySubset(
			$new_seen_event,
			$api->get_reader_data( $client_id, 'prompt_seen' )[1],
			'Returns prompt data.'
		);

		// Report another view of a different prompt.
		$new_popup_id            = Newspack_Popups_Model::canonize_popup_id( uniqid() );
		$expected_new_popup_data = [
			'count'       => 1,
			'last_viewed' => time(),
		];
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => $new_popup_id,
			]
		);
		$third_seen_event = [
			'client_id'     => $client_id,
			'date_created'  => gmdate( 'Y-m-d H:i:s' ),
			'date_modified' => gmdate( 'Y-m-d H:i:s' ),
			'type'          => 'prompt_seen',
			'context'       => $new_popup_id,
			'value'         => '',
		];

		self::assertArraySubset(
			$third_seen_event,
			$api->get_reader_data( $client_id, 'prompt_seen' )[2],
			'Returns data in expected shape.'
		);
	}

	/**
	 * Client data saving - a single donation.
	 */
	public function test_client_data_donations() {
		$api       = new Lightweight_API();
		$client_id = 'test_' . uniqid();

		// Report a donation.
		$donation_1 = [
			'order_id' => '120',
			'date'     => '2020-10-28',
			'amount'   => '180.00',
		];
		self::$report_client_data->report_client_data(
			[
				'client_id' => $client_id,
				'donation'  => $donation_1,
			]
		);
		// Add another donation, JSON-encoded.
		$donation_2 = [
			'date'   => '2020-11-28',
			'amount' => '120.00',
		];
		self::$report_client_data->report_client_data(
			[
				'client_id' => $client_id,
				'donation'  => wp_json_encode( $donation_2 ),
			]
		);

		$donate_event_1 = [
			'client_id' => $client_id,
			'type'      => 'donation',
			'value'     => $donation_1,
		];

		$donate_event_2 = [
			'client_id' => $client_id,
			'type'      => 'donation',
			'value'     => $donation_2,
		];

		self::assertArraySubset(
			$donate_event_1,
			$api->get_reader_data( $client_id, 'donation' )[0],
			'Returns data with donation data after a donation is reported.'
		);

		self::assertArraySubset(
			$donate_event_2,
			$api->get_reader_data( $client_id, 'donation' )[1],
			'Returns data with donation data after a donation is reported.'
		);
	}

	/**
	 * Collecting of the email address used for newsletter subscription.
	 */
	public function test_newsletter_subscription_data_collection() {
		$test_popup_with_subscription_block = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		$blueprint              = self::$report_campaign_data->reader_blueprint;
		$blueprint['client_id'] = self::$client_id;

		self::assertEquals(
			self::$report_campaign_data->get_reader( self::$client_id ),
			$blueprint,
			'The initial reader data has expected shape.'
		);

		$email_address = 'foo@bar.com';
		$prompt_id     = Newspack_Popups_Model::canonize_popup_id( $test_popup_with_subscription_block['id'] );

		// Report a subscription.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => self::$client_id,
				'popup_id'            => $prompt_id,
				'mailing_list_status' => 'subscribed',
				'email'               => $email_address,
			]
		);

		$subscribe_event = [
			'client_id' => self::$client_id,
			'type'      => 'subscription',
			'context'   => '',
			'value'     => [
				'email' => $email_address,
			],
		];

		self::assertArraySubset(
			$subscribe_event,
			self::$report_campaign_data->get_reader_data( self::$client_id, 'subscription' )[0],
			'The events data after a subscription contains the provided email address.'
		);
	}

	/**
	 * Suppression of a subscriber-segmented campaign.
	 */
	public function test_only_subscriber_suppression() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentSubscribers'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report a subscription.
		self::$report_client_data->report_client_data(
			[
				'client_id'          => self::$client_id,
				'email_subscription' => [
					'email' => 'reader@example.com',
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert shown after reader has subscribed.'
		);
	}

	/**
	 * Suppression of a non-subscriber-segmented campaign.
	 */
	public function test_non_subscriber_suppression() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentNonSubscribers'],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a subscription.
		self::$report_client_data->report_client_data(
			[
				'client_id'          => self::$client_id,
				'email_subscription' => [
					'email' => 'reader@example.com',
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after reader has subscribed.'
		);
	}

	/**
	 * Default (not configured) segment.
	 */
	public function test_segment_default() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => '',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert visible.'
		);
	}

	/**
	 * User account segment.
	 */
	public function test_segment_user_account() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentLoggedIn'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, since user has no account.'
		);

		// Report a login.
		self::$report_client_data->report_client_data(
			[
				'client_id' => self::$client_id,
				'user_id'   => 123,
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible, since user has an account.'
		);
	}

	/**
	 * Suppression caused by a read count segment.
	 */
	public function test_segment_read_count_range() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 3 articles read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
				self::create_event( [ 'post_id' => 2 ] ),
				self::create_event( [ 'post_id' => 3 ] ),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report more than 5 articles read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 4 ] ),
				self::create_event( [ 'post_id' => 5 ] ),
				self::create_event( [ 'post_id' => 6 ], false, 'view', 'page' ), // All singular post types are counted as articles.
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not shown when more than five articles were read.'
		);
	}

	/**
	 * Suppression caused by a read count segment, with a 'once' frequency cap.
	 */
	public function test_segment_read_count_range_with_once_frequency() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'once',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 3 articles read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
				self::create_event( [ 'post_id' => 2 ] ),
				self::create_event( [ 'post_id' => 3 ] ),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report a prompt view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => self::$client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_segment['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not shown after it has been shown once.'
		);
	}

	/**
	 * Suppression caused by a read count segment, with a 'daily' frequency cap.
	 */
	public function test_segment_read_count_range_with_daily_frequency() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'daily',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 3 articles read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
				self::create_event( [ 'post_id' => 2 ] ),
				self::create_event( [ 'post_id' => 3 ] ),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => self::$client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_segment['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not shown after it has been shown once.'
		);
	}

	/**
	 * Suppression caused by a session read count segment.
	 */
	public function test_segment_session_read_count() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentSessionReadCountBetween3And5'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not visible initially.'
		);

		// Report 2 articles read before current session.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ], gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', time() ) ) ),
				self::create_event( [ 'post_id' => 2 ], gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', time() ) ) ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not visible initially, still.'
		);

		// Report 3 articles read in the session.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 3 ] ),
				self::create_event( [ 'post_id' => 4 ] ),
				self::create_event( [ 'post_id' => 5 ] ),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report more than 5 articles read in the session.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 6 ] ),
				self::create_event( [ 'post_id' => 7 ] ),
				self::create_event( [ 'post_id' => 8 ] ),
				self::create_event( [ 'post_id' => 9 ] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not shown when more than five articles were read.'
		);
	}

	/**
	 * Handling referrer-based segmentation.
	 */
	public function test_segment_page_referrer() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentWithReferrers'],
			]
		);

		self::$settings->best_priority_segment_id = self::$segment_ids['segmentWithReferrers'];

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'http://foobar.com' ),
			'Assert visible if first referrer matches.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://newspack.pub' ),
			'Assert visible if second referrer matches.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://sub.newspack.pub' ),
			'Assert visible if referrer with subdomain matches.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://www.foobar.com' ),
			'Assert visible if referrer matches, with a www subdomain.'
		);
		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://google.com' ),
			'Assert not visible if referrer does not match.'
		);
	}

	/**
	 * Handling negative referrer-based segmentation.
	 */
	public function test_segment_page_not_referrer() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentWithNegativeReferrer'],
			]
		);

		self::$settings->best_priority_segment_id = self::$segment_ids['segmentWithNegativeReferrer'];

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert visible without referrer.'
		);
		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'http://baz.com' ),
			'Assert not visible if referrer matches.'
		);
		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://www.baz.com' ),
			'Assert not visible if referrer matches, with a www subdomain.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://google.com' ),
			'Assert visible if referrer does not match.'
		);
	}

	/**
	 * Handling category-affinity-based segmentation.
	 */
	public function test_segment_page_favorite_categories() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentFavCategory42'],
			]
		);

		self::$settings->best_priority_segment_id = self::$segment_ids['segmentFavCategory42'];

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert invisible initially.'
		);

		// Report an article read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert invisible with a single category-less article read.'
		);

		// Report 2 articles from the favorite category read.
		self::$maybe_show_campaign->save_reader_data(
			self::$client_id,
			[
				self::create_event(
					[
						'post_id'    => 2,
						'categories' => self::$category_ids[0],
					]
				),
				self::create_event(
					[
						'post_id'    => 3,
						'categories' => implode( ',', self::$category_ids ),
					]
				),
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible after favourite category matches.'
		);
	}

	/**
	 * Non-existing segment.
	 */
	public function test_segment_non_existing_segment() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => 'no-such-segment',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible, since there is no such segment.'
		);
	}

	/**
	 * View as a segment – subscribers.
	 */
	public function test_view_as_segment_subscribers() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentSubscribers'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the client is not a subscriber.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentSubscribers'] ] ),
			'Assert visible when viewing as a segment member.'
		);
	}

	/**
	 * View as a segment – logged in.
	 */
	public function test_view_as_segment_logged_in() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentLoggedIn'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the client is not logged in.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentLoggedIn'] ] ),
			'Assert visible when viewing as a segment member.'
		);
	}

	/**
	 * View as a segment – ignoring client data.
	 */
	public function test_view_as_segment_client_data_ignoring() {
		$api = new Lightweight_API();

		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);

		$api->save_reader_data(
			self::$client_id,
			[
				self::create_event( [ 'post_id' => 1 ], false, '42' ),
				self::create_event( [ 'post_id' => 2 ], false, '42' ),
				self::create_event( [ 'post_id' => 3 ], false, '42' ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentWithReferrers'] ] ),
			'Assert campaign with read count not visible when viewing as a different segment.'
		);

		$test_popup_2 = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentFavCategory42'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_2['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentWithReferrers'] ] ),
			'Assert campaign with fav. categories segment not visible when viewing as a different segment.'
		);
	}

	/**
	 * View as a segment – posts read count.
	 */
	public function test_view_as_segment_read_count() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentBetween3And5'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the client does not have the appropriate read count.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentBetween3And5'] ] ),
			'Assert visible when viewing as a segment member.'
		);
	}

	/**
	 * View as a segment – referrers.
	 */
	public function test_view_as_segment_referrers() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentWithReferrers'],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible without referrer.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentWithReferrers'] ] ),
			'Assert visible when viewing as a segment member.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', 'https://newspack.pub', [ 'segment' => self::$segment_ids['segmentWithReferrers'] ] ),
			'Assert visible when viewing as a segment member, with a referrer.'
		);
		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', 'https://twitter.com', [ 'segment' => self::$segment_ids['anotherSegmentWithReferrers'] ] ),
			'Assert not visible when viewing as a different segment with a referrer.'
		);
	}

	/**
	 * View as a segment – referrers.
	 */
	public function test_view_as_segment_referrers_negative() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentWithNegativeReferrer'],
			]
		);

		self::$settings->best_priority_segment_id = self::$segment_ids['segmentWithNegativeReferrer'];

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible without referrer.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentWithNegativeReferrer'] ] ),
			'Assert visible when viewing as a segment member.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', 'https://newspack.pub', [ 'segment' => self::$segment_ids['segmentWithNegativeReferrer'] ] ),
			'Assert visible when viewing as a segment member, with a referrer.'
		);
	}

	/**
	 * View as a segment – category affinity.
	 */
	public function test_view_as_segment_favorite_categories() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentFavCategory42'],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => self::$segment_ids['segmentFavCategory42'] ] ),
			'Assert visible when viewing as a segment member.'
		);
	}

	/**
	 * View as a segment – non-existent segment.
	 */
	public function test_view_as_segment_non_existent() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => 'garbagio',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible, a non-existent segment is disregarded.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => 'not-a-segment' ] ),
			'Assert visible, a non-existent segment is disregarded.'
		);
	}

	/**
	 * View as a segment - "everyone" segment.
	 */
	public function test_view_as_segment_everyone() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => self::$segment_ids['segmentFavCategory42'], // any segment.
			]
		);
		$test_popup_everyone     = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => '',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', '', [ 'segment' => 'everyone' ] ),
			'Assert prompt with a segment is not visible when previewing "everyone" segment.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_popup_be_shown( self::$client_id, $test_popup_everyone['payload'], self::$settings, '', '', [ 'segment' => 'everyone' ] ),
			'Assert prompt with no segment is visible when previewing "everyone" segment.'
		);
	}

	/**
	 * Serializing a popup object to be sent to the API.
	 */
	public function test_popup_object_api_serialization() {
		$default_payload = self::create_test_popup( [] )['payload'];
		self::assertArraySubset(
			(array) [
				'f'   => 'always',
				'utm' => null,
				's'   => '',
			],
			(array) $default_payload,
			false,
			'API payload for the default test popup is correct.'
		);

		self::assertRegExp(
			'/id_\d/',
			$default_payload->id,
			'The id in the payload is the popup id prefixed with "id_"'
		);

		self::assertArraySubset(
			(array) [
				'f'   => 'once',
				'utm' => null,
				's'   => '',
			],
			(array) self::create_test_popup(
				[
					'frequency' => 'always',
					'placement' => 'top',
				]
			)['payload'],
			false,
			'An overlay popup with "always" frequency has it corrected to "once".'
		);
	}

	/**
	 * Test missing segment.
	 */
	public function test_missing_segment() {
		$test_popup = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => 'garbagio',
			]
		);

		self::assertNull(
			$test_popup['payload']->s,
			'Returns null if segment is missing.'
		);
	}

	/**
	 * Discarding bot traffic.
	 */
	public function test_discard_bot_traffic() {
		$api = new Lightweight_API();

		self::assertFalse(
			$api->is_a_web_crawler(),
			'Returns false if the user agent is not of a web crawler.'
		);
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'; // phpcs:ignore
		self::assertTrue(
			$api->is_a_web_crawler(),
			'Return true if the user agent is of a web crawler.'
		);
		$_SERVER['HTTP_USER_AGENT'] = 'Test'; // phpcs:ignore
	}

	/**
	 * Test prompt retrieval with a lot of prompts.
	 */
	public function test_many_prompts() {
		$number_of_prompts_to_display = 100;
		$current_index                = 0;
		$test_popups                  = [];

		while ( $current_index < $number_of_prompts_to_display ) {
			$test_popups[] = self::create_test_popup(
				[
					'placement' => 'inline',
					'frequency' => 'always',
				]
			);

			$current_index ++;
		}

		self::assertTrue(
			count( Newspack_Popups_Model::retrieve_eligible_popups() ) === $number_of_prompts_to_display,
			'Can retrieve up to 100 prompts at once.'
		);
	}
}
