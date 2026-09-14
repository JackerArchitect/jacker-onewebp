=== OneWebP ===
Contributors: jackerteo
Tags: webp, image optimization, lazy load, performance, speed, free, local converter, gd, seo, core web vitals, progressive loading
Requires at least: 6.1
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

100% Free, Unlimited, Zero API. Convert your images to WebP locally. Smart lazy loading with progressive blur-to-sharp transitions.

== Description ==

OneWebP is the ultimate, truly free image optimization plugin for WordPress. No monthly fees, no image limits, and no third-party APIs. Your images never leave your server.

= Core Features =

* 100% Local Processing: Zero API calls. All conversion happens on your server using PHP GD. Maximum privacy.
* Unlimited and Free: Convert as many images as you want. No monthly quotas, no hidden fees.
* Smart Memory Engine: Dynamically calculates memory requirements and adjusts batch sizes. Prevents 502 errors on shared hosting.
* Smart Queue Lazy Loading: Critical images load instantly with fetchpriority="high", others lazy-load via smart queue (3 concurrent). Boosts LCP and Core Web Vitals.
* Progressive Loading (LQIP): Generates a tiny 12px placeholder (3-6KB) that loads instantly, then smoothly transitions to the full-quality WebP in under 500ms. Dramatically improves perceived performance.
* Automatic Downscaling: Automatically resizes images larger than your defined max resolution (default: 3000px). Saves bandwidth and improves load times.
* Global Picture Wrapper: Wraps every image in a picture tag with WebP source and fallback img. Works with existing picture tags and supports srcset.
* One-Click Dashboard: Native WordPress UI with real-time stats, progress bars, and bulk optimization.
* Server Health Monitor: Proactive warnings for low disk space (under 500MB) and low RAM (under 64MB).
* Image Manager: Detailed list of all converted images with actions: Edit, Reoptimize, Remove, Copy URL.
* Bulk Actions: Delete or reoptimize multiple images at once.
* Search and Filter: Quickly find images in the manager.
* External Image Support: Download and convert external images to WebP.

= Supported Formats =

* JPEG/JPG to WebP: Fully supported. Best compression results. Up to 80 percent smaller.
* PNG to WebP: Fully supported. Preserves transparent background.
* GIF to WebP: Static only. Animated GIFs become static (first frame only). GD library limitation.
* WebP: Already WebP. No conversion needed.
* SVG: Not supported. SVG is a vector format.
* BMP/TIFF: Not recommended. Very large file sizes may cause memory issues.

= Image Deletion Modes =

OneWebP offers three deletion modes to give you full control over your images:

1. Sync Delete (Recommended)

When original images are deleted from Media Library, WebP files are automatically deleted too. Full fallback support: original JPG/PNG remains for browsers without WebP support.

2. Keep WebP After Deletion

WebP files remain on server even when original images are deleted. Media library displays WebP versions. Saves storage space.

Warning: Deleting original images removes the fallback for browsers without WebP support. Users on older browsers (Safari older than 14, IE) will see broken images.

Media library shows WebP with all thumbnail sizes available.

Saves storage space by removing originals.

3. Delete Original After Conversion

Automatically delete original images after WebP conversion. Maximum storage savings.

WARNING: NO FALLBACK SUPPORT. Original files are permanently deleted. Users on browsers without WebP support will see broken images. Cannot be undone.

When switching to this mode, existing pending images will trigger a notice asking you to either:
- Convert and delete originals
- Convert but keep originals
- Cancel (leave them as pending)

New uploads will immediately follow Delete Original mode.

= Mode Comparison =

Sync Delete (Recommended)
- Original File: Kept
- WebP File: Kept
- Media Library Shows: Original JPG/PNG
- Fallback Support: Full
- Storage Space: Normal
- Browser Compatibility: All browsers

Keep WebP After Deletion
- Original File: Deleted
- WebP File: Kept (replaces original)
- Media Library Shows: WebP
- Fallback Support: Limited
- Storage Space: Optimized
- Browser Compatibility: WebP-only

Delete Original After Conversion
- Original File: Deleted
- WebP File: Kept (replaces original)
- Media Library Shows: WebP
- Fallback Support: None
- Storage Space: Maximum
- Browser Compatibility: WebP-only

= How It Works =

OneWebP creates WebP files with the suffix .jo.webp (e.g., image.jpg.jo.webp) and LQIP files with .lqip.webp (e.g., image.jpg.lqip.webp). This unique naming prevents conflicts with other plugins and allows safe cleanup.

