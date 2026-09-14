<?php
/**
 * Core class for OneWebP plugin.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OneWebP_Core
 */
class OneWebP_Core {

	/**
	 * Singleton instance.
	 *
	 * @var OneWebP_Core|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return OneWebP_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Initialize the core hooks and sub-components.
	 *
	 * @return void
	 */
	public function init() {
		new OneWebP_Converter();
		new OneWebP_Frontend();
		new OneWebP_Smart_Batch();
		new OneWebP_External();
		add_action( 'admin_init', array( $this, 'handle_reset_actions' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_action( 'wp_loaded', array( $this, 'handle_cleanup_orphaned_files' ) );
	}

	/**
	 * Plugin activation callback.
	 *
	 * Creates the database tables and sets a transient for the activation
	 * redirect.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}onewebp_logs (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) DEFAULT 0,
			image_type varchar(20) NOT NULL DEFAULT 'local',
			size_name varchar(50) DEFAULT 'original',
			original_url varchar(2000) NOT NULL,
			webp_url varchar(2000) NOT NULL,
			original_size int(11) DEFAULT 0,
			webp_size int(11) DEFAULT 0,
			is_downscaled tinyint(1) DEFAULT 0,
			status varchar(20) DEFAULT 'pending',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		$sql_ext = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}onewebp_external_cache (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			original_url varchar(2000) NOT NULL,
			local_webp_url varchar(2000) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_logs );
		dbDelta( $sql_ext );
		set_transient( 'onewebp_activation_redirect', true, 30 );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		delete_transient( 'onewebp_activation_redirect' );
		delete_transient( 'onewebp_cleanup_run' );
		delete_transient( 'onewebp_show_mode_notice' );
	}

	/**
	 * Redirect to the OneWebP dashboard after activation.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation() {
		if ( get_transient( 'onewebp_activation_redirect' ) ) {
			delete_transient( 'onewebp_activation_redirect' );
			if ( ! isset( $_GET['activate-multi'] ) && ! wp_doing_ajax() ) {
				wp_safe_redirect( admin_url( 'admin.php?page=onewebp&tab=dashboard' ) );
				exit;
			}
		}
	}

	/**
	 * Handle the reset settings action.
	 *
	 * @return void
	 */
	public function handle_reset_actions() {
		if ( ! isset( $_POST['onewebp_reset_action'] ) ) {
			return;
		}
		$action = sanitize_text_field( wp_unslash( $_POST['onewebp_reset_action'] ) );

		if ( 'reset_settings' === $action && check_admin_referer( 'onewebp_reset_settings' ) ) {
			$options = array(
				'onewebp_quality',
				'onewebp_max_resolution',
				'onewebp_allow_oversized',
				'onewebp_first_n_direct',
				'onewebp_conversion_scope',
				'onewebp_enable_external',
				'onewebp_enable_lazyload',
				'onewebp_allowed_types',
				'onewebp_image_deletion_mode',
				'onewebp_enable_picture_wrapper',
				'onewebp_enable_progressive',
			);
			foreach ( $options as $opt ) {
				delete_option( $opt );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=onewebp&tab=settings&msg=reset_settings' ) );
			exit;
		}
	}

	/**
	 * Replace the original file with its WebP version.
	 *
	 * Used in delete_original mode: after conversion, the original file is
	 * removed and the WebP file becomes the main attachment file.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public function replace_original_with_webp( $attachment_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'onewebp_logs';

		$original_file = get_attached_file( $attachment_id );
		if ( ! $original_file || ! file_exists( $original_file ) ) {
			return;
		}

		$webp_path = $original_file . '.jo.webp';
		if ( ! file_exists( $webp_path ) ) {
			return;
		}

		$original_dir      = dirname( $original_file );
		$original_basename = basename( $original_file );
		$original_filename = pathinfo( $original_basename, PATHINFO_FILENAME );

		// Get metadata before deleting files.
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Delete original file.
		@unlink( $original_file );

		// Delete original thumbnails.
		if ( $metadata && ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
			foreach ( $metadata['sizes'] as $size_name => $size_data ) {
				if ( empty( $size_data['file'] ) ) {
					continue;
				}

				$size_path = $original_dir . '/' . $size_data['file'];
				if ( file_exists( $size_path ) ) {
					@unlink( $size_path );
				}
			}
		}

		// Update _wp_attached_file.
		$upload_dir    = wp_upload_dir();
		$relative_path = str_replace( $upload_dir['basedir'] . '/', '', $webp_path );
		update_post_meta( $attachment_id, '_wp_attached_file', $relative_path );

		// Update attachment metadata.
		if ( $metadata ) {
			$metadata['file'] = $relative_path;

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				$new_sizes = array();
				foreach ( $metadata['sizes'] as $size_name => $size_data ) {
					if ( empty( $size_data['file'] ) ) {
						$new_sizes[ $size_name ] = $size_data;
						continue;
					}

					$new_size_file            = $size_data['file'] . '.jo.webp';
					$size_data['file']        = $new_size_file;
					$size_data['mime-type']   = 'image/webp';
					$new_sizes[ $size_name ]  = $size_data;
				}
				$metadata['sizes'] = $new_sizes;
			}

			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		// Update mime type.
		wp_update_post(
			array(
				'ID'             => $attachment_id,
				'post_mime_type' => 'image/webp',
			)
		);

		// Update guid.
		$webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $webp_path );
		$wpdb->update(
			$wpdb->posts,
			array( 'guid' => $webp_url ),
			array( 'ID' => $attachment_id )
		);

		// Update log: original_url is now the WebP file.
		$wpdb->update(
			$table,
			array( 'original_url' => $webp_path ),
			array(
				'attachment_id' => $attachment_id,
				'size_name'     => 'original',
			)
		);

		// Delete LQIP meta.
		delete_post_meta( $attachment_id, '_onewebp_lqip' );
	}

	/**
	 * Clean up orphaned WebP files that have no database record.
	 *
	 * Runs at most once per week and only for administrators.
	 *
	 * @return void
	 */
	public function handle_cleanup_orphaned_files() {
		if ( get_transient( 'onewebp_cleanup_run' ) ) {
			return;
		}
		set_transient( 'onewebp_cleanup_run', true, WEEK_IN_SECONDS );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table      = $wpdb->prefix . 'onewebp_logs';
		$upload_dir = wp_upload_dir();
		$base_dir   = str_replace( '\\', '/', $upload_dir['basedir'] );

		if ( ! is_dir( $base_dir ) ) {
			return;
		}

		$files = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $base_dir, FilesystemIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
			foreach ( $iterator as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}
				$path = str_replace( '\\', '/', $file->getPathname() );
				if ( strpos( $path, '/onewebp-external-cache/' ) !== false ) {
					continue;
				}
				if ( preg_match( '/\.jo\.webp$/i', $path ) ) {
					$files[] = $path;
				}
			}
		} catch ( Exception $e ) {
			return;
		}

		if ( empty( $files ) ) {
			return;
		}

		$batch_size = 100;
		for ( $i = 0; $i < count( $files ); $i += $batch_size ) {
			$batch = array_slice( $files, $i, $batch_size );
			if ( empty( $batch ) ) {
				continue;
			}

			$placeholders = implode( ', ', array_fill( 0, count( $batch ), '%s' ) );
			$query        = $wpdb->prepare( "SELECT webp_url FROM {$table} WHERE webp_url IN ($placeholders)", $batch );
			$existing     = $wpdb->get_col( $query );
			$existing_set = array_flip( $existing );

			foreach ( $batch as $file ) {
				if ( ! isset( $existing_set[ $file ] ) ) {
					@unlink( $file );
				}
			}
		}
	}
}