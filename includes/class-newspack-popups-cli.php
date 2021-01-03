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
			[ 'Newspack_Popups_Utils', 'update_campaigns' ],
			[
				'shortdesc' => 'Update campaign settings.',
			]
		);
	}
}
$newspack_popups_cli = new Newspack_Popups_CLI();
