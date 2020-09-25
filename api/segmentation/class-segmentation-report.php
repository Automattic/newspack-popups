<?php
/**
 * Newspack Popups Segmentation Report
 *
 * @package Newspack
 */

/**
 * Extend the base Lightweight_API class.
 */
require_once dirname( __FILE__ ) . '/../classes/class-lightweight-api.php';

/**
 * Manages Segmentation.
 */
class Segmentation_Report extends Lightweight_API {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->api_handle_post_read( $this->get_post_payload() );
		$this->respond();
	}

	/**
	 * Handle visitor.
	 *
	 * May or may not add a post read to the events table – ideally the request should be made
	 * only on single posts, but in order to make amp-analytics set the client ID cookie,
	 * the code has to be placed on each page. There may be a better solution, as that non-event-adding
	 * request is not necessary.
	 *
	 * @param object $payload a payload.
	 */
	public function api_handle_post_read( $payload ) {
		if ( file_exists( Segmentation::IS_PARSING_FILE_PATH ) ) {
			return;
		}

		$is_post = $payload['is_post'];
		if ( '1' === $is_post ) {
			// Add line to log file.
			$line = implode(
				';',
				[
					'post_read',
					$payload['clientId'],
					gmdate( 'Y-m-d', time() ),
					$payload['id'],
					$payload['categories'],
				]
			);

			file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
				Segmentation::LOG_FILE_PATH,
				$line . PHP_EOL,
				FILE_APPEND
			);
		}
	}
}

new Segmentation_Report();
