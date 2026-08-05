<?php
/**
 * Plugin Name:     Newspack Campaigns (WRONG VERSION)
 * Plugin URI:      https://newspack.com
 * Description:     This plugin was downloaded from the legacy plugin repo. Please download the latest version from https://github.com/Automattic/newspack-workspace.
 * Author:          Automattic
 * Author URI:      https://newspack.com
 * Text Domain:     newspack-popups
 * Domain Path:     /languages
 * Version:         3.12.1
 *
 * @package         Newspack_Popups
 */

defined( 'ABSPATH' ) || exit;

// Define the plugin file path.
if ( ! defined( 'NEWSPACK_POPUPS_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_POPUPS_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/vendor/autoload.php';

// Include the main Newspack Google Ad Manager class.
if ( ! class_exists( 'Newspack_Popups' ) ) {
	include_once __DIR__ . '/includes/class-newspack-popups.php';
}
