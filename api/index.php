<?php
/**
 * Route Newspack Campaigns API requests based on method.
 *
 * @package Newspack
 */

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'GET':
		include 'classes/class-maybe-show-campaign.php';
		break;
	case 'POST':
		include 'classes/class-dismiss-campaign.php';
		break;
}
