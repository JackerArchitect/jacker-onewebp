<?php
/**
 * OneWebP uninstall handler.
 *
 * @package OneWebP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop tables.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_external_cache" );

// Delete all options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'onewebp_%'" );

// Delete all transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_onewebp_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_onewebp_%'" );

// Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_onewebp_lqip'" );

// Delete WebP files created by OneWebP.
$upload_dir = wp_upload_dir();
$base_dir   = str_replace( '\\', '/', $upload_dir['basedir'] );

if ( is_dir( $base_dir ) ) {
	try {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}

			$path      = str_replace( '\\', '/', $file->getPathname() );
			$file_name = basename( $path );

			// Only delete .jo.webp and .lqip.webp.
			if ( preg_match( '/\.jo\.webp$/i', $file_name ) || preg_match( '/\.lqip\.webp$/i', $file_name ) ) {
				@unlink( $path );
			}
		}
	} catch ( Exception $e ) {
		// Ignore.
	}
}

// Delete external cache directory.
$ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
if ( is_dir( $ext_cache_dir ) ) {
	$ext_files = glob( $ext_cache_dir . '*' );
	if ( is_array( $ext_files ) ) {
		foreach ( $ext_files as $file ) {
			if ( is_file( $file ) ) {
				@unlink( $file );
			}
		}
	}
	@rmdir( $ext_cache_dir );
}