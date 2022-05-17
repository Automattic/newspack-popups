<?php
/**
 * Newspack Popups Segmentation Report
 *
 * @package Newspack
 */

/**
 * Manages reporting data for segmentation features.
 */
class Segmentation_Report {
	/**
	 * Handle visitor.
	 *
	 * @param object $event A reader event to log.
	 *               $event['client_id'] Client ID associated with the event.
	 *               $event['date_created'] Timestamp of the event. Optional.
	 *               $event['event_type'] Type of event.
	 *               $event['event_value'] Data associated with the event.
	 */
	public static function log_reader_event( $event ) {
		// Add line to log file.
		$line = implode(
			';',
			[
				$event['client_id'],
				isset( $event['date_created'] ) ? $event['date_created'] : gmdate( 'Y-m-d H:i:s', time() ),
				$event['event_type'],
				isset( $event['event_value'] ) ? maybe_serialize( $event['event_value'] ) : '',
			]
		);

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			Segmentation::get_log_file_path(),
			$line . PHP_EOL,
			FILE_APPEND
		);
	}
}
