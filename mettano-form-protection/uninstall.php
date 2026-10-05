<?php
/**
 * Runs when the plugin is deleted from Plugins → Delete.
 * Removes settings and the log folder.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'mfp_settings' );
delete_option( 'mfp_log_key' );

$mfp_dir = WP_CONTENT_DIR . '/mfp-logs';
if ( is_dir( $mfp_dir ) ) {
	foreach ( scandir( $mfp_dir ) as $mfp_file ) {
		if ( is_file( $mfp_dir . '/' . $mfp_file ) ) {
			unlink( $mfp_dir . '/' . $mfp_file );
		}
	}
	rmdir( $mfp_dir );
}
