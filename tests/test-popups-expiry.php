<?php
/**
 * Class Test_Newspack_Popups_Expiry
 *
 * @package Newspack_Popups
 */

/**
 * Prompt expiry test case.
 */
class Test_Newspack_Popups_Expiry extends WP_UnitTestCase {

	/**
	 * A single example test.
	 */
	public function test_prompt_expiry_constants() {
		$this->assertEquals( 'newspack_popups_check_expiry', Newspack_Popups_Expiry::CRON_HOOK );
	}

	/**
	 * Test the init function.
	 */
	public function test_prompt_expiry_init() {
		Newspack_Popups_Expiry::init();
		$this->assertEquals( 10, has_action( 'transition_post_status', [ 'Newspack_Popups_Expiry', 'transition_post_status' ] ) );
		$this->assertEquals( 10, has_action( Newspack_Popups_Expiry::CRON_HOOK, [ 'Newspack_Popups_Expiry', 'revert_expired_to_draft' ] ) );
	}

	/**
	 * Test the revert_expired_to_draft function.
	 */
	public function test_prompt_expiry_revert_expired_to_draft() {
		// Create a post with an expiration date in the past.
		$post_id = $this->factory->post->create(
			[
				'post_type' => Newspack_Popups::NEWSPACK_POPUPS_CPT,
			]
		);
		// Set post expiration date meta - for some reason setting this as 'meta_input' while post status is publish does not work.
		update_post_meta( $post_id, 'expiration_date', gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) ) );

		$this->assertEquals( 'publish', get_post_status( $post_id ) );

		// This will run in a cron job.
		Newspack_Popups_Expiry::revert_expired_to_draft();

		$this->assertEquals( 'draft', get_post_status( $post_id ) );
	}

	/**
	 * Test the transition_post_status function.
	 */
	public function test_prompt_expiry_transition_post_status() {
		// Create a post with an expiration date in the past.
		$expiration_date = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day' ) );
		$post_id = $this->factory->post->create(
			[
				'post_type'   => Newspack_Popups::NEWSPACK_POPUPS_CPT,
				'post_status' => 'draft',
				// meta_input only works if the post_status is not 'publish'.
				'meta_input'  => [
					'expiration_date' => $expiration_date,
				],
			]
		);
		$this->assertEquals( get_post_meta( $post_id, 'expiration_date', true ), $expiration_date );

		// Publish the post.
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			]
		);

		// Check that the expiration_date meta is now deleted.
		$this->assertEmpty( get_post_meta( $post_id, 'expiration_date', true ) );
	}
}
