<?php
/**
 * Campaigns Import CLI command.
 *
 * @package Newspack
 */

namespace Newspack\Campaigns\CLI;

use \WP_CLI;
use \WP_CLI_Command;
use Newspack_Popups_Importer;

/**
 * Campaigns Import CLI command.
 */
class Import extends WP_CLI_Command {

	/**
	 * Import Campaigns, Segments and Prompts.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The name of the input file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp newspack-popups import newspack-campaigns.json
	 *
	 * @param array $args Args.
	 * @param array $assoc_args Assoc args.
	 */
	public function __invoke( $args, $assoc_args ) {

		$file = $args[0];
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( 'File not found or not readable.' );
		}

		$data = wp_json_file_decode( $file, [ 'associative' => true ] ); // phpcs:ignore
		if ( empty( $data ) ) {
			WP_CLI::error( 'File is not valid JSON.' );
		}

		$importer = new Newspack_Popups_Importer( $data );
		$result   = $importer->import();

		if ( ! empty( $result['errors'] ) && ! empty( $result['errors']['validation'] ) ) {
			WP_CLI::warning( 'JSON could not be validated.' );
			foreach ( $result['errors']['validation'] as $message ) {
				WP_CLI::warning( $message );
			}
			WP_CLI::error( 'Could not import JSON file.' );
		}

		foreach ( $result['errors'] as $error_type => $error_group ) {
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

		WP_CLI::success( $result['totals']['campaigns'] . ' campaigns imported.' );
		WP_CLI::success( $result['totals']['segments'] . ' segments imported.' );
		WP_CLI::success( $result['totals']['prompts'] . ' prompts imported.' );
		WP_CLI::success( 'Import complete!' );

	}
}
