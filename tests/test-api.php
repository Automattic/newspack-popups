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

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$maybe_show_campaign  = new Maybe_Show_Campaign();
		self::$report_campaign_data = new Report_Campaign_Data();
		self::$report_client_data   = new Segmentation_Client_Data();

		self::$settings = (object) [ // phpcs:ignore Squiz.Commenting.VariableComment.Missing
			'suppress_newsletter_campaigns'        => true,
			'suppress_all_newsletter_campaigns_if_one_dismissed' => true,
			'suppress_donation_campaigns_if_donor' => true,
			'all_segments'                         => (object) [
				'segmentBetween3And5' => (object) [
					'min_posts'         => 2,
					'max_posts'         => 3,
					'is_subscribed'     => false,
					'is_donor'          => false,
					'is_not_subscribed' => false,
					'is_not_donor'      => false,
				],
				'segmentWithZeros'    => (object) [
					'min_posts'         => 0,
					'max_posts'         => 0,
					'is_subscribed'     => false,
					'is_donor'          => false,
					'is_not_subscribed' => false,
					'is_not_donor'      => false,
				],
			],
		];
	}

	public static function create_test_popup( $options, $post_content = 'Faucibus placerat senectus metus molestie varius tincidunt.' ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
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

	/**
	 * Suppression caused by "once" frequency.
	 */
	public function test_once_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'once' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );
		$client_id = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);
	}

	/**
	 * Suppression caused by "daily" frequency.
	 */
	public function test_daily_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'daily' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );
		$client_id = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup['payload'],
				self::$settings,
				'',
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
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert visible on a subsequent visit.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'              => $client_id,
				'popup_id'         => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
				'suppress_forever' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
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
		$client_id    = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=twitter'
			),
			'Assert shown when a referer is set, but not the one to be suppressed.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=Our+Newsletter'
			),
			'Assert not shown when a referer is set, using plus sign as space.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
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
				$client_id,
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
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
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
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_newsletter_popup['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=conduit'
			),
			'Assert visible with referer and non-email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=email'
			),
			'Assert not shown with email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
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
				$client_id,
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
		$client_id    = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => $client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_a['id'] ),
				'suppress_forever'    => true,
				'is_newsletter_popup' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
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
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_b['payload'], self::$settings ),
			'Assert the other newsletter popup is not shown.'
		);

		$modified_settings = clone self::$settings;
		$modified_settings->suppress_all_newsletter_campaigns_if_one_dismissed = false;
		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_b['payload'], $modified_settings ),
			'Assert the other newsletter popup is shown if the pertinent setting is off.'
		);

		$test_popup_c = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_c['payload'], self::$settings ),
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
		$client_id                          = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_subscription_block['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a subscription.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => $client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_subscription_block['id'] ),
				'mailing_list_status' => 'subscribed',
				'email'               => 'foo@bar.com',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_subscription_block['payload'], self::$settings ),
			'Assert not shown after subscribed.'
		);
	}

	/**
	 * Client data saving and retrieval.
	 */
	public function test_client_data() {
		$client_id = 'amp-456';
		$api       = new Lightweight_API();

		self::assertEquals(
			$api->get_client_data( $client_id ),
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
			$client_id,
			[
				'posts_read' => $posts_read,
			]
		);

		self::assertEquals(
			$api->get_client_data( $client_id ),
			[
				'suppressed_newsletter_campaign' => false,
				'posts_read'                     => $posts_read,
				'email_subscriptions'            => [],
				'donations'                      => [],
			],
			'Returns data with saved post after an article reading was reported.'
		);

		$api->save_client_data(
			$client_id,
			[
				'some_other_data' => 42,
			]
		);

		self::assertEquals(
			$api->get_client_data( $client_id ),
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
		$client_id                          = 'amp-123';

		self::assertEquals(
			self::$report_campaign_data->get_client_data( $client_id ),
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
				'cid'                 => $client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_with_subscription_block['id'] ),
				'mailing_list_status' => 'subscribed',
				'email'               => $email_address,
			]
		);

		self::assertEquals(
			self::$report_campaign_data->get_client_data( $client_id ),
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
	 * Suppression caused by reader having donated.
	 */
	public function test_donor_suppression() {
		$test_popup_with_donate_block = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:newspack-blocks/donate /-->'
		);
		$client_id                    = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_donate_block['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a donation.
		self::$report_client_data->report_client_data(
			[
				'client_id' => $client_id,
				'donation'  => [
					'order_id' => '120',
					'date'     => '2020-10-28',
					'amount'   => '180.00',
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_donate_block['payload'], self::$settings ),
			'Assert not shown after reader donated.'
		);
	}

	/**
	 * Suppression caused by a reading count segment.
	 */
	public function test_segment_read_count_range() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => 'segmentBetween3And5',
			]
		);
		$client_id               = 'amp-123';

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert initially not visible.'
		);

		$post_read_item = [
			'post_id'      => '142',
			'category_ids' => '',
		];

		// Report 2 articles read.
		self::$maybe_show_campaign->save_client_data(
			$client_id,
			[
				'posts_read' => [
					$post_read_item,
					$post_read_item,
				],
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert shown when a second article is read.'
		);

		// Report more than 3 articles read.
		self::$maybe_show_campaign->save_client_data(
			$client_id,
			[
				'posts_read' => [
					$post_read_item,
					$post_read_item,
					$post_read_item,
					$post_read_item,
				],
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert not shown when more than three articles were read.'
		);
	}

	/**
	 * Suppression caused by a reading count segment.
	 */
	public function test_segment_read_count_zeros() {
		$test_popup_with_segment = self::create_test_popup(
			[
				'placement'           => 'inline',
				'frequency'           => 'always',
				'selected_segment_id' => 'segmentWithZeros',
			]
		);
		$client_id               = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_with_segment['payload'], self::$settings ),
			'Assert visible.'
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
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert visible, since there is no such segment.'
		);
	}
}
