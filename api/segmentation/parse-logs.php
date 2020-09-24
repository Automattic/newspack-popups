<?php

$start_time = microtime( true );

$visits_table_name    = $argv[1];
$clients_table_name   = $argv[2];
$log_file_path        = $argv[3];
$is_parsing_file_path = $argv[4];

file_put_contents( $is_parsing_file_path, '' ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

$_SERVER['SCRIPT_FILENAME'] = dirname( __FILE__ );

require_once dirname( __FILE__ ) . '/../setup.php';

$log_file = fopen( $log_file_path, 'r' );
$lines    = [];

while ( ! feof( $log_file ) ) {
	$line = trim( fgets( $log_file ) );
	if ( ! empty( $line ) ) {
		$lines[] = $line;
	}
}

error_log( 'Parsing ' . count( $lines ) . ' lines of log file: ' . $log_file_path );

$lines = array_unique( $lines );

foreach ( $lines as $line ) {
	$result    = explode( ';', $line );
	$client_id = $result[0];
	$post_id   = $result[2];

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$found_client = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $clients_table_name WHERE client_id = %s", $client_id ) );

	if ( null === $found_client ) {
		$updates = [
			'client_id'  => $client_id,
			'created_at' => gmdate( 'Y-m-d' ),
			'updated_at' => gmdate( 'Y-m-d' ),
		];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$clients_table_name,
			$updates
		);
	} else {
		$updates = [
			'updated_at' => gmdate( 'Y-m-d' ),
		];
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$clients_table_name,
			$updates,
			[ 'ID' => $found_client->id ]
		);
	}

	$existing_post_visits = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare( "SELECT * FROM $visits_table_name WHERE post_id = %s AND client_id = %s", $post_id, $client_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	);
	if ( null === $existing_post_visits ) {
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$visits_table_name,
			[
				'client_id'      => $client_id,
				'created_at'     => $result[1],
				'post_id'        => $post_id,
				'categories_ids' => $result[3],
			]
		);
	}
}

fclose( $log_file );

// Clear the log file.
file_put_contents( $log_file_path, '' );

// Remove the is-parsing file to enable logging again.
unlink( $is_parsing_file_path );

error_log( 'parsing duration: ' . round( ( microtime( true ) - $start_time ) * 1000 ) . 'ms' );
