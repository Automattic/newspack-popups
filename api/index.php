<?php
/**
 * Route Newspack Campaigns API requests based on method.
 *
 * @package Newspack
 */

define( 'ABSPATH', str_replace( 'wp-content/plugins/newspack-popups/api/index.php', '', $_SERVER['SCRIPT_FILENAME'] ) ); // phpcs:ignore
define( 'WPINC', 'wp-includes/' );
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content/' );
}
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

require_once ABSPATH . 'newspack-popups-config.php';

// phpcs:disable

function __( $in ) { return $in; }
function wp_load_translations_early() {return null;}
function add_filter() {}
function do_action() {}
function has_filter() { return false;}
function apply_filters( $f, $in ) { return $in; }
function is_multisite() { return false;}
function wp_suspend_cache_addition() { return false; }
function wp_debug_backtrace_summary() { return false; }

if ( file_exists( ABSPATH . WPINC . '/wp-db.php' ) ) {
	require_once ABSPATH . WPINC . '/wp-db.php';
} else if ( file_exists( ABSPATH . '__wp__/' . WPINC . '/wp-db.php' ) ) {
	require_once ABSPATH . '__wp__/' . WPINC . '/wp-db.php';
} else {
	die( "{ error: 'no_wordpress' }" );
}

if ( file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ) {
	require WP_CONTENT_DIR . '/object-cache.php';
} else {
	require_once ABSPATH . WPINC . '/cache.php';
}

$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$wpdb->set_prefix( DB_PREFIX );

global $table_prefix;
$table_prefix = DB_PREFIX;

wp_cache_init();

// phpcs:enable

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'GET':
		include 'classes/class-maybe-show-campaign.php';
		break;
	case 'POST':
		include 'classes/class-dismiss-campaign.php';
		break;
}
