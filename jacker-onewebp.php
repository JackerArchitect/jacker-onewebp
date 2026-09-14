<?php
/**
 * Plugin Name:       OneWebP
 * Plugin URI:        https://jackerteo.com/plugin/onewebp
 * Description:       100% free, unlimited local WebP converter with smart lazy loading. Zero API, your images never leave your server.
 * Version:           1.0.0
 * Requires at least: 6.1
 * Requires PHP:      7.4
 * Author:            Jacker Architect
 * Author URI:        https://github.com/JackerArchitect
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       onewebp
 * Domain Path:       /languages
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ONEWEBP_VERSION', '1.0.0' );
define( 'ONEWEBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONEWEBP_URL', plugin_dir_url( __FILE__ ) );
define( 'ONEWEBP_BASENAME', plugin_basename( __FILE__ ) );

require_once ONEWEBP_DIR . 'includes/class-onewebp-core.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-converter.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-smart-batch.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-frontend.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-external.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-list-table.php';

register_activation_hook( __FILE__, array( 'OneWebP_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OneWebP_Core', 'deactivate' ) );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function onewebp_init() {
	load_plugin_textdomain( 'onewebp', false, dirname( ONEWEBP_BASENAME ) . '/languages' );
	OneWebP_Core::get_instance()->init();
}
add_action( 'plugins_loaded', 'onewebp_init' );

/**
 * Register the admin menu page.
 *
 * @return void
 */
function onewebp_admin_menu() {
	add_menu_page(
		__( 'OneWebP', 'onewebp' ),
		__( 'OneWebP', 'onewebp' ),
		'manage_options',
		'onewebp',
		'onewebp_render_page',
		ONEWEBP_URL . 'assets/images/favicon_s.png?v=2',
		59
	);
}
add_action( 'admin_menu', 'onewebp_admin_menu' );

/**
 * Register plugin settings.
 *
 * @return void
 */
