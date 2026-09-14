<?php
/**
 * External image handler for OneWebP.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OneWebP_External
 */
class OneWebP_External {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'onewebp_process_external_image', array( $this, 'handle_external' ), 10, 2 );
	}

	/**
	 * Handle external image conversion.
	 *
	 * @param string $url  Image URL.
	 * @param string $html HTML content.
	 * @return string Modified HTML.
	 */
	public function handle_external( $url, $html ) {
		if ( ! get_option( 'onewebp_enable_external', 0 ) ) {
			return $html;
		}

		global $wpdb;
		$cache_table = $wpdb->prefix . 'onewebp_external_cache';

		$cached = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT local_webp_url FROM {$cache_table} WHERE original_url = %s",
				$url
			)
		);

		if ( $cached ) {
			return str_replace( $url, esc_url( $cached->local_webp_url ), $html );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 15,
				'limit_response_size' => 5 * 1024 * 1024,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $html;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return $html;
		}

		$image_data   = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		$allowed_types = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		$valid_type    = false;
		foreach ( $allowed_types as $type ) {
			if ( strpos( $content_type, $type ) !== false ) {
				$valid_type = true;
				break;
			}
		}

		if ( ! $valid_type ) {
			return $html;
		}

		$image_size = strlen( $image_data );
		if ( $image_size > 5 * 1024 * 1024 || 0 === $image_size ) {
			return $html;
		}

		$upload_dir    = wp_upload_dir();
		$ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
		if ( ! file_exists( $ext_cache_dir ) ) {
			wp_mkdir_p( $ext_cache_dir );
		}

		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);

		$ext       = isset( $ext_map[ $content_type ] ) ? $ext_map[ $content_type ] : 'jpg';
		$filename  = md5( $url ) . '.' . $ext;
		$temp_path = $ext_cache_dir . $filename;

		if ( false === file_put_contents( $temp_path, $image_data ) ) {
			return $html;
		}

		$webp_path = $temp_path . '.jo.webp';
		$converter = new OneWebP_Converter();
		$result    = $converter->execute_conversion( $temp_path, $webp_path );

		if ( $result['success'] && file_exists( $webp_path ) ) {
			$local_webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $webp_path );

			$wpdb->insert(
				$cache_table,
				array(
					'original_url'   => $url,
					'local_webp_url' => $local_webp_url,
				)
			);

			@unlink( $temp_path );
			return str_replace( $url, esc_url( $local_webp_url ), $html );
		}

		@unlink( $temp_path );
		if ( file_exists( $webp_path ) ) {
			@unlink( $webp_path );
		}

		return $html;
	}
}