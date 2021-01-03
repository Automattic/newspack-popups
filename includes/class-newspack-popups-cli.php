<?php
/**
 * Newspack Popups CLI commands.
 *
 * @package Newspack
 */

defined( 'ABSPATH' ) || exit;

/**
 * Newspack Popups CLI commands.
 */
final class Newspack_Popups_CLI {
	/**
	 * Constructor.
	 */
	public function __construct() {
		WP_CLI::add_command(
			'newspack_popups update_campaigns',
			[ $this, 'cli_update_campaigns' ],
			[
				'shortdesc' => 'Update campaign settings.',
			]
		);
	}

	/**
	 * CLI command to update campaign options.
	 *
	 * @param array $args WPCLI args.
	 * @param array $assoc_args WPCLI associative args.
	 */
	public function cli_update_campaigns( $args, $assoc_args ) {
		$reset  = isset( $assoc_args['reset'] );
		$utils  = new Newspack_Popups_Utils();
		$result = $utils->update_campaigns( $reset );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message(), $exit = true );
		}
		WP_CLI::success( __( 'Campaigns updated successfully!', 'newspack-popups' ) );
	}
}
$newspack_popups_cli = new Newspack_Popups_CLI();
