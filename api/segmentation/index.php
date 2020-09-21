<?php
/**
 * Route Newspack Campaigns Segmentation API requests based on method.
 *
 * @package Newspack
 */

require_once '../setup.php';

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'POST':
		include 'class-segmentation-report.php';
		break;
	default:
		die( "{ error: 'unsupported_method' }" );
}