= Installation =

= Quick Install (60 seconds) =

1. Download the latest release ZIP file from the releases page or GitHub.
2. Log in to your WordPress admin dashboard.
3. Navigate to Plugins, Add New, Upload Plugin.
4. Upload the onewebp.zip file and click Install Now.
5. Click Activate Plugin.
6. Go to the OneWebP menu in your sidebar.
7. Review settings and click Start Optimization.

= Manual Installation =

1. Upload the onewebp folder to /wp-content/plugins/ via FTP.
2. Activate the plugin through the Plugins menu in WordPress.
3. Configure settings and start optimizing.

== Frequently Asked Questions ==

= Does this work with animated GIFs? =

No. The GD library used for conversion does not support animated GIF conversion. Animated GIFs will be skipped and marked as failed in the Image Manager.

= Will this slow down my site? =

The conversion process runs in the background via AJAX. Frontend lazy loading with 3 concurrent images ensures optimal performance for your visitors. Progressive loading provides a blurry preview instantly while the full image loads.

= What happens to my original images? =

Original images are preserved by default (Sync Delete mode). You can choose Keep WebP After Deletion or Delete Original After Conversion for storage savings, but these remove fallback support for older browsers.

= Does this work with WooCommerce? =

Yes. OneWebP converts all product images and thumbnails automatically. Works perfectly with WooCommerce and all major page builders (Elementor, Gutenberg, WPBakery, etc.).

= Is this really 100 percent free? =

Yes. OneWebP is completely free, open-source (GPL-2.0+), and will always be free. No hidden costs, no premium versions, no API limits.

= What is Progressive Loading? =

OneWebP generates a tiny placeholder image (12px, 3-6KB) that loads instantly, then smoothly transitions to the full-quality WebP in under 500ms. This dramatically improves perceived performance and helps achieve better Core Web Vitals scores (LCP, CLS).

= What PHP extensions are required? =

PHP GD Library is required for image conversion. Most WordPress hosting environments have this enabled by default.

= Does this support srcset? =

Yes. OneWebP automatically converts srcset URLs to WebP versions while preserving the original as fallback.

= What happens if a browser does not support WebP? =

When using Sync Delete mode, the original JPG/PNG serves as fallback. When using Keep WebP or Delete Original modes, the original fallback is removed, and older browsers (Safari older than 14, IE) may show broken images.

= Which browsers support WebP? =

WebP is supported by all modern browsers including Chrome, Edge, Firefox, Safari 14 and newer, and Opera. The only browsers without WebP support are Safari versions older than 14 and Internet Explorer.

= What happens when I switch to Delete Original mode? =

You will see an admin notice asking what to do with existing pending images. Options:
- Convert and Delete Originals
- Convert but Keep Originals
- Cancel (leave them as pending)

New uploads will follow Delete Original mode immediately.

= What happens when I delete an image from the media library? =

This depends on your selected deletion mode:
- Sync Delete: Both original and WebP files are deleted
- Keep WebP: Original is deleted, WebP remains (with original's metadata)
- Delete Original: Originals are already gone; WebP remains

== Screenshots ==

For screenshots and detailed documentation, please visit the GitHub repository:
https://github.com/JackerArchitect/onewebp

== Changelog ==

= 1.0.0 =
* Initial public release
* 100 percent local WebP conversion via PHP GD
* Smart lazy loading with 3-concurrent queue
* Progressive loading with LQIP (12px, 3-6KB, 300ms transition)
* Global picture wrapper with srcset support
* Real-time dashboard with progress tracking
* Image manager with bulk actions
* External image support
* Server health monitoring (disk space, RAM)
* 3 image deletion modes with comparison
* Delete Original mode with mode change notice
* Unique file naming (.jo.webp and .lqip.webp)
* Full internationalization (i18n) ready
* WordPress coding standards compliance

== Upgrade Notice ==

= 1.0.0 =
Initial release. OneWebP is a complete, production-ready WebP optimization plugin for WordPress.

== Privacy ==

OneWebP processes all images locally on your server. No data is sent to external services. Your images never leave your hosting environment.

== Support ==

* Email: support@jackerteo.com
* Website: https://jackerteo.com/plugin/onewebp
* GitHub: https://github.com/JackerArchitect/onewebp

== Credits ==

Developed and maintained by Jacker Architect.

---

OneWebP - 100% Free, Unlimited, Zero API. Convert your images to WebP locally.