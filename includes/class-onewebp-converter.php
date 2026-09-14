<?php
/**
 * Image converter class for WebP conversion with LQIP support.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OneWebP_Converter
 */
class OneWebP_Converter {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_upload' ), 10, 2 );
	}

	/**
	 * Process a newly uploaded attachment and convert it to WebP.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata.
	 */
	public function process_upload( $metadata, $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path ) {
			return $metadata;
		}

		$file_dir      = dirname( $file_path );
		$scope         = get_option( 'onewebp_conversion_scope', 'all' );
		$allowed_types = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) );
		$enable_lqip   = (bool) get_option( 'onewebp_enable_progressive', 1 );

		$mime     = get_post_mime_type( $attachment_id );
		$type_key = str_replace( 'image/', '', $mime );
		if ( 'jpg' === $type_key ) {
			$type_key = 'jpeg';
		}

		if ( ! in_array( $type_key, $allowed_types, true ) ) {
			return $metadata;
		}

		// Convert the original image.
		if ( 'original' === $scope || 'all' === $scope ) {
			$this->convert_and_log( $file_path, $file_path . '.jo.webp', $attachment_id, 'original' );
			if ( $enable_lqip ) {
				$this->generate_lqip( $file_path, $attachment_id, 'original' );
			}
		}

		// Convert thumbnails only when scope is "all".
		if ( 'all' === $scope ) {
			if ( ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					$size_path = $file_dir . '/' . $size_data['file'];
					if ( file_exists( $size_path ) ) {
						$this->convert_and_log( $size_path, $size_path . '.jo.webp', $attachment_id, $size_name );
					}
				}
			}
		}

		// Handle delete_original mode.
		$mode = get_option( 'onewebp_image_deletion_mode', 'sync' );
		if ( 'delete_original' === $mode ) {
			OneWebP_Core::get_instance()->replace_original_with_webp( $attachment_id );
		}

		return $metadata;
	}

	/**
	 * Generate a low-quality image placeholder (LQIP).
	 *
	 * @param string $source_path   Source image path.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name     Size name.
	 * @return void
	 */
	public function generate_lqip( $source_path, $attachment_id, $size_name = 'original' ) {
		if ( ! file_exists( $source_path ) ) {
			return;
		}

		$lqip_path = $source_path . '.lqip.webp';
		if ( file_exists( $lqip_path ) ) {
			return;
		}

		$image_info = @getimagesize( $source_path );
		if ( empty( $image_info ) ) {
			return;
		}

		list( $orig_w, $orig_h, $orig_type ) = $image_info;
		if ( ! $orig_w || ! $orig_h ) {
			return;
		}

		$lqip_max = 12;
		if ( $orig_w > $orig_h ) {
			$lqip_w = $lqip_max;
			$lqip_h = round( $orig_h * ( $lqip_max / $orig_w ) );
		} else {
			$lqip_h = $lqip_max;
			$lqip_w = round( $orig_w * ( $lqip_max / $orig_h ) );
		}
		$lqip_w = max( 1, $lqip_w );
		$lqip_h = max( 1, $lqip_h );

		$source_image = $this->load_image( $source_path, $orig_type );
		if ( ! $source_image ) {
			return;
		}

		$lqip_image = imagecreatetruecolor( $lqip_w, $lqip_h );
		if ( ! $lqip_image ) {
			imagedestroy( $source_image );
			return;
		}

		$this->handle_transparency( $lqip_image, $orig_type );
		imagecopyresampled( $lqip_image, $source_image, 0, 0, 0, 0, $lqip_w, $lqip_h, $orig_w, $orig_h );
		$success = imagewebp( $lqip_image, $lqip_path, 8 );

		imagedestroy( $source_image );
		imagedestroy( $lqip_image );

		if ( $success ) {
			$upload_dir = wp_upload_dir();
			$lqip_url   = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $lqip_path );

			$meta = get_post_meta( $attachment_id, '_onewebp_lqip', true );
			if ( ! is_array( $meta ) ) {
				$meta = array();
			}
			$meta[ $size_name ] = $lqip_url;
			update_post_meta( $attachment_id, '_onewebp_lqip', $meta );
		}
	}

	/**
	 * Load an image resource from a file path.
	 *
	 * @param string $source_path Source image path.
	 * @param int    $type        Image type constant.
	 * @return resource|false
	 */
	private function load_image( $source_path, $type ) {
		switch ( $type ) {
			case IMAGETYPE_JPEG:
				return imagecreatefromjpeg( $source_path );
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng( $source_path );
				if ( $image ) {
					imagepalettetotruecolor( $image );
					imagealphablending( $image, true );
					imagesavealpha( $image, true );
				}
				return $image;
			case IMAGETYPE_GIF:
				return imagecreatefromgif( $source_path );
			case IMAGETYPE_WEBP:
				return imagecreatefromwebp( $source_path );
			default:
				return false;
		}
	}

	/**
	 * Handle transparency for PNG, GIF, and WebP images.
	 *
	 * @param resource $image Image resource.
	 * @param int      $type  Image type constant.
	 * @return void
	 */
	private function handle_transparency( $image, $type ) {
		if ( IMAGETYPE_PNG === $type || IMAGETYPE_GIF === $type || IMAGETYPE_WEBP === $type ) {
			$transparent = imagecolorallocatealpha( $image, 0, 0, 0, 127 );
			imagecolortransparent( $image, $transparent );
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}
	}

	/**
	 * Convert an image and log the result.
	 *
	 * @param string $source_path   Source image path.
	 * @param string $dest_path     Destination WebP path.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name     Size name.
	 * @return void
	 */
	private function convert_and_log( $source_path, $dest_path, $attachment_id, $size_name ) {
		global $wpdb;

		$original_size = file_exists( $source_path ) ? filesize( $source_path ) : 0;
		$result        = $this->execute_conversion( $source_path, $dest_path );
		$status        = $result['success'] ? 'success' : 'failed';
		$webp_size     = $result['success'] && file_exists( $dest_path ) ? filesize( $dest_path ) : 0;

		$wpdb->insert(
			$wpdb->prefix . 'onewebp_logs',
			array(
				'attachment_id' => $attachment_id,
				'image_type'    => 'local',
				'size_name'     => $size_name,
				'original_url'  => $source_path,
				'webp_url'      => $dest_path,
				'original_size' => $original_size,
				'webp_size'     => $webp_size,
				'is_downscaled' => ! empty( $result['is_downscaled'] ) ? 1 : 0,
				'status'        => $status,
			)
		);
	}

	/**
	 * Execute the WebP conversion.
	 *
	 * @param string   $source_path             Source image path.
	 * @param string   $dest_path               Destination WebP path.
	 * @param int|null $custom_quality          Optional custom quality.
	 * @param int|null $custom_max_res          Optional custom max resolution.
	 * @param int|null $custom_allow_oversized  Optional oversized allowance.
	 * @return array Result array with success flag.
	 */
	public function execute_conversion( $source_path, $dest_path, $custom_quality = null, $custom_max_res = null, $custom_allow_oversized = null ) {
		if ( ! file_exists( $source_path ) ) {
			return array(
				'success' => false,
				'error'   => 'Source file not found',
			);
		}

		$dest_dir = dirname( $dest_path );
		if ( ! is_writable( $dest_dir ) ) {
			return array(
				'success' => false,
				'error'   => 'Destination directory not writable',
			);
		}

		$quality = $this->sanitize_quality(
			null !== $custom_quality ? intval( $custom_quality ) : intval( get_option( 'onewebp_quality', 82 ) )
		);

		$max_res   = null !== $custom_max_res ? intval( $custom_max_res ) : intval( get_option( 'onewebp_max_resolution', 3000 ) );
		$allow_big = null !== $custom_allow_oversized ? intval( $custom_allow_oversized ) : get_option( 'onewebp_allow_oversized', 0 );

		$this->ensure_memory_for_image( $source_path );

		list( $orig_w, $orig_h, $orig_type ) = @getimagesize( $source_path );
		if ( ! $orig_w ) {
			return array(
				'success' => false,
				'error'   => 'Failed to get image dimensions',
			);
		}

		$target_w      = $orig_w;
		$target_h      = $orig_h;
		$is_downscaled = false;

		if ( ! $allow_big && ( $orig_w > $max_res || $orig_h > $max_res ) ) {
			$is_downscaled = true;
			if ( $orig_w > $orig_h ) {
				$target_w = $max_res;
				$target_h = round( $orig_h * ( $max_res / $orig_w ) );
			} else {
				$target_h = $max_res;
				$target_w = round( $orig_w * ( $max_res / $orig_h ) );
			}
		}

		if ( IMAGETYPE_GIF === $orig_type && $this->is_animated_gif( $source_path ) ) {
			return array(
				'success' => false,
				'error'   => 'Animated GIF not supported',
			);
		}

		$source_image = $this->load_image( $source_path, $orig_type );
		if ( ! $source_image ) {
			return array(
				'success' => false,
				'error'   => 'Failed to load image',
			);
		}

		$new_image = imagecreatetruecolor( $target_w, $target_h );
		if ( ! $new_image ) {
			imagedestroy( $source_image );
			return array(
				'success' => false,
				'error'   => 'Failed to create canvas',
			);
		}

		$this->handle_transparency( $new_image, $orig_type );
		imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h );
		$success = imagewebp( $new_image, $dest_path, $quality );

		imagedestroy( $source_image );
		imagedestroy( $new_image );

		return array(
			'success'      => $success,
			'is_downscaled' => $is_downscaled,
		);
	}

	/**
	 * Sanitize the quality value.
	 *
	 * @param int $quality Raw quality value.
	 * @return int Sanitized quality between 50 and 100.
	 */
	private function sanitize_quality( $quality ) {
		$quality = intval( $quality );
		if ( $quality < 50 ) {
			return 50;
		}
		if ( $quality > 100 ) {
			return 100;
		}
		return $quality;
	}

	/**
	 * Check whether a GIF file is animated.
	 *
	 * @param string $filename GIF file path.
	 * @return bool True if animated, false otherwise.
	 */
	private function is_animated_gif( $filename ) {
		if ( ! function_exists( 'file_get_contents' ) ) {
			return false;
		}
		$contents = file_get_contents( $filename );
		if ( ! $contents ) {
			return false;
		}
		$frames = preg_match_all( '/\x00\x21\xF9\x04/', $contents );
		return $frames > 1;
	}

	/**
	 * Ensure the PHP memory limit is high enough for the image.
	 *
	 * @param string $source_path Source image path.
	 * @return void
	 */
	private function ensure_memory_for_image( $source_path ) {
		$image_size      = file_exists( $source_path ) ? filesize( $source_path ) : 0;
		$required_memory = $image_size * 6;

		$memory_limit = ini_get( 'memory_limit' );
		if ( '-1' === $memory_limit ) {
			return;
		}

		$memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
		if ( $memory_bytes > 0 && $required_memory > $memory_bytes * 0.7 ) {
			$new_limit = ceil( $required_memory / 1024 / 1024 ) + 64;
			@ini_set( 'memory_limit', $new_limit . 'M' );
		}
	}
}