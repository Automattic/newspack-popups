<?php
/**
 * Campaigns Export CLI command.
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use Newspack_Popups_Exporter;

/**
 * Campaigns Export CLI command.
 */
class Export extends WP_CLI_Command {

	/**
	 * Export Campaigns, Segments and Prompts.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : The name of the output file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-campaigns export --file=newspack-campaigns.json
	 *
	 * @param array $args Args.
	 * @param array $assoc_args Assoc args.
	 */
	public function __invoke( $args, $assoc_args ) {

		$output = isset( $assoc_args['file'] ) ? $assoc_args['file'] : 'newspack-campaigns-' . gmdate( 'Y-m-d-H-i-s' ) . '.json';

		$exporter = new Newspack_Popups_Exporter();
		$data     = $exporter->export();

		foreach ( $data['errors'] as $error_type => $error_group ) {
			if ( empty( $error_group ) ) {
				continue;
			}
			WP_CLI::warning( $error_type );
			foreach ( $error_group as $error_id => $error ) {
				WP_CLI::warning( '-- ' . $error_id );
				foreach ( $error as $message ) {
					WP_CLI::warning( '---- ' . $message );
				}
			}
		}

		WP_CLI::success( $data['totals']['campaigns'] . ' campaigns exported.' );
		WP_CLI::success( $data['totals']['segments'] . ' segments exported.' );
		WP_CLI::success( $data['totals']['prompts'] . ' prompts exported.' );

		file_put_contents( $output, wp_json_encode( $data['data'], JSON_PRETTY_PRINT ) ); // phpcs:ignore

		WP_CLI::success( 'Exported to ' . $output );

	}
}
