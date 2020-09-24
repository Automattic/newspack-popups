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
		$this->api_handle_visit( $this->get_post_payload() );
		$this->respond();
	}

	/**
	 * Add visit data to the table.
	 *
	 * @param string $client_id Client ID.
	 * @param object $visit_data visit data.
	 */
	public function log_visit_data( $client_id, $visit_data ) {
		if ( file_exists( Segmentation::IS_PARSING_FILE_PATH ) ) {
			return;
		}

		// Add line to log file.
		$line = implode(
			';',
			[
				$client_id,
				$visit_data['date'],
				$visit_data['post_id'],
				$visit_data['categories_ids'],
			]
		);

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			Segmentation::LOG_FILE_PATH,
			$line . PHP_EOL,
			FILE_APPEND
		);
	}

	/**
	 * Handle visitor.
	 *
	 * May or may not add a visit to the visits table – ideally the request should be made
	 * only on single posts, but in order to make amp-analytics set the client ID cookie,
	 * the code has to be placed on each page. There may be a better solution, as that non-visit-adding
	 * request is not necessary.
	 *
	 * @param object $payload a payload.
	 */
	public function api_handle_visit( $payload ) {
		$add_visit = $payload['add_visit'];
		if ( '1' === $add_visit ) {
			$client_id          = $payload['clientId'];
			$visit_data_payload = [
				'post_id'        => $payload['id'],
				'date'           => gmdate( 'Y-m-d', time() ),
				'categories_ids' => $payload['categories'],
			];
			self::log_visit_data( $client_id, $visit_data_payload );
		}
	}
}

new Segmentation_Report();
