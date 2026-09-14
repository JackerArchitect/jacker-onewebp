<?php
/**
 * Smart batch processing for OneWebP.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OneWebP_Smart_Batch
 */
class OneWebP_Smart_Batch {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_onewebp_run_batch', array( $this, 'handle_ajax_batch' ) );
		add_action( 'wp_ajax_onewebp_get_stats', array( $this, 'handle_ajax_get_stats' ) );
		add_action( 'wp_ajax_onewebp_scan_library', array( $this, 'handle_ajax_scan_library' ) );
		add_action( 'wp_ajax_onewebp_reset_data', array( $this, 'handle_ajax_reset_data' ) );
		add_action( 'wp_ajax_onewebp_dismiss_mode_notice', array( $this, 'handle_dismiss_mode_notice' ) );
	}

	/**
	 * Verify the AJAX nonce and user capability.
	 *
	 * @return void
	 */
	private function verify_nonce() {
		if ( ! isset( $_POST['nonce'] ) ) {
			wp_send_json_error( __( 'Missing nonce parameter.', 'onewebp' ) );
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'onewebp_nonce' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'onewebp' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'onewebp' ) );
		}
	}

	/**
	 * Return the list of MIME types that OneWebP can scan.
	 *
	 * @return array
	 */
	private function get_scannable_mime_types() {
		return array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
	}

	/**
	 * Get the conversion scope.
	 *
	 * @return string
	 */
	private function get_scope() {
		return get_option( 'onewebp_conversion_scope', 'all' );
	}

	/**
	 * Count all scannable files (original + thumbnails) in the media library.
	 *
	 * @return int Total number of files to scan.
	 */
	private function count_all_scannable_files() {
		$scope = $this->get_scope();

		$attachments = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_mime_type'   => $this->get_scannable_mime_types(),
				'post_status'      => 'inherit',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		$total = 0;

		foreach ( $attachments as $attachment_id ) {
			// Original file.
			$total++;

			// Thumbnail sizes (only when scope includes thumbnails).
			if ( 'all' === $scope ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
					$total += count( $metadata['sizes'] );
				}
			}
		}

		return $total;
	}

	/**
	 * Check whether a file path points to a native WebP image.
	 *
	 * @param string $file_path File path.
	 * @return bool
	 */
	private function is_native_webp( $file_path ) {
		$ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( 'webp' === $ext ) {
			return true;
		}

		$mime = wp_check_filetype( $file_path );
		return isset( $mime['type'] ) && 'image/webp' === $mime['type'];
	}

	/**
	 * Dismiss the mode change notice and store the user's choice.
	 *
	 * @return void
	 */
	public function handle_dismiss_mode_notice() {
		$this->verify_nonce();

		$mode_action = isset( $_POST['mode_action'] ) ? sanitize_text_field( wp_unslash( $_POST['mode_action'] ) ) : 'cancel';

		delete_transient( 'onewebp_show_mode_notice' );

		if ( 'convert_delete' === $mode_action ) {
			update_option( 'onewebp_batch_mode_override', 'delete_original' );
		} elseif ( 'convert_keep' === $mode_action ) {
			update_option( 'onewebp_batch_mode_override', 'sync' );
		}

		wp_send_json_success();
	}

	/**
	 * Return dashboard statistics.
	 *
	 * @return void
	 */
	public function handle_ajax_get_stats() {
		$this->verify_nonce();

		global $wpdb;
		$table = $wpdb->prefix . 'onewebp_logs';

		$total     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		$converted = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'success'" );
		$pending   = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
		$failed    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'failed'" );
		$native    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'native'" );

		$total_orig = (int) $wpdb->get_var( "SELECT COALESCE(SUM(original_size), 0) FROM {$table} WHERE status = 'success'" );
		$total_webp = (int) $wpdb->get_var( "SELECT COALESCE(SUM(webp_size), 0) FROM {$table} WHERE status = 'success'" );

		$saved_bytes = max( 0, $total_orig - $total_webp );
		$progress    = ( $total > 0 ) ? round( ( ( $converted + $native ) / $total ) * 100, 1 ) : 0;

		wp_send_json_success(
			array(
				'total'       => $total,
				'converted'   => $converted,
				'pending'     => $pending,
				'failed'      => $failed,
				'native'      => $native,
				'saved_bytes' => $saved_bytes,
				'progress'    => $progress,
			)
		);
	}

	/**
	 * Scan the media library in one shot.
	 *
	 * The whole library is scanned in a single AJAX request for speed.
	 * The frontend animates the file counter locally so the user still
	 * sees a real-time progress indicator.
	 *
	 * @return void
	 */
	public function handle_ajax_scan_library() {
		$this->verify_nonce();

		global $wpdb;
		$table = $wpdb->prefix . 'onewebp_logs';
		$scope = $this->get_scope();

		// Clear the log table before the scan.
		$wpdb->query( "TRUNCATE TABLE {$table}" );

		$attachments = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_mime_type'   => $this->get_scannable_mime_types(),
				'post_status'      => 'inherit',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		if ( empty( $attachments ) ) {
			wp_send_json_success(
				array(
					'done'        => true,
					'scanned'     => 0,
					'total_files' => 0,
				)
			);
		}

		foreach ( $attachments as $attachment_id ) {
			$attachment_id = (int) $attachment_id;

			// Original.
			$this->scan_attachment_size( $attachment_id, 'original', '', $table );

			// Thumbnails.
			if ( 'all' === $scope ) {
				$metadata = wp_get_attachment_metadata( $attachment_id );
				if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
					foreach ( $metadata['sizes'] as $size_name => $size_data ) {
						if ( ! empty( $size_data['file'] ) ) {
							$this->scan_attachment_size( $attachment_id, $size_name, $size_data['file'], $table );
						}
					}
				}
			}
		}

		$scanned_total = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );

		wp_send_json_success(
			array(
				'done'        => true,
				'scanned'     => $scanned_total,
				'total_files' => $scanned_total,
			)
		);
	}

	/**
	 * Scan a single attachment size and insert a log record.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size_name     Size name.
	 * @param string $size_file     Size file name.
	 * @param string $table         Logs table name.
	 * @return int 1 if a record was inserted, 0 otherwise.
	 */
	private function scan_attachment_size( $attachment_id, $size_name, $size_file, $table ) {
		global $wpdb;

		$original_path = get_attached_file( $attachment_id );
		if ( ! $original_path ) {
			return 0;
		}

		if ( 'original' === $size_name ) {
			$file_path = $original_path;
		} else {
			if ( empty( $size_file ) ) {
				return 0;
			}
			$file_path = dirname( $original_path ) . '/' . $size_file;
		}

		if ( ! file_exists( $file_path ) ) {
			return 0;
		}

		$file_size = filesize( $file_path );
		if ( $file_size < 100 ) {
			return 0;
		}

		$webp_url  = '';
		$webp_size = 0;
		$status    = 'pending';

		if ( $this->is_native_webp( $file_path ) ) {
			$webp_url  = $file_path;
			$webp_size = $file_size;
			$status    = 'native';
		} else {
			$jo_webp = $file_path . '.jo.webp';
			if ( file_exists( $jo_webp ) ) {
				$webp_url  = $jo_webp;
				$webp_size = filesize( $jo_webp );
				$status    = 'success';
			}
		}

		$wpdb->insert(
			$table,
			array(
				'attachment_id' => $attachment_id,
				'image_type'    => 'local',
				'size_name'     => $size_name,
				'original_url'  => $file_path,
				'webp_url'      => $webp_url,
				'original_size' => $file_size,
				'webp_size'     => $webp_size,
				'status'        => $status,
			)
		);

		return 1;
	}

	/**
	 * Run a batch conversion over pending images.
	 *
	 * @return void
	 */
	public function handle_ajax_batch() {
		$this->verify_nonce();

		global $wpdb;
		$table = $wpdb->prefix . 'onewebp_logs';

		$total_pending = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );

		if ( 0 === $total_pending ) {
			wp_send_json_success(
				array(
					'done'          => true,
					'progress'      => 100,
					'message'       => __( 'Optimization Complete!', 'onewebp' ),
					'total_pending' => 0,
				)
			);
		}

		$batch_size = min( 10, max( 3, ceil( $total_pending / 100 ) ) );

		$pending_images = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' LIMIT %d", $batch_size ),
			ARRAY_A
		);

		if ( empty( $pending_images ) ) {
			wp_send_json_success(
				array(
					'done'     => true,
					'progress' => 100,
					'message'  => __( 'Optimization Complete!', 'onewebp' ),
				)
			);
		}

		if ( ! class_exists( 'OneWebP_Converter' ) ) {
			require_once ONEWEBP_DIR . 'includes/class-onewebp-converter.php';
		}

		$converter = new OneWebP_Converter();
		$processed = 0;

		$batch_override = get_option( 'onewebp_batch_mode_override', '' );
		$mode           = $batch_override ? $batch_override : get_option( 'onewebp_image_deletion_mode', 'sync' );

		if ( $batch_override ) {
			delete_option( 'onewebp_batch_mode_override' );
		}

		foreach ( $pending_images as $img ) {
			$dest_path = $img['original_url'] . '.jo.webp';

			$result = $converter->execute_conversion( $img['original_url'], $dest_path );

			if ( $result['success'] ) {
				$webp_size = file_exists( $dest_path ) ? filesize( $dest_path ) : 0;
				$orig_size = $img['original_size'] ? $img['original_size'] : ( file_exists( $img['original_url'] ) ? filesize( $img['original_url'] ) : 0 );

				$wpdb->update(
					$table,
					array(
						'webp_url'      => $dest_path,
						'status'        => 'success',
						'webp_size'     => $webp_size,
						'original_size' => $orig_size,
						'is_downscaled' => $result['is_downscaled'] ? 1 : 0,
					),
					array( 'id' => $img['id'] )
				);

				if ( 'delete_original' === $mode && 'original' === $img['size_name'] && ! empty( $img['attachment_id'] ) ) {
					OneWebP_Core::get_instance()->replace_original_with_webp( $img['attachment_id'] );
				}
			} else {
				$wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $img['id'] ) );
			}
			$processed++;
		}

		$total_processed = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status IN ('success', 'failed', 'native')" );
		$total_all       = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
		$progress        = ( $total_all > 0 ) ? round( ( $total_processed / $total_all ) * 100, 1 ) : 100;
		$remaining       = max( 0, $total_pending - $processed );

		wp_send_json_success(
			array(
				'done'          => 0 === $remaining,
				'progress'      => $progress,
				'total_pending' => $remaining,
				/* translators: %s: progress percentage. */
				'message'       => sprintf( __( 'Processing... %s%%', 'onewebp' ), $progress ),
			)
		);
	}

	/**
	 * Reset all OneWebP data and delete generated files.
	 *
	 * @return void
	 */
	public function handle_ajax_reset_data() {
		$this->verify_nonce();

		global $wpdb;
		$table     = $wpdb->prefix . 'onewebp_logs';
		$ext_table = $wpdb->prefix . 'onewebp_external_cache';

		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$phase  = isset( $_POST['phase'] ) ? sanitize_text_field( wp_unslash( $_POST['phase'] ) ) : '';

		if ( '' === $phase || 'delete' === $phase ) {
			$limit = 30;

			$total_count = $this->count_all_scannable_files();

			$attachments = get_posts(
				array(
					'post_type'        => 'attachment',
					'post_mime_type'   => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
					'post_status'      => 'inherit',
					'posts_per_page'   => $limit,
					'offset'           => $offset,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => true,
				)
			);

			if ( empty( $attachments ) ) {
				wp_send_json_success(
					array(
						'done'        => false,
						'progress'    => 95,
						'message'     => __( 'Cleaning database...', 'onewebp' ),
						'next_offset' => 0,
						'next_phase'  => 'database',
					)
				);
			}

			foreach ( $attachments as $attachment_id ) {
				$original_path = get_attached_file( $attachment_id );
				if ( $original_path ) {
					onewebp_delete_onewebp_files( $original_path );

					$metadata = wp_get_attachment_metadata( $attachment_id );
					if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
						foreach ( $metadata['sizes'] as $size_data ) {
							if ( ! empty( $size_data['file'] ) ) {
								$size_path = dirname( $original_path ) . '/' . $size_data['file'];
								onewebp_delete_onewebp_files( $size_path );
							}
						}
					}
				}

				delete_post_meta( $attachment_id, '_onewebp_lqip' );
			}

			$next_offset = $offset + $limit;
			$progress    = ( $total_count > 0 ) ? round( ( $next_offset / $total_count ) * 90, 1 ) : 90;
			$progress    = min( 90, $progress );

			if ( $next_offset < $total_count ) {
				wp_send_json_success(
					array(
						'done'        => false,
						'progress'    => $progress,
						/* translators: %s: progress percentage. */
						'message'     => sprintf( __( 'Deleting OneWebP files... %s%%', 'onewebp' ), round( $progress, 1 ) ),
						'next_offset' => $next_offset,
						'next_phase'  => 'delete',
					)
				);
			}

			wp_send_json_success(
				array(
					'done'        => false,
					'progress'    => 90,
					'message'     => __( 'Cleaning database...', 'onewebp' ),
					'next_offset' => 0,
					'next_phase'  => 'database',
				)
			);
		}

		if ( 'database' === $phase ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
			$wpdb->query( "TRUNCATE TABLE {$ext_table}" );

			$upload_dir    = wp_upload_dir();
			$ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
			if ( is_dir( $ext_cache_dir ) ) {
				$ext_files = glob( $ext_cache_dir . '*' );
				if ( is_array( $ext_files ) ) {
					foreach ( $ext_files as $f ) {
						if ( is_file( $f ) ) {
							@unlink( $f );
						}
					}
				}
			}

			wp_send_json_success(
				array(
					'done'       => true,
					'progress'   => 100,
					'message'    => __( 'Reset complete!', 'onewebp' ),
					'next_phase' => 'done',
				)
			);
		}
	}
}