<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all RSL options from the database
$rsl_options_to_delete = array(
	'rsl_global_license_id',
	'rsl_enable_html_injection',
	'rsl_enable_http_headers',
	'rsl_enable_robots_txt',
	'rsl_enable_rss_feed',
	'rsl_enable_media_metadata',
	'rsl_default_namespace',
);

foreach ( $rsl_options_to_delete as $rsl_option ) {
	delete_option( $rsl_option );
	delete_site_option( $rsl_option );
}

// Remove all post meta related to RSL licensing
delete_metadata( 'post', 0, '_rsl_license_id', '', true );
delete_metadata( 'post', 0, '_rsl_override_content_url', '', true );

// Ask user if they want to remove the licenses table and data
$rsl_remove_data = get_option( 'rsl_remove_data_on_uninstall', false );

if ( $rsl_remove_data ) {
	global $wpdb;

	$rsl_table_name = $wpdb->prefix . 'rsl_licenses';

	// Drop the licenses table. The table name comes from $wpdb->prefix, not user input,
	// and identifier placeholders (%i) need WordPress 6.2 while the plugin supports 5.0.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS `$rsl_table_name`" );

	// Remove the cleanup option itself
	delete_option( 'rsl_remove_data_on_uninstall' );
}
