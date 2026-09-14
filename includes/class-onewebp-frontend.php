<?php
/**
 * Frontend handler for WebP conversion with lazy loading and progressive loading.
 *
 * @package OneWebP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OneWebP_Frontend
 */
class OneWebP_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'template_redirect', array( $this, 'start_buffer' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Start output buffering on the frontend.
	 *
	 * @return void
	 */
	public function start_buffer() {
		if ( is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! (bool) get_option( 'onewebp_enable_picture_wrapper', 1 ) ) {
			return;
		}
		ob_start( array( $this, 'process_html' ) );
	}

	/**
	 * Process the final HTML output.
	 *
	 * Applies several passes to cover every common way an image URL can
	 * appear in the final HTML: <img> tags, inline style attributes,
	 * <style> blocks, and common data-* attributes used by lazy loaders.
	 *
	 * @param string $html Full HTML output.
	 * @return string Modified HTML.
	 */
	public function process_html( $html ) {
		if ( strlen( $html ) < 100 ) {
			return $html;
		}

		if ( ! (bool) get_option( 'onewebp_enable_picture_wrapper', 1 ) ) {
			return $html;
		}

		$upload_info        = wp_upload_dir();
		$base_url           = $upload_info['baseurl'];
		$base_dir           = $upload_info['basedir'];
		$first_n            = intval( get_option( 'onewebp_first_n_direct', 3 ) );
		$enable_lazy        = (bool) get_option( 'onewebp_enable_lazyload', 1 );
		$enable_progressive = (bool) get_option( 'onewebp_enable_progressive', 1 );

		// Pass 1: <img> tags to <picture> with WebP sources.
		if ( strpos( $html, '<img' ) !== false ) {
			$html = $this->process_images_regex( $html, $base_url, $base_dir, $first_n, $enable_lazy, $enable_progressive );
		}

		// Pass 2: inline style="..." attributes containing url(...).
		if ( strpos( $html, 'style=' ) !== false && strpos( $html, 'url(' ) !== false ) {
			$html = $this->process_inline_backgrounds( $html, $base_url, $base_dir );
		}

		// Pass 3: <style>...</style> blocks.
		if ( strpos( $html, '<style' ) !== false && strpos( $html, 'url(' ) !== false ) {
			$html = $this->process_style_blocks( $html, $base_url, $base_dir );
		}

		// Pass 4: data-* attributes used by lazy loaders and galleries.
		$html = $this->process_data_attributes( $html, $base_url, $base_dir );

		return $html;
	}

	/**
	 * Replace background-image URLs inside inline style attributes.
	 *
	 * @param string $html     Full HTML output.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Modified HTML.
	 */
	private function process_inline_backgrounds( $html, $base_url, $base_dir ) {
		return preg_replace_callback(
			'/style=(["\'])([^"\']*url\([^"\']*)\1/i',
			function ( $matches ) use ( $base_url, $base_dir ) {
				$quote = $matches[1];
				$style = $matches[2];

				$new_style = $this->replace_background_urls( $style, $base_url, $base_dir );

				if ( $new_style === $style ) {
					return $matches[0];
				}

				return 'style=' . $quote . $new_style . $quote;
			},
			$html
		);
	}

	/**
	 * Replace URLs inside <style> blocks.
	 *
	 * @param string $html     Full HTML output.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Modified HTML.
	 */
	private function process_style_blocks( $html, $base_url, $base_dir ) {
		return preg_replace_callback(
			'/<style\b([^>]*)>(.*?)<\/style>/is',
			function ( $matches ) use ( $base_url, $base_dir ) {
				$attrs = $matches[1];
				$css   = $matches[2];

				$new_css = $this->replace_background_urls( $css, $base_url, $base_dir );

				if ( $new_css === $css ) {
					return $matches[0];
				}

				return '<style' . $attrs . '>' . $new_css . '</style>';
			},
			$html
		);
	}

	/**
	 * Replace URLs inside common data-* attributes used by lazy loaders,
	 * galleries, and theme builders.
	 *
	 * Attributes handled:
	 * - data-thumbnail
	 * - data-bg
	 * - data-background
	 * - data-background-image
	 * - data-src
	 * - data-lazy-src
	 * - data-original
	 * - data-large_image
	 * - data-image
	 * - poster (video covers)
	 *
	 * @param string $html     Full HTML output.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Modified HTML.
	 */
	private function process_data_attributes( $html, $base_url, $base_dir ) {
		$attributes = array(
			'data-thumbnail',
			'data-bg',
			'data-background',
			'data-background-image',
			'data-src',
			'data-lazy-src',
			'data-original',
			'data-large_image',
			'data-image',
			'poster',
		);

		foreach ( $attributes as $attr ) {
			// Quick skip if the attribute is not present at all.
			if ( strpos( $html, $attr . '=' ) === false ) {
				continue;
			}

			$pattern = '/' . preg_quote( $attr, '/' ) . '=(["\'])([^"\']+)\1/i';

			$html = preg_replace_callback(
				$pattern,
				function ( $matches ) use ( $base_url, $base_dir ) {
					$quote = $matches[1];
					$url   = $matches[2];

					$new_url = $this->maybe_swap_url( $url, $base_url, $base_dir );

					if ( $new_url === $url ) {
						return $matches[0];
					}

					return $matches[0] === $url ? $matches[0] : str_replace( $url, $new_url, $matches[0] );
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Replace background URLs in a CSS fragment.
	 *
	 * Handles plain quotes, HTML entities, and unquoted URLs.
	 *
	 * @param string $css      CSS fragment.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Modified CSS fragment.
	 */
	private function replace_background_urls( $css, $base_url, $base_dir ) {
		if ( strpos( $css, 'url(' ) === false ) {
			return $css;
		}

		return preg_replace_callback(
			'/url\(\s*(&quot;|&#0?39;|&#0?34;|["\']?)([^"\'&)]+)\1\s*\)/i',
			function ( $url_match ) use ( $base_url, $base_dir ) {
				$quote = $url_match[1];
				$url   = $url_match[2];

				$new_url = $this->maybe_swap_url( $url, $base_url, $base_dir );

				if ( $new_url === $url ) {
					return $url_match[0];
				}

				return 'url(' . $quote . $new_url . $quote . ')';
			},
			$css
		);
	}

	/**
	 * Swap a single URL for its WebP variant if conditions are met.
	 *
	 * Returns the original URL when:
	 * - the URL is a data: URI
	 * - the URL is already a .jo.webp file
	 * - the URL is not inside the uploads directory
	 * - the URL does not end in a supported image extension
	 * - the matching .jo.webp file does not exist on disk
	 *
	 * @param string $url      Original URL.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Swapped URL or the original URL.
	 */
	private function maybe_swap_url( $url, $base_url, $base_dir ) {
		// Skip data URIs.
		if ( strpos( $url, 'data:' ) === 0 ) {
			return $url;
		}

		// Skip already rewritten URLs.
		if ( strpos( $url, '.jo.webp' ) !== false ) {
			return $url;
		}

		// Only process URLs in the uploads directory.
		if ( strpos( $url, $base_url ) !== 0 ) {
			return $url;
		}

		// Only process image extensions.
		if ( ! preg_match( '/\.(jpe?g|png|gif)(\?.*)?$/i', $url ) ) {
			return $url;
		}

		$url_path = preg_replace( '/\?.*$/', '', $url );

		$webp_url  = $url_path . '.jo.webp';
		$webp_path = str_replace( $base_url, $base_dir, $webp_url );

		if ( ! file_exists( $webp_path ) ) {
			return $url;
		}

		return $webp_url;
	}

	/**
	 * Rewrite <img> tags into <picture> tags with WebP sources.
	 *
	 * @param string $html               Full HTML output.
	 * @param string $base_url           Upload base URL.
	 * @param string $base_dir           Upload base directory.
	 * @param int    $first_n            Number of images to load directly.
	 * @param bool   $enable_lazy        Whether lazy loading is enabled.
	 * @param bool   $enable_progressive Whether progressive loading is enabled.
	 * @return string Modified HTML.
	 */
	private function process_images_regex( $html, $base_url, $base_dir, $first_n, $enable_lazy, $enable_progressive ) {
		$counter = 0;

		return preg_replace_callback(
			'/<img\b([^>]*)>/i',
			function ( $matches ) use ( $base_url, $base_dir, $first_n, $enable_lazy, $enable_progressive, &$counter ) {
				$counter++;
				$img_attrs = trim( $matches[1] );

				preg_match( '/src=["\']([^"\']+)["\']/', $img_attrs, $src_match );
				if ( empty( $src_match[1] ) ) {
					return $matches[0];
				}

				$src = $src_match[1];

				if ( strpos( $src, $base_url ) !== 0 ) {
					return $matches[0];
				}

				$webp_url  = $src . '.jo.webp';
				$webp_path = str_replace( $base_url, $base_dir, $webp_url );

				if ( ! file_exists( $webp_path ) ) {
					return $matches[0];
				}

				$lqip_url = '';
				if ( $enable_progressive ) {
					$lqip_path      = $src . '.lqip.webp';
					$lqip_full_path = str_replace( $base_url, $base_dir, $lqip_path );
					if ( file_exists( $lqip_full_path ) ) {
						$lqip_url = $lqip_path;
					}
				}

				$is_critical    = ( $counter <= $first_n );
				$is_progressive = ! empty( $lqip_url ) && ! $is_critical && $enable_lazy;

				$picture = '<picture>';

				if ( $is_progressive ) {
					$picture .= '<source srcset="' . esc_attr( $lqip_url ) . '" type="image/webp" data-lqip="true">';
				}

				if ( ! $is_critical && $enable_lazy ) {
					$picture .= '<source data-srcset="' . esc_attr( $webp_url ) . '" type="image/webp"';
					if ( $is_progressive ) {
						$picture .= ' data-progressive="true"';
					}
					$picture .= '>';
				} else {
					$picture .= '<source srcset="' . esc_attr( $webp_url ) . '" type="image/webp">';
				}

				$img_tag = '<img ' . $img_attrs;

				preg_match( '/srcset=["\']([^"\']+)["\']/', $img_attrs, $srcset_match );
				if ( ! empty( $srcset_match[1] ) ) {
					$srcset      = $srcset_match[1];
					$webp_srcset = $this->convert_srcset_to_webp( $srcset, $base_url, $base_dir );
					if ( ! empty( $webp_srcset ) && $webp_srcset !== $srcset ) {
						if ( ! $is_critical && $enable_lazy ) {
							$img_tag = preg_replace( '/\s+srcset=["\'][^"\']+["\']/', '', $img_tag );
							$img_tag = str_replace( '<img ', '<img data-srcset="' . esc_attr( $webp_srcset ) . '" ', $img_tag );
						} else {
							$img_tag = preg_replace( '/srcset=["\'][^"\']+["\']/', 'srcset="' . esc_attr( $webp_srcset ) . '"', $img_tag );
						}
					}
				}

				if ( ! $is_critical && $enable_lazy ) {
					if ( $is_progressive && ! empty( $lqip_url ) ) {
						$img_tag = preg_replace( '/\s+src=["\'][^"\']+["\']/', ' src="' . esc_attr( $lqip_url ) . '"', $img_tag );
						$img_tag = str_replace( '<img ', '<img data-src="' . esc_attr( $src ) . '" data-progressive="true" ', $img_tag );
					} else {
						$placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
						$img_tag     = preg_replace( '/\s+src=["\'][^"\']+["\']/', ' src="' . $placeholder . '"', $img_tag );
						$img_tag     = str_replace( '<img ', '<img data-src="' . esc_attr( $src ) . '" ', $img_tag );
					}

					if ( strpos( $img_tag, 'class=' ) === false ) {
						$img_tag = str_replace( '<img ', '<img class="onewebp-lazy-img" ', $img_tag );
					} else {
						$img_tag = preg_replace( '/class=["\']([^"\']*)["\']/', 'class="$1 onewebp-lazy-img"', $img_tag );
					}

					$img_tag = preg_replace( '/\s+srcset=["\'][^"\']+["\']/', '', $img_tag );
				} else {
					if ( 1 === $counter ) {
						$img_tag = str_replace( '<img ', '<img fetchpriority="high" ', $img_tag );
					}
				}

				$picture .= $img_tag . '>';
				$picture .= '</picture>';

				return $picture;
			},
			$html
		);
	}

	/**
	 * Convert a srcset string to WebP URLs where available.
	 *
	 * @param string $srcset   Original srcset string.
	 * @param string $base_url Upload base URL.
	 * @param string $base_dir Upload base directory.
	 * @return string Converted srcset string.
	 */
	private function convert_srcset_to_webp( $srcset, $base_url, $base_dir ) {
		$srcset_parts = explode( ',', $srcset );
		$webp_srcset  = array();

		foreach ( $srcset_parts as $part ) {
			$part = trim( $part );
			if ( empty( $part ) ) {
				continue;
			}

			preg_match( '/^([^\s]+)\s*(.*)$/', $part, $matches );
			if ( empty( $matches[1] ) ) {
				$webp_srcset[] = $part;
				continue;
			}

			$url        = $matches[1];
			$descriptor = isset( $matches[2] ) ? ' ' . $matches[2] : '';

			$new_url = $this->maybe_swap_url( $url, $base_url, $base_dir );

			if ( $new_url !== $url ) {
				$webp_srcset[] = $new_url . $descriptor;
			} else {
				$webp_srcset[] = $part;
			}
		}

		return implode( ', ', $webp_srcset );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( (bool) get_option( 'onewebp_enable_lazyload', 1 ) ) {
			wp_enqueue_script(
				'onewebp-frontend-js',
				ONEWEBP_URL . 'assets/js/frontend-lazy.js',
				array(),
				ONEWEBP_VERSION,
				true
			);
		}
	}
}