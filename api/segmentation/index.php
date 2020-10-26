<?php // phpcs:ignore Squiz.Commenting.FileComment.Missing
/**
 * Route Newspack Campaigns API requests based on method.
 *
 * @package Newspack
 */

require_once '../setup.php';

switch ( $_SERVER['REQUEST_METHOD'] ) { //phpcs:ignore
	case 'GET':
		include './class-report-client-data.php';
		break;
	default:
		die( "{ error: 'unsupported_method' }" );
}
