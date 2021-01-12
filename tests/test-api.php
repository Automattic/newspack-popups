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

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$maybe_show_campaign  = new Maybe_Show_Campaign();
		self::$report_campaign_data = new Report_Campaign_Data();
		self::$report_client_data   = new Segmentation_Client_Data();

		self::$settings = (object) [ // phpcs:ignore Squiz.Commenting.VariableComment.Missing
			'suppress_newsletter_campaigns'        => true,
			'suppress_all_newsletter_campaigns_if_one_dismissed' => true,
			'suppress_donation_campaigns_if_donor' => true,
			'all_segments'                         => (object) [
				'defaultSegment'                      => (object) [],
				'segmentBetween3And5'                 => (object) [
					'min_posts' => 2,
					'max_posts' => 3,
				],
				'segmentSessionReadCountBetween3And5' => (object) [
					'min_session_posts' => 2,
					'max_session_posts' => 3,
				],
				'segmentSubscribers'                  => (object) [
					'is_subscribed' => true,
				],
				'segmentNonSubscribers'               => (object) [
					'is_not_subscribed' => true,
				],
				'segmentWithReferrers'                => (object) [
					'referrers' => 'foobar.com, newspack.pub',
				],
				'segmentFavCategory42'                => (object) [
					'favorite_categories' => [ 42 ],
				],
			],
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

	public static function create_read_post( $id, $created_at = false, $category_ids = '' ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		if ( false === $created_at ) {
			$created_at = gmdate( 'Y-m-d H:i:s' );
		}
		return [
			'post_id'      => $id,
			'category_ids' => $category_ids,
			'created_at'   => $created_at,
		];
	}

	/**
	 * Suppression caused by "once" frequency.
	 */
	public function test_once_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'once' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);
	}

	/**
	 * Suppression caused by "daily" frequency.
	 */
	public function test_daily_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'daily' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
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
	 * Suppression caused by permanent dismissal.
	 */
	public function test_permanent_dismissal() {
		$test_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible on a subsequent visit.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'              => self::$client_id,
				'popup_id'         => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
				'suppress_forever' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a permanently dismissed.'
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=twitter'
			),
			'Assert shown when a referer is set, but not the one to be suppressed.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=Our+Newsletter'
			),
			'Assert not shown when a referer is set, using plus sign as space.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert not shown on a subsequent visit, without the UTM source in the URL.'
		);

		$test_popup_b = self::create_test_popup(
			[
				'placement'       => 'inline',
				'frequency'       => 'always',
				'utm_suppression' => 'Our Newsletter',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_popup_b['payload'],
				self::$settings,
				'http://example.com?utm_source=Our%20Newsletter'
			),
			'Assert not shown when a referer is set, using %20 as space.'
		);
	}

	/**
	 * Suppression by UTM medium.
	 */
	public function test_utm_medium_suppression() {
		$test_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=email'
			),
			'Assert visible with email utm_medium, but no newsletter form in content.'
		);

		$test_newsletter_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_newsletter_popup['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=conduit'
			),
			'Assert visible with referer and non-email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=email'
			),
			'Assert not shown with email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_newsletter_popup['payload'],
				self::$settings
			),
			'Assert not shown on a subsequent visit, without the UTM medium in the URL.'
		);

		$modified_settings                                = clone self::$settings;
		$modified_settings->suppress_newsletter_campaigns = false;

		$test_newsletter_popup_a = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				self::$client_id,
				$test_newsletter_popup_a['payload'],
				$modified_settings,
				'http://example.com?utm_medium=email'
			),
			'Assert shown with email utm_medium if the perinent setting is off.'
		);
	}

	/**
	 * Suppression of a *different* newsletter campaign.
	 * By default, if a visitor suppresses a newsletter campaign, they will not
	 * be shown other newsletter campaigns.
	 */
	public function test_different_newsletter_campaign_suppression() {
		$test_popup_a = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => self::$client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_a['id'] ),
				'suppress_forever'    => true,
				'is_newsletter_popup' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_a['payload'], self::$settings ),
			'Assert not visible after permanent dismissal.'
		);

		$test_popup_b = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_b['payload'], self::$settings ),
			'Assert the other newsletter popup is not shown.'
		);

		$modified_settings = clone self::$settings;
		$modified_settings->suppress_all_newsletter_campaigns_if_one_dismissed = false;
		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_b['payload'], $modified_settings ),
			'Assert the other newsletter popup is shown if the pertinent setting is off.'
		);

		$test_popup_c = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_c['payload'], self::$settings ),
			'Assert a non-newsletter campaign is displayed.'
		);
	}

	/**
	 * Suppression caused by a newsletter subscription.
	 */
	public function test_newsletter_subscription() {
		$test_popup_with_subscription_block = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_subscription_block['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a subscription.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => self::$client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_subscription_block['id'] ),
				'mailing_list_status' => 'subscribed',
				'email'               => 'foo@bar.com',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_subscription_block['payload'], self::$settings ),
			'Assert not shown after subscribed.'
		);
	}

	/**
	 * Client data saving and retrieval.
	 */
	public function test_client_data() {
		$api = new Lightweight_API();

		self::assertEquals(
			$api->get_client_data( self::$client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => [],
				'email_subscriptions'            => [],
				'donations'                      => [],
			],
			'Returns expected data blueprint in absence of saved data.'
		);

		$posts_read = [
			[
				'post_id'      => '142',
				'category_ids' => '',
			],
		];

		$api->save_client_data(
			self::$client_id,
			[
				'posts_read' => $posts_read,
			]
		);

		self::assertEquals(
			$api->get_client_data( self::$client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => $posts_read,
				'email_subscriptions'            => [],
				'donations'                      => [],
			],
			'Returns data with saved post after an article reading was reported.'
		);

		$api->save_client_data(
			self::$client_id,
			[
				'some_other_data' => 42,
			]
		);

		self::assertEquals(
			$api->get_client_data( self::$client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => $posts_read,
				'email_subscriptions'            => [],
				'some_other_data'                => 42,
				'donations'                      => [],
			],
			'Returns data without overwriting the existing data.'
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

		self::assertEquals(
			self::$report_campaign_data->get_client_data( self::$client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => [],
				'email_subscriptions'            => [],
				'donations'                      => [],
			],
			'The initial client data has expected shape.'
		);

		$email_address = 'foo@bar.com';
		// Report a subscription.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => self::$client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_subscription_block['id'] ),
				'mailing_list_status' => 'subscribed',
				'email'               => $email_address,
			]
		);

		self::assertEquals(
			self::$report_campaign_data->get_client_data( self::$client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => [],
				'donations'                      => [],
				'email_subscriptions'            => [
					[
						'email' => $email_address,
					],
				],
			],
			'The client data after a subscription contains the provided email address.'
		);
	}

	/**
	 * Suppression of a donation campaigns caused by reader having donated.
	 */
	public function test_donor_suppression() {
		$test_popup_with_donate_block = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:newspack-blocks/donate /-->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_donate_block['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a donation.
		self::$report_client_data->report_client_data(
			[
				'client_id' => self::$client_id,
				'donation'  => [
					'order_id' => '120',
					'date'     => '2020-10-28',
					'amount'   => '180.00',
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_donate_block['payload'], self::$settings ),
			'Assert not shown after reader has donated.'
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
				'selected_segment_id' => 'segmentSubscribers',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert shown after reader has subscribed.'
		);

		$referer_url = 'https://example.com/news?utm_medium=email';
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( 'new-client-id', $test_popup['payload'], self::$settings, $referer_url ),
			'Assert shown if coming from email.'
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
				'selected_segment_id' => 'segmentNonSubscribers',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after reader has subscribed.'
		);

		$referer_url = 'https://example.com/news?utm_medium=email';
		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( 'new-client-id', $test_popup['payload'], self::$settings, $referer_url ),
			'Assert not shown if coming from email.'
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
				'selected_segment_id' => 'defaultSegment',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert visible.'
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
				'selected_segment_id' => 'segmentBetween3And5',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 2 articles read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1 ),
					self::create_read_post( 2 ),
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report more than 5 articles read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 3 ),
					self::create_read_post( 4 ),
					self::create_read_post( 5 ),
					self::create_read_post( 6 ),
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
				'selected_segment_id' => 'segmentBetween3And5',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 2 articles read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1 ),
					self::create_read_post( 2 ),
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
				'selected_segment_id' => 'segmentBetween3And5',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		// Report 2 articles read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1 ),
					self::create_read_post( 2 ),
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
				'selected_segment_id' => 'segmentSessionReadCountBetween3And5',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not visible initially.'
		);

		// Ensure legacy post read data format is handled gracefully.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					[
						'post_id'      => 1,
						'category_ids' => '',
					],
				],
			]
		);

		// Report 2 articles read before current session.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1, gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', time() ) ) ),
					self::create_read_post( 2, gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', time() ) ) ),
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not visible initially, still.'
		);

		// Report 2 articles read in the session.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1 ),
					self::create_read_post( 2 ),
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a third article is read.'
		);

		// Report more than 5 articles read in the session.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 3 ),
					self::create_read_post( 4 ),
					self::create_read_post( 5 ),
					self::create_read_post( 6 ),
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings ),
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
				'selected_segment_id' => 'segmentWithReferrers',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'http://foobar.com' ),
			'Assert visible if first referrer matches.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://newspack.pub' ),
			'Assert visible if second referrer matches.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://www.foobar.com' ),
			'Assert visible if referrer matches, with a www subdomain.'
		);
		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup_with_segment['payload'], self::$settings, '', 'https://google.com' ),
			'Assert not visible if referrer does not match.'
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
				'selected_segment_id' => 'segmentFavCategory42',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert invisible initially.'
		);

		// Report an article read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 1 ),
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert invisible with a single category-less article read.'
		);

		// Report 2 articles from the favorite category read.
		self::$maybe_show_campaign->save_client_data(
			self::$client_id,
			[
				'posts_read' => [
					self::create_read_post( 2, false, '42' ),
					self::create_read_post( 3, false, '42,140' ),
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
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
				'selected_segment_id' => 'segmentSubscribers',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the client is not a subscriber.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => 'segmentSubscribers' ] ),
			'Assert visible when viewing as a segment member.'
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
				'selected_segment_id' => 'segmentBetween3And5',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the client does not have the appropriate read count.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => 'segmentBetween3And5' ] ),
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
				'selected_segment_id' => 'segmentWithReferrers',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert not visible, as the referrer does not match.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', 'https://newspack.pub', [ 'segment' => 'segmentWithReferrers' ] ),
			'Assert visible when viewing as a segment member.'
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
				'selected_segment_id' => 'segmentFavCategory42',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => 'segmentFavCategory42' ] ),
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
				'selected_segment_id' => 'defaultSegment',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings ),
			'Assert visible, a non-existent segment is disregarded.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( self::$client_id, $test_popup['payload'], self::$settings, '', '', [ 'segment' => 'not-a-segment' ] ),
			'Assert visible, a non-existent segment is disregarded.'
		);
	}
}
