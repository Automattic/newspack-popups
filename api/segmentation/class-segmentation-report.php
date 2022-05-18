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
	 * @param array $events Array of reader events to log.
	 *              ['client_id'] Client ID associated with the event.
	 *              ['date_created'] Timestamp of the event. Optional.
	 *              ['type'] Type of event.
	 *              ['event_value'] Data associated with the event.
	 */
	public static function log_reader_events( $events ) {
		$lines = '';
		foreach ( $events as $event ) {
			// Add line to log file.
			$line = implode(
				'|',
				[
					$event['client_id'],
					isset( $event['date_created'] ) ? $event['date_created'] : gmdate( 'Y-m-d H:i:s', time() ),
					$event['type'],
					isset( $event['event_value'] ) ? maybe_serialize( $event['event_value'] ) : '',
					isset( $event['is_preview'] ) ? 1 : 0,
				]
			);

			$lines .= $line . PHP_EOL;
		}

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			Segmentation::get_log_file_path(),
			$lines,
			FILE_APPEND
		);
	}
}
