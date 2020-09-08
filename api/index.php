<?php
/**
 * Route Newspack Campaigns API requests based on method.
 *
 * @package Newspack
 */

define( 'ABSPATH', $_SERVER[ 'DOCUMENT_ROOT'] . '/' ); // phpcs:ignore
define( 'WPINC', 'wp-includes/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content/' );
define( 'WP_DEBUG', false );

require_once ABSPATH . 'newspack-popups-config.php';

// phpcs:disable

function __( $in ) { return $in; }
function wp_load_translations_early() {return null;}
function add_filter() {}
function do_action() {}
function has_filter() { return false;}
function apply_filters( $f, $in ) { return $in; }
function is_multisite() { return false;}

require_once ABSPATH . WPINC . '/wp-db.php';

$wpdb = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$wpdb->set_prefix( DB_PREFIX );

// phpcs:enable

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'GET':
		include 'classes/class-maybe-show-campaign.php';
		break;
	case 'POST':
		include 'classes/class-dismiss-campaign.php';
		break;
}