function onewebp_register_settings() {
	register_setting(
		'onewebp_settings',
		'onewebp_quality',
		array(
			'default'           => 82,
			'sanitize_callback' => 'onewebp_sanitize_quality',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_max_resolution',
		array(
			'default'           => 3000,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_allow_oversized',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_first_n_direct',
		array(
			'default'           => 3,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_conversion_scope',
		array(
			'default'           => 'all',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_enable_external',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_enable_lazyload',
		array(
			'default'           => 1,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_allowed_types',
		array(
			'default'           => array( 'jpeg', 'png' ),
			'sanitize_callback' => 'onewebp_sanitize_allowed_types',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_image_deletion_mode',
		array(
			'default'           => 'sync',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_enable_picture_wrapper',
		array(
			'default'           => 1,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_enable_progressive',
		array(
			'default'           => 1,
			'sanitize_callback' => 'absint',
		)
	);
	register_setting(
		'onewebp_settings',
		'onewebp_replace_original_immediately',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
}
add_action( 'admin_init', 'onewebp_register_settings' );

/**
 * Sanitize the WebP quality value.
 *
 * @param mixed $input Raw input value.
 * @return int Sanitized quality between 50 and 100.
 */
function onewebp_sanitize_quality( $input ) {
	$input = intval( $input );
	if ( $input < 50 ) {
		return 50;
	}
	if ( $input > 100 ) {
		return 100;
	}
	return $input;
}

/**
 * Sanitize the allowed image types.
 *
 * @param mixed $input Raw input value.
 * @return array Sanitized array of allowed types.
 */
function onewebp_sanitize_allowed_types( $input ) {
	if ( ! is_array( $input ) ) {
		return array( 'jpeg', 'png' );
	}
	$allowed = array( 'jpeg', 'png', 'gif' );
	return array_intersect( $input, $allowed );
}

/**
 * Handle deletion mode change.
 *
 * @param string $old_value Previous option value.
 * @param string $new_value New option value.
 * @return void
 */
function onewebp_on_mode_change( $old_value, $new_value ) {
	if ( $old_value === $new_value ) {
		return;
	}
	if ( 'delete_original' === $new_value ) {
		global $wpdb;
		$table         = $wpdb->prefix . 'onewebp_logs';
		$pending_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
		if ( $pending_count > 0 ) {
			set_transient( 'onewebp_show_mode_notice', $pending_count, DAY_IN_SECONDS );
		}
	}
}
add_action( 'update_option_onewebp_image_deletion_mode', 'onewebp_on_mode_change', 10, 2 );

/**
 * Handle conversion scope change.
 *
 * When the scope changes, the existing log no longer reflects the
 * current scan configuration. Clear the log and notify the user so
 * they can rescan the library.
 *
 * @param string $old_value Previous option value.
 * @param string $new_value New option value.
 * @return void
 */
function onewebp_on_scope_change( $old_value, $new_value ) {
	if ( $old_value === $new_value ) {
		return;
	}

	global $wpdb;
	$table = $wpdb->prefix . 'onewebp_logs';

	// Reset the log so the next scan reflects the new scope.
	$wpdb->query( "TRUNCATE TABLE {$table}" );

	// Notify the user.
	set_transient( 'onewebp_scope_changed_notice', 1, DAY_IN_SECONDS );
}
add_action( 'update_option_onewebp_conversion_scope', 'onewebp_on_scope_change', 10, 2 );

/**
 * Display a notice after the conversion scope changes.
 *
 * @return void
 */
function onewebp_scope_changed_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! get_transient( 'onewebp_scope_changed_notice' ) ) {
		return;
	}

	delete_transient( 'onewebp_scope_changed_notice' );

	?>
	<div class="notice notice-info is-dismissible">
		<p>
			<strong><?php esc_html_e( 'OneWebP:', 'onewebp' ); ?></strong>
			<?php esc_html_e( 'Conversion scope changed. The image log has been reset. Please rescan the library to apply the new scope.', 'onewebp' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'onewebp_scope_changed_notice' );

/**
 * Display the admin notice when switching to delete_original mode.
 *
 * @return void
 */
function onewebp_mode_change_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$pending_count = get_transient( 'onewebp_show_mode_notice' );
	if ( ! $pending_count ) {
		return;
	}

	$base_url = admin_url( 'admin.php?page=onewebp&tab=dashboard' );

	wp_register_script( 'onewebp-mode-notice', false, array( 'jquery' ), ONEWEBP_VERSION, true );
	wp_enqueue_script( 'onewebp-mode-notice' );

	$inline_js = '
		jQuery(document).ready(function($) {
			var onewebpAjaxUrl = ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';
			var onewebpNonce   = ' . wp_json_encode( wp_create_nonce( 'onewebp_nonce' ) ) . ';
			var onewebpBaseUrl = ' . wp_json_encode( $base_url ) . ';

			function dismissNotice(action) {
				$.post(onewebpAjaxUrl, {
					action: "onewebp_dismiss_mode_notice",
					nonce: onewebpNonce,
					mode_action: action
				}, function() {
					$("#onewebp-mode-notice").fadeOut();
					if (action === "convert_delete" || action === "convert_keep") {
						window.location.href = onewebpBaseUrl;
					}
				});
			}

			$("#onewebp-mode-convert-delete").on("click", function() { dismissNotice("convert_delete"); });
			$("#onewebp-mode-convert-keep").on("click", function() { dismissNotice("convert_keep"); });
			$("#onewebp-mode-cancel").on("click", function() { dismissNotice("cancel"); });
		});
	';
	wp_add_inline_script( 'onewebp-mode-notice', $inline_js );

	?>
	<div class="notice notice-warning" id="onewebp-mode-notice">
		<p>
			<strong><?php esc_html_e( 'OneWebP: Delete Original mode is now active.', 'onewebp' ); ?></strong>
		</p>
		<p>
			<?php
			printf(
				/* translators: %d: number of pending images. */
				esc_html__( 'Found %d images that have not been converted yet. What would you like to do?', 'onewebp' ),
				intval( $pending_count )
			);
			?>
		</p>
		<p>
			<button type="button" class="button button-primary" id="onewebp-mode-convert-delete">
				<?php esc_html_e( 'Convert and Delete Originals', 'onewebp' ); ?>
			</button>
			<button type="button" class="button" id="onewebp-mode-convert-keep">
				<?php esc_html_e( 'Convert but Keep Originals', 'onewebp' ); ?>
			</button>
			<button type="button" class="button" id="onewebp-mode-cancel">
				<?php esc_html_e( 'Cancel', 'onewebp' ); ?>
			</button>
		</p>
		<p class="onewebp-mode-notice-hint">
			<?php esc_html_e( 'New uploads will follow the Delete Original mode. Pending images will wait for your action.', 'onewebp' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'onewebp_mode_change_notice' );

/**
 * Handle image manager actions (remove, reoptimize, edit).
 *
 * @return void
 */
function onewebp_handle_manager_actions() {
	if ( ! isset( $_GET['page'] ) || 'onewebp' !== $_GET['page'] ) {
		return;
	}
	if ( ! isset( $_GET['tab'] ) || 'manager' !== $_GET['tab'] ) {
		return;
	}
	if ( ! isset( $_GET['action'] ) || ! isset( $_GET['log_id'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'onewebp' ) );
	}

	$action = sanitize_text_field( wp_unslash( $_GET['action'] ) );
	$log_id = intval( $_GET['log_id'] );
	$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'onewebp_action_' . $log_id ) ) {
		wp_die( esc_html__( 'Security check failed.', 'onewebp' ) );
	}

	global $wpdb;
	$table = $wpdb->prefix . 'onewebp_logs';
	$log   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ) );

	if ( ! $log ) {
		wp_die( esc_html__( 'Record not found.', 'onewebp' ) );
	}

	$base_redirect = admin_url( 'admin.php?page=onewebp&tab=manager' );

	if ( 'remove' === $action ) {
		onewebp_delete_onewebp_files( $log->original_url );
		$wpdb->delete( $table, array( 'id' => $log_id ) );
		wp_safe_redirect( add_query_arg( 'msg', 'removed', $base_redirect ) );
		exit;
	}

	if ( 'reoptimize' === $action ) {
		$dest_path = $log->original_url . '.jo.webp';
		if ( file_exists( $dest_path ) ) {
			@unlink( $dest_path );
		}

		$lqip_path = $log->original_url . '.lqip.webp';
		if ( file_exists( $lqip_path ) ) {
			@unlink( $lqip_path );
		}

		$converter = new OneWebP_Converter();
		$result    = $converter->execute_conversion( $log->original_url, $dest_path );
		if ( $result['success'] ) {
			$converter->generate_lqip( $log->original_url, $log->attachment_id, $log->size_name );

			$mode = get_option( 'onewebp_image_deletion_mode', 'sync' );
			if ( 'delete_original' === $mode && 'original' === $log->size_name ) {
				OneWebP_Core::get_instance()->replace_original_with_webp( $log->attachment_id );
			}

			$wpdb->update(
				$table,
				array(
					'webp_url'      => $dest_path,
					'webp_size'     => filesize( $dest_path ),
					'status'        => 'success',
					'is_downscaled' => $result['is_downscaled'] ? 1 : 0,
				),
				array( 'id' => $log_id )
			);
			wp_safe_redirect( add_query_arg( 'msg', 'reoptimized', $base_redirect ) );
		} else {
			$wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $log_id ) );
			wp_safe_redirect( add_query_arg( 'msg', 'failed', $base_redirect ) );
		}
		exit;
	}

	if ( 'edit' === $action && isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['onewebp_edit_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['onewebp_edit_nonce'] ) ), 'onewebp_edit_' . $log_id ) ) {
			wp_die( esc_html__( 'Security check failed.', 'onewebp' ) );
		}

		$custom_quality = isset( $_POST['custom_quality'] ) ? intval( $_POST['custom_quality'] ) : 82;
		$custom_max_res = isset( $_POST['custom_max_res'] ) ? intval( $_POST['custom_max_res'] ) : 3000;

		$custom_quality = max( 50, min( 100, $custom_quality ) );
		$custom_max_res = max( 100, $custom_max_res );

		$dest_path = $log->original_url . '.jo.webp';
		if ( file_exists( $dest_path ) ) {
			@unlink( $dest_path );
		}

		$converter = new OneWebP_Converter();
		$result    = $converter->execute_conversion( $log->original_url, $dest_path, $custom_quality, $custom_max_res, 0 );

		if ( $result['success'] ) {
			$wpdb->update(
				$table,
				array(
					'webp_url'      => $dest_path,
					'webp_size'     => filesize( $dest_path ),
					'status'        => 'success',
					'is_downscaled' => $result['is_downscaled'] ? 1 : 0,
				),
				array( 'id' => $log_id )
			);
			wp_safe_redirect( add_query_arg( 'msg', 'edited', $base_redirect ) );
		} else {
			wp_safe_redirect( add_query_arg( 'msg', 'failed', $base_redirect ) );
		}
		exit;
	}
}
add_action( 'admin_init', 'onewebp_handle_manager_actions' );

/**
 * Delete OneWebP generated files (.jo.webp and .lqip.webp) for a base path.
 *
 * @param string $base_path Base file path.
 * @return void
 */
function onewebp_delete_onewebp_files( $base_path ) {
	$jo = $base_path . '.jo.webp';
	if ( file_exists( $jo ) ) {
		@unlink( $jo );
	}

	$lqip = $base_path . '.lqip.webp';
	if ( file_exists( $lqip ) ) {
		@unlink( $lqip );
	}
}

/**
 * Enqueue admin assets on the OneWebP page.
 *
 * @param string $hook Current admin page hook.
 * @return void
 */
function onewebp_admin_assets( $hook ) {
	if ( 'toplevel_page_onewebp' !== $hook ) {
		return;
	}

	wp_enqueue_style( 'onewebp-admin-css', ONEWEBP_URL . 'assets/css/admin-style.css', array(), ONEWEBP_VERSION );

	$js_version = file_exists( ONEWEBP_DIR . 'assets/js/admin-dashboard.js' ) ?
		filemtime( ONEWEBP_DIR . 'assets/js/admin-dashboard.js' ) :
		ONEWEBP_VERSION;

	wp_enqueue_script( 'onewebp-admin-js', ONEWEBP_URL . 'assets/js/admin-dashboard.js', array( 'jquery' ), $js_version, true );

	wp_localize_script(
		'onewebp-admin-js',
		'onewebp_vars',
		array(
			'nonce'          => wp_create_nonce( 'onewebp_nonce' ),
			'ajaxurl'        => admin_url( 'admin-ajax.php' ),
			'text_completed' => __( 'Optimization Complete!', 'onewebp' ),
			'text_paused'    => __( 'Paused', 'onewebp' ),
			'text_scanning'  => __( 'Scanning...', 'onewebp' ),
			'text_start'     => __( 'Start Optimization', 'onewebp' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'onewebp_admin_assets' );

/**
 * Display a disk space warning notice.
 *
 * @return void
 */
function onewebp_check_disk_space_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$upload_dir = wp_upload_dir();
	if ( ! function_exists( 'disk_free_space' ) ) {
		return;
	}
	$free_space = @disk_free_space( $upload_dir['basedir'] );
	if ( false === $free_space ) {
		return;
	}
	$free_space_mb = $free_space / ( 1024 * 1024 );
	if ( $free_space_mb < 500 ) {
		echo '<div class="notice notice-warning is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'OneWebP Disk Space Warning:', 'onewebp' ) . '</strong> ';
		printf(
			/* translators: %s: remaining disk space in MB. */
			esc_html__( 'Your server disk space is running low. Currently remaining: %s.', 'onewebp' ),
			'<strong>' . esc_html( round( $free_space_mb, 1 ) ) . ' MB</strong>'
		);
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'onewebp_check_disk_space_notice' );

/**
 * Display a RAM warning notice.
 *
 * @return void
 */
function onewebp_check_ram_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$limit_str = ini_get( 'memory_limit' );
	if ( '-1' === $limit_str ) {
		return;
	}
	$memory_bytes    = wp_convert_hr_to_bytes( $limit_str );
	$available_bytes = $memory_bytes - memory_get_usage( true );
	if ( $available_bytes < 64 * 1024 * 1024 ) {
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'OneWebP Critical Memory Warning:', 'onewebp' ) . '</strong> ';
		printf(
			/* translators: %s: available memory in MB. */
			esc_html__( 'Available RAM is too low (%s MB). Please increase memory_limit.', 'onewebp' ),
			esc_html( round( $available_bytes / 1024 / 1024, 1 ) )
		);
		echo '</p></div>';
	}
}
add_action( 'admin_notices', 'onewebp_check_ram_notice' );

/**
 * Handle image deletion based on the selected deletion mode.
 *
 * @param int $attachment_id Attachment ID.
 * @return void
 */
function onewebp_handle_image_deletion( $attachment_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'onewebp_logs';
	$mode  = get_option( 'onewebp_image_deletion_mode', 'sync' );

	if ( 'keep' === $mode ) {
		return;
	}

	$original_file = get_attached_file( $attachment_id );

	$records = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, webp_url FROM {$table} WHERE attachment_id = %d",
			$attachment_id
		)
	);

	if ( ! empty( $records ) ) {
		foreach ( $records as $record ) {
			if ( 'sync' === $mode && ! empty( $record->webp_url ) && file_exists( $record->webp_url ) ) {
				onewebp_delete_onewebp_files( $record->webp_url );
			}
		}
		$wpdb->delete( $table, array( 'attachment_id' => $attachment_id ) );
	}

	if ( $original_file && 'sync' === $mode ) {
		$dir              = dirname( $original_file );
		$name_without_ext = pathinfo( $original_file, PATHINFO_FILENAME );

		$jo_files = glob( $dir . '/' . $name_without_ext . '*.jo.webp' );
		if ( $jo_files ) {
			foreach ( $jo_files as $f ) {
				@unlink( $f );
			}
		}
		$lqip_files = glob( $dir . '/' . $name_without_ext . '*.lqip.webp' );
		if ( $lqip_files ) {
			foreach ( $lqip_files as $f ) {
				@unlink( $f );
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE original_url LIKE %s",
				'%' . $wpdb->esc_like( $name_without_ext ) . '%'
			)
		);
	}
}
add_action( 'delete_attachment', 'onewebp_handle_image_deletion' );

/**
 * Render the plugin admin page.
 *
 * All CSS lives in the admin stylesheet. No inline style or script
 * blocks are used here, complying with WordPress enqueue standards.
 *
 * @return void
 */
function onewebp_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'dashboard';

	if ( isset( $_GET['msg'] ) ) {
		$msg      = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
		$messages = array(
			'removed'        => __( 'WebP file removed successfully.', 'onewebp' ),
			'reoptimized'    => __( 'Image re-optimized with global settings.', 'onewebp' ),
			'edited'         => __( 'Image re-optimized with custom settings.', 'onewebp' ),
			'failed'         => __( 'Conversion failed.', 'onewebp' ),
			'reset_settings' => __( 'Settings reset to defaults.', 'onewebp' ),
			'reset_data'     => __( 'All data and WebP files cleared.', 'onewebp' ),
		);
		if ( isset( $messages[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
		}
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'OneWebP Optimizer', 'onewebp' ); ?></h1>

		<nav class="nav-tab-wrapper">
			<a href="?page=onewebp&tab=dashboard" class="nav-tab <?php echo 'dashboard' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'onewebp' ); ?></a>
			<a href="?page=onewebp&tab=settings" class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'onewebp' ); ?></a>
			<a href="?page=onewebp&tab=manager" class="nav-tab <?php echo 'manager' === $tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Image Manager', 'onewebp' ); ?></a>
		</nav>

		<div class="onewebp-content">
			<?php if ( 'dashboard' === $tab ) : ?>
				<div class="onewebp-report-grid">
					<div class="onewebp-report-box"><span class="onewebp-report-num" id="stat-total">0</span><span class="onewebp-report-label"><?php esc_html_e( 'Total Images', 'onewebp' ); ?></span></div>
					<div class="onewebp-report-box"><span class="onewebp-report-num onewebp-stat-converted" id="stat-converted">0</span><span class="onewebp-report-label"><?php esc_html_e( 'Converted', 'onewebp' ); ?></span></div>
					<div class="onewebp-report-box"><span class="onewebp-report-num onewebp-stat-native" id="stat-native">0</span><span class="onewebp-report-label"><?php esc_html_e( 'Already WebP', 'onewebp' ); ?></span></div>
					<div class="onewebp-report-box"><span class="onewebp-report-num onewebp-stat-pending" id="stat-pending">0</span><span class="onewebp-report-label"><?php esc_html_e( 'Pending', 'onewebp' ); ?></span></div>
					<div class="onewebp-report-box"><span class="onewebp-report-num onewebp-stat-failed" id="stat-failed">0</span><span class="onewebp-report-label"><?php esc_html_e( 'Failed', 'onewebp' ); ?></span></div>
				</div>

				<div class="onewebp-progress-container">
					<div class="onewebp-progress-fill" id="main-progress-bar"></div>
					<div class="onewebp-progress-text" id="main-progress-text">0%</div>
				</div>
				<p class="onewebp-scan-status" id="scan-status"></p>

				<div class="onewebp-card">
					<div class="onewebp-saved-space-box">
						<p class="onewebp-saved-space-text">
							<?php esc_html_e( 'Total Space Saved:', 'onewebp' ); ?>
							<strong id="saved-space" class="onewebp-saved-space-value">0 <?php esc_html_e( 'Bytes', 'onewebp' ); ?></strong>
						</p>
					</div>
					<p>
						<button id="start-optimize-btn" class="button button-primary button-hero" disabled><?php esc_html_e( 'Scanning...', 'onewebp' ); ?></button>
						<button id="stop-optimize-btn" class="button button-link-delete onewebp-hidden"><?php esc_html_e( 'Pause', 'onewebp' ); ?></button>
						<button id="rescan-btn" class="button button-secondary onewebp-ml-10"><?php esc_html_e( 'Rescan Library', 'onewebp' ); ?></button>
					</p>
				</div>

				<div class="onewebp-footer">
					<p class="onewebp-footer-text">
						<?php esc_html_e( 'Support open source. Buy me a coffee to keep this project alive!', 'onewebp' ); ?>
						<a href="https://jackerteo.com/plugin/onewebp" target="_blank" rel="noopener noreferrer" class="onewebp-coffee-btn">Buy me a coffee</a>
					</p>
				</div>

			<?php elseif ( 'settings' === $tab ) : ?>

				<div class="onewebp-settings-intro">
					<h2><?php esc_html_e( 'OneWebP Settings', 'onewebp' ); ?></h2>
					<p><?php esc_html_e( 'Configure how OneWebP converts your images to WebP and how the generated files are served on the frontend. Changes take effect immediately after saving.', 'onewebp' ); ?></p>
				</div>

				<form method="post" action="options.php">
					<?php settings_fields( 'onewebp_settings' ); ?>

					<h2 class="onewebp-settings-section"><?php esc_html_e( 'Conversion Settings', 'onewebp' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'WebP Quality', 'onewebp' ); ?></th>
							<td>
								<input type="number" name="onewebp_quality" value="<?php echo esc_attr( get_option( 'onewebp_quality', 82 ) ); ?>" class="small-text" min="50" max="100">
								<p class="description"><?php esc_html_e( 'Higher quality means larger files. Recommended: 82. Range: 50-100.', 'onewebp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Max Resolution (px)', 'onewebp' ); ?></th>
							<td>
								<input type="number" name="onewebp_max_resolution" value="<?php echo esc_attr( get_option( 'onewebp_max_resolution', 3000 ) ); ?>" class="small-text" min="800">
								<p class="description"><?php esc_html_e( 'Images larger than this will be downscaled before conversion. Default: 3000.', 'onewebp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Allow Oversized Images', 'onewebp' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="onewebp_allow_oversized" value="1" <?php checked( get_option( 'onewebp_allow_oversized', 0 ), 1 ); ?>>
									<?php esc_html_e( 'Disable automatic downscaling.', 'onewebp' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'When enabled, images above Max Resolution are converted at their original size. Not recommended for shared hosting.', 'onewebp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'First N Direct Load', 'onewebp' ); ?></th>
							<td>
								<input type="number" name="onewebp_first_n_direct" value="<?php echo esc_attr( get_option( 'onewebp_first_n_direct', 3 ) ); ?>" class="small-text" min="0" max="10">
								<p class="description"><?php esc_html_e( 'Number of images above the fold to load directly with high priority. Default: 3.', 'onewebp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Conversion Scope', 'onewebp' ); ?></th>
							<td>
								<fieldset>
									<label><input type="radio" name="onewebp_conversion_scope" value="all" <?php checked( get_option( 'onewebp_conversion_scope', 'all' ), 'all' ); ?>> <?php esc_html_e( 'Original + All Thumbnails (Recommended)', 'onewebp' ); ?></label><br>
									<label><input type="radio" name="onewebp_conversion_scope" value="original" <?php checked( get_option( 'onewebp_conversion_scope' ), 'original' ); ?>> <?php esc_html_e( 'Original Image Only', 'onewebp' ); ?></label>
								</fieldset>
								<p class="description"><?php esc_html_e( 'Choose whether to convert only the original image or also every generated thumbnail size. Changing this option resets the image log so you can rescan with the new scope.', 'onewebp' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Image Types to Convert', 'onewebp' ); ?></th>
							<td>
								<?php $allowed = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) ); ?>
								<label><input type="checkbox" name="onewebp_allowed_types[]" value="jpeg" <?php checked( in_array( 'jpeg', $allowed, true ) ); ?>> JPEG / JPG</label><br>
								<label><input type="checkbox" name="onewebp_allowed_types[]" value="png" <?php checked( in_array( 'png', $allowed, true ) ); ?>> PNG</label><br>
								<label><input type="checkbox" name="onewebp_allowed_types[]" value="gif" <?php checked( in_array( 'gif', $allowed, true ) ); ?>> GIF (Static only)</label>
								<p class="description"><?php esc_html_e( 'Animated GIFs are skipped because the GD library does not support them.', 'onewebp' ); ?></p>
							</td>
						</tr>
					</table>

					<h2 class="onewebp-settings-section"><?php esc_html_e( 'Frontend Delivery', 'onewebp' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Global Picture Wrapper', 'onewebp' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="onewebp_enable_picture_wrapper" value="1" <?php checked( get_option( 'onewebp_enable_picture_wrapper', 1 ), 1 ); ?>>
									<?php esc_html_e( 'Enable automatic picture wrapper for all images.', 'onewebp' ); ?>
								</label>

								<div class="onewebp-picture-note">
									<p><?php esc_html_e( 'What this does:', 'onewebp' ); ?></p>
									<div class="onewebp-picture-details">
										<ul>
											<li><?php esc_html_e( 'Wraps every eligible <img> tag in a <picture> element.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'Adds a WebP <source> for browsers that support it.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'Keeps the original <img> as a fallback for older browsers.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'Preserves srcset, sizes, alt, class, and other attributes.', 'onewebp' ); ?></li>
										</ul>
									</div>
									<div class="onewebp-picture-warning">
										<strong><?php esc_html_e( 'Warning:', 'onewebp' ); ?></strong>
										<?php esc_html_e( 'Some page builders output their own <picture> or lazy-load markup. If images look broken on the frontend, disable this option and test again.', 'onewebp' ); ?>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Progressive Loading', 'onewebp' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="onewebp_enable_progressive" value="1" <?php checked( get_option( 'onewebp_enable_progressive', 1 ), 1 ); ?>>
									<?php esc_html_e( 'Enable blur-to-sharp progressive image loading.', 'onewebp' ); ?>
								</label>

								<div class="onewebp-progressive-note">
									<p><?php esc_html_e( 'What this does:', 'onewebp' ); ?></p>
									<div class="onewebp-progressive-details">
										<ul>
											<li><?php esc_html_e( 'Generates a tiny 12px placeholder (LQIP) for each converted image.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'The placeholder loads instantly and is shown blurred.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'The full-quality WebP fades in smoothly once loaded.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'Improves perceived performance and Core Web Vitals (LCP, CLS).', 'onewebp' ); ?></li>
										</ul>
									</div>
									<div class="onewebp-progressive-note-warning">
										<strong><?php esc_html_e( 'Note:', 'onewebp' ); ?></strong>
										<?php esc_html_e( 'Each image gets an extra .lqip.webp file (3-6 KB). Recommended for image-heavy sites. Disable to save disk space.', 'onewebp' ); ?>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Smart Lazy Load', 'onewebp' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="onewebp_enable_lazyload" value="1" <?php checked( get_option( 'onewebp_enable_lazyload', 1 ), 1 ); ?>>
									<?php esc_html_e( 'Enable queue-based lazy loading.', 'onewebp' ); ?>
								</label>

								<div class="onewebp-lazy-note">
									<p><?php esc_html_e( 'How it works:', 'onewebp' ); ?></p>
									<div class="onewebp-lazy-details">
										<ul>
											<li><?php esc_html_e( 'The first N images load immediately with fetchpriority="high".', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'All other images load only when they approach the viewport.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'A queue of 3 concurrent requests keeps the network smooth.', 'onewebp' ); ?></li>
										</ul>
									</div>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'External Images', 'onewebp' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="onewebp_enable_external" value="1" <?php checked( get_option( 'onewebp_enable_external', 0 ), 1 ); ?>>
									<?php esc_html_e( 'Download and convert external images.', 'onewebp' ); ?>
								</label>

								<div class="onewebp-external-note">
									<p><?php esc_html_e( 'How it works:', 'onewebp' ); ?></p>
									<div class="onewebp-lazy-details">
										<ul>
											<li><?php esc_html_e( 'Fetches external image URLs found in your content.', 'onewebp' ); ?></li>
											<li><?php esc_html_e( 'Converts them to WebP and stores them in a local cache folder.', 'onewebp' ); ?></li>
										</ul>
									</div>
									<div class="onewebp-external-warning">
										<strong><?php esc_html_e( 'Warning:', 'onewebp' ); ?></strong>
										<?php esc_html_e( 'Only enable if your server has enough disk space and you trust the external sources. Cached files are stored under wp-content/uploads/onewebp-external-cache/.', 'onewebp' ); ?>
									</div>
								</div>
							</td>
						</tr>
					</table>

					<h2 class="onewebp-settings-section"><?php esc_html_e( 'Image Deletion Mode', 'onewebp' ); ?></h2>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Deletion Mode', 'onewebp' ); ?></th>
							<td>
								<?php $current_mode = get_option( 'onewebp_image_deletion_mode', 'sync' ); ?>
								<fieldset>
									<label class="onewebp-deletion-option <?php echo 'sync' === $current_mode ? 'selected' : ''; ?>">
										<input type="radio" name="onewebp_image_deletion_mode" value="sync" <?php checked( $current_mode, 'sync' ); ?>>
										<span class="option-title"><?php esc_html_e( 'Sync Delete (Recommended)', 'onewebp' ); ?></span>
										<span class="option-desc"><?php esc_html_e( 'When originals are deleted, WebP files are deleted too. Original JPG/PNG remains as fallback for all browsers.', 'onewebp' ); ?></span>
									</label>
									<label class="onewebp-deletion-option <?php echo 'keep' === $current_mode ? 'selected' : ''; ?>">
										<input type="radio" name="onewebp_image_deletion_mode" value="keep" <?php checked( $current_mode, 'keep' ); ?>>
										<span class="option-title"><?php esc_html_e( 'Keep WebP After Deletion', 'onewebp' ); ?></span>
										<span class="option-desc"><?php esc_html_e( 'WebP files remain even when originals are deleted. Media library displays WebP versions. Saves storage but removes fallback.', 'onewebp' ); ?></span>
									</label>
									<label class="onewebp-deletion-option onewebp-deletion-option-danger <?php echo 'delete_original' === $current_mode ? 'selected' : ''; ?>">
										<input type="radio" name="onewebp_image_deletion_mode" value="delete_original" <?php checked( $current_mode, 'delete_original' ); ?>>
										<span class="option-title onewebp-option-title-danger"><?php esc_html_e( 'Delete Original After Conversion', 'onewebp' ); ?></span>
										<span class="option-desc"><?php esc_html_e( 'Delete originals immediately after conversion. Maximum storage savings. NO fallback for older browsers. Cannot be undone.', 'onewebp' ); ?></span>
									</label>
								</fieldset>

								<label class="onewebp-replace-option">
									<input type="checkbox" name="onewebp_replace_original_immediately" value="1" <?php checked( get_option( 'onewebp_replace_original_immediately', 0 ), 1 ); ?>>
									<?php esc_html_e( 'Replace original with WebP immediately after conversion', 'onewebp' ); ?>
								</label>
								<span class="onewebp-replace-desc"><?php esc_html_e( 'Recommended for Delete Original mode. If disabled, the original stays until you manually convert from the Image Manager.', 'onewebp' ); ?></span>

								<div class="onewebp-mode-comparison">
									<h3><?php esc_html_e( 'Mode Comparison', 'onewebp' ); ?></h3>
									<table>
										<thead>
											<tr>
												<th><?php esc_html_e( 'Mode', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'Original File', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'WebP File', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'Media Library Shows', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'Fallback Support', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'Storage', 'onewebp' ); ?></th>
												<th><?php esc_html_e( 'Browser Compatibility', 'onewebp' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<tr>
												<td class="sync-color"><?php esc_html_e( 'Sync Delete', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Kept', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Kept', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Original JPG/PNG', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Full', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Normal', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'All browsers', 'onewebp' ); ?></td>
											</tr>
											<tr>
												<td class="keep-color"><?php esc_html_e( 'Keep WebP', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Deleted', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Kept', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'WebP', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Limited', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Optimized', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'WebP-only', 'onewebp' ); ?></td>
											</tr>
											<tr>
												<td class="delete-color"><?php esc_html_e( 'Delete Original', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Deleted', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Kept', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'WebP', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'None', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'Maximum', 'onewebp' ); ?></td>
												<td><?php esc_html_e( 'WebP-only', 'onewebp' ); ?></td>
											</tr>
										</tbody>
									</table>
								</div>
							</td>
						</tr>
					</table>

					<?php submit_button( __( 'Save Settings', 'onewebp' ) ); ?>
				</form>

				<hr>

				<div class="onewebp-card">
					<h2><?php esc_html_e( 'Reset Options', 'onewebp' ); ?></h2>
					<form method="post" action="" class="onewebp-reset-settings-form">
						<?php wp_nonce_field( 'onewebp_reset_settings' ); ?>
						<input type="hidden" name="onewebp_reset_action" value="reset_settings">
						<input type="submit" class="button" value="<?php esc_attr_e( 'Reset to Default Settings', 'onewebp' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure?', 'onewebp' ) ); ?>');">
					</form>
					<button type="button" id="reset-data-btn" class="button button-link-delete"><?php esc_html_e( 'Clear All Data', 'onewebp' ); ?></button>
					<div id="reset-progress-container" class="onewebp-reset-progress">
						<p><?php esc_html_e( 'Clearing Data...', 'onewebp' ); ?></p>
						<div class="bar">
							<div id="reset-progress-fill" class="fill"></div>
							<div id="reset-progress-text" class="text">0%</div>
						</div>
						<p id="reset-status-text" class="status"><?php esc_html_e( 'Initializing...', 'onewebp' ); ?></p>
					</div>
				</div>

			<?php elseif ( 'manager' === $tab ) : ?>
				<?php
				if ( isset( $_GET['action'] ) && 'edit' === $_GET['action'] && isset( $_GET['log_id'] ) ) {
					global $wpdb;
					$log_id = intval( $_GET['log_id'] );
					$log    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}onewebp_logs WHERE id = %d", $log_id ) );
					if ( $log ) :
						$upload_dir = wp_upload_dir();
						$img_url    = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $log->original_url );
						?>
						<div class="onewebp-card onewebp-edit-card">
							<h2><?php esc_html_e( 'Edit Conversion Settings', 'onewebp' ); ?></h2>
							<img src="<?php echo esc_url( $img_url ); ?>" class="onewebp-edit-preview" alt="">
							<form method="post">
								<?php wp_nonce_field( 'onewebp_edit_' . $log_id, 'onewebp_edit_nonce' ); ?>
								<table class="form-table">
									<tr><th><?php esc_html_e( 'Quality (50-100)', 'onewebp' ); ?></th><td><input type="number" name="custom_quality" value="82" min="50" max="100" class="small-text" required></td></tr>
									<tr><th><?php esc_html_e( 'Max Resolution (px)', 'onewebp' ); ?></th><td><input type="number" name="custom_max_res" value="3000" min="100" class="small-text" required></td></tr>
								</table>
								<input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Apply & Re-convert', 'onewebp' ); ?>">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=onewebp&tab=manager' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'onewebp' ); ?></a>
							</form>
						</div>
						<?php
					endif;
				} else {
					$list_table = new OneWebP_List_Table();
					$list_table->prepare_items();
					$list_table->display();
				}
				?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}