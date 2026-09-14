<?php
/**
 * List table for OneWebP image manager.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class OneWebP_List_Table
 */
class OneWebP_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'log',
				'plural'   => 'logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define the table columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'preview'       => __( 'Preview', 'onewebp' ),
			'size_name'     => __( 'Size', 'onewebp' ),
			'original_size' => __( 'Original', 'onewebp' ),
			'webp_size'     => __( 'WebP', 'onewebp' ),
			'actions'       => __( 'Actions', 'onewebp' ),
		);
	}

	/**
	 * Define the sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'original_size' => array( 'original_size', false ),
			'webp_size'     => array( 'webp_size', false ),
		);
	}

	/**
	 * Define the bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete'     => __( 'Delete', 'onewebp' ),
			'reoptimize' => __( 'Reoptimize', 'onewebp' ),
		);
	}

	/**
	 * Render the checkbox column.
	 *
	 * @param array $item Item data.
	 * @return string
	 */
	public function column_cb( $item ) {
		return '<input type="checkbox" name="log_ids[]" value="' . esc_attr( $item['id'] ) . '" />';
	}

	/**
	 * Prepare the items for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;
		$table        = $wpdb->prefix . 'onewebp_logs';
		$per_page     = 30;
		$current_page = $this->get_pagenum();

		$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$where  = '';
		if ( ! empty( $search ) ) {
			$where = $wpdb->prepare( ' WHERE original_url LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		$total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} {$where}" );

		$allowed_orderby = array( 'id', 'original_size', 'webp_size', 'size_name', 'status' );
		$orderby         = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'id';
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'id';
		}

		$order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
		$order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
				$per_page,
				( $current_page - 1 ) * $per_page
			),
			ARRAY_A
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
			)
		);

		$this->_column_headers = array(
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
			$this->get_primary_column_name(),
		);
	}

	/**
	 * Render default columns.
	 *
	 * @param array  $item        Item data.
	 * @param string $column_name Column name.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		$upload_dir = wp_upload_dir();

		switch ( $column_name ) {
			case 'preview':
				$img_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['original_url'] );

				$wrap_style = 'display:inline-flex !important;'
					. 'align-items:center !important;'
					. 'justify-content:center !important;'
					. 'width:80px !important;'
					. 'height:80px !important;'
					. 'min-width:80px !important;'
					. 'min-height:80px !important;'
					. 'max-width:80px !important;'
					. 'max-height:80px !important;'
					. 'overflow:hidden !important;'
					. 'line-height:0 !important;'
					. 'vertical-align:middle !important;'
					. 'margin:0 !important;'
					. 'padding:0 !important;'
					. 'float:none !important;'
					. 'box-sizing:border-box !important;'
					. 'background:#f6f7f7 !important;'
					. 'border:1px solid #ddd !important;'
					. 'border-radius:3px !important;';

				$img_style = 'width:auto !important;'
					. 'height:auto !important;'
					. 'max-width:100% !important;'
					. 'max-height:100% !important;'
					. 'min-width:0 !important;'
					. 'min-height:0 !important;'
					. 'object-fit:contain !important;'
					. 'display:block !important;'
					. 'margin:0 !important;'
					. 'padding:0 !important;'
					. 'border:0 !important;'
					. 'float:none !important;'
					. 'box-sizing:border-box !important;';

				return '<span class="onewebp-preview-wrap" style="' . esc_attr( $wrap_style ) . '">'
					. '<img src="' . esc_url( $img_url ) . '" class="onewebp-manager-thumb" style="' . esc_attr( $img_style ) . '" alt="">'
					. '</span>';

			case 'size_name':
				$file_name  = basename( $item['original_url'] );
				$dimensions = $this->get_image_dimensions( $item );
				$img_url    = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['original_url'] );

				$output  = '<strong><a href="' . esc_url( $img_url ) . '" target="_blank" rel="noopener noreferrer" class="onewebp-file-link">' . esc_html( $file_name ) . '</a></strong>';
				$output .= '<br><span class="onewebp-size-label">' . esc_html( $item['size_name'] ) . '</span>';

				if ( $dimensions ) {
					$output .= ' <span class="onewebp-size-label">(' . esc_html( $dimensions ) . ')</span>';
				}

				return $output;

			case 'original_size':
				return $item['original_size'] > 0 ? size_format( $item['original_size'], 2 ) : '-';

			case 'webp_size':
				// Native WebP: already optimized, no conversion needed.
				if ( 'native' === $item['status'] ) {
					return '<span class="onewebp-native-webp">' . esc_html__( 'Already WebP', 'onewebp' ) . '</span>';
				}

				if ( 'success' === $item['status'] && $item['webp_size'] > 0 ) {
					$webp_size_text = size_format( $item['webp_size'], 2 );

					if ( $item['original_size'] > 0 ) {
						if ( $item['webp_size'] < $item['original_size'] ) {
							$p               = round( ( 1 - $item['webp_size'] / $item['original_size'] ) * 100, 1 );
							$webp_size_text .= ' <span class="onewebp-savings-positive">(-' . esc_html( $p ) . '%)</span>';
						} else {
							$p               = round( ( $item['webp_size'] / $item['original_size'] - 1 ) * 100, 1 );
							$webp_size_text .= ' <span class="onewebp-savings-negative">(+' . esc_html( $p ) . '%)</span>';
						}
					}

					$webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['webp_url'] );
					return '<a href="' . esc_url( $webp_url ) . '" target="_blank" rel="noopener noreferrer" class="onewebp-webp-link">' . $webp_size_text . '</a>';
				}

				if ( 'pending' === $item['status'] ) {
					return '<span class="onewebp-pending">' . esc_html__( 'Pending', 'onewebp' ) . '</span>';
				}

				if ( 'failed' === $item['status'] ) {
					$ext            = strtolower( pathinfo( $item['original_url'], PATHINFO_EXTENSION ) );
					$supported_exts = array( 'jpg', 'jpeg', 'png', 'gif' );

					if ( ! in_array( $ext, $supported_exts, true ) ) {
						return '<span class="onewebp-unsupported">' . esc_html__( 'Unsupported Format', 'onewebp' ) . '</span>';
					}

					$base_url     = admin_url( 'admin.php?page=onewebp&tab=manager' );
					$nonce        = wp_create_nonce( 'onewebp_action_' . $item['id'] );
					$optimize_url = esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
					return '<a href="' . $optimize_url . '" class="button button-small">' . esc_html__( 'Retry', 'onewebp' ) . '</a>';
				}

				return '-';

			case 'actions':
				$ext      = strtolower( pathinfo( $item['original_url'], PATHINFO_EXTENSION ) );
				$base_url = admin_url( 'admin.php?page=onewebp&tab=manager' );
				$nonce    = wp_create_nonce( 'onewebp_action_' . $item['id'] );

				// Native WebP: only allow removal of the log record.
				if ( 'native' === $item['status'] ) {
					$remove_url = esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
					return '<a href="' . $remove_url . '" class="onewebp-action-remove" onclick="return confirm(\'' . esc_js( __( 'Delete record?', 'onewebp' ) ) . '\');">' . esc_html__( 'Remove', 'onewebp' ) . '</a>';
				}

				if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif' ), true ) ) {
					$remove_url = esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
					return '<a href="' . $remove_url . '" class="onewebp-action-remove" onclick="return confirm(\'' . esc_js( __( 'Delete record?', 'onewebp' ) ) . '\');">' . esc_html__( 'Remove', 'onewebp' ) . '</a>';
				}

				if ( 'success' === $item['status'] && ! empty( $item['webp_url'] ) ) {
					$webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['webp_url'] );

					$actions               = array();
					$actions['edit']       = '<a href="' . esc_url( $base_url . '&action=edit&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '">' . esc_html__( 'Edit', 'onewebp' ) . '</a>';
					$actions['reoptimize'] = '<a href="' . esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '">' . esc_html__( 'Reoptimize', 'onewebp' ) . '</a>';
					$actions['remove']     = '<a href="' . esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '" class="onewebp-action-remove" onclick="return confirm(\'' . esc_js( __( 'Delete this WebP file?', 'onewebp' ) ) . '\');">' . esc_html__( 'Remove', 'onewebp' ) . '</a>';
					$actions['copy_url']   = '<a href="#" class="onewebp-copy-url" data-url="' . esc_attr( $webp_url ) . '">' . esc_html__( 'Copy URL', 'onewebp' ) . '</a>';

					return implode( ' | ', $actions );
				}

				if ( 'pending' === $item['status'] ) {
					return '-';
				}

				return '';

			default:
				return esc_html( isset( $item[ $column_name ] ) ? $item[ $column_name ] : '' );
		}
	}

	/**
	 * Get image dimensions for an attachment size.
	 *
	 * @param array $item Item data.
	 * @return string|false
	 */
	private function get_image_dimensions( $item ) {
		if ( ! empty( $item['attachment_id'] ) ) {
			$meta = wp_get_attachment_metadata( $item['attachment_id'] );
			if ( $meta && isset( $meta['width'], $meta['height'] ) ) {
				if ( 'original' === $item['size_name'] ) {
					return $meta['width'] . 'x' . $meta['height'];
				}
			}

			if ( $meta && ! empty( $meta['sizes'][ $item['size_name'] ] ) ) {
				$size_data = $meta['sizes'][ $item['size_name'] ];
				if ( isset( $size_data['width'], $size_data['height'] ) ) {
					return $size_data['width'] . 'x' . $size_data['height'];
				}
			}
		}

		if ( file_exists( $item['original_url'] ) ) {
			$info = @getimagesize( $item['original_url'] );
			if ( $info && isset( $info[0], $info[1] ) ) {
				return $info[0] . 'x' . $info[1];
			}
		}

		$file_name = basename( $item['original_url'] );
		if ( preg_match( '/-(\d+)x(\d+)\./', $file_name, $matches ) ) {
			return $matches[1] . 'x' . $matches[2];
		}

		return false;
	}
}