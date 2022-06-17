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
	 *              ['context'] Context of event.
	 *              ['value'] Data associated with the event.
	 */
	public static function log_reader_events( $events ) {
		$lines = '';
		foreach ( $events as $event ) {
			// Add line to log file.
			if ( ! empty( $event['client_id'] ) ) {
				$line = implode(
					'|',
					[
						isset( $event['id'] ) ? $event['id'] : '',
						$event['client_id'],
						isset( $event['type'] ) ? $event['type'] : '',
						isset( $event['context'] ) ? $event['context'] : '',
						isset( $event['value'] ) ? $event['value'] : '',
					]
				);

				$lines .= $line . PHP_EOL;
			}
		}

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
			Segmentation::get_log_file_path(),
			$lines,
			FILE_APPEND
		);
	}
}
