# Jacker OneWebP

**100% Free, Unlimited Local WebP Optimizer & SEO Booster for WordPress.**

Jacker OneWebP is a lightweight, open-source WordPress plugin designed to boost your website's performance and Core Web Vitals. It converts your images to the modern WebP format locally on your server, ensuring maximum privacy, zero API limits, and faster loading speeds.

![Version](https://img.shields.io/badge/version-1.0.0-blue)
![License](https://img.shields.io/badge/license-GPL--2.0-green)
![WordPress](https://img.shields.io/badge/WordPress-6.1%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

---

## Features

| Feature | Description |
|---------|-------------|
| **100% Local Processing** | Zero API calls. Images never leave your server. Maximum privacy guaranteed. |
| **Unlimited & Free** | No monthly limits, no quotas, no hidden fees. Convert as many images as you want. |
| **Smart Memory Engine** | Dynamically calculates memory requirements and adjusts batch sizes. Prevents 502 errors on shared hosting. |
| **Smart Queue Lazy Loading** | Critical images load instantly with `fetchpriority="high"`, others lazy-load via smart queue. Boosts LCP and Core Web Vitals. |
| **First N Direct Load** | A dedicated setting that controls how many top-of-page images skip lazy loading. The single most effective way to fix LCP warnings without breaking the rest of your layout. |
| **Automatic Downscaling** | Automatically resizes images larger than your defined max resolution (default: 3000px). Saves bandwidth and improves load times. |
| **One-Click Dashboard** | Beautiful, native WordPress UI with real-time stats, progress bars, and bulk optimization. |
| **Server Health Monitor** | Proactive warnings for low disk space (under 500MB) and low RAM (under 64MB). Prevents disasters before they happen. |
| **Image Manager** | Detailed list of all converted images with individual actions: Edit, Reoptimize, Remove, Copy URL. |
| **Bulk Actions** | Delete or reoptimize multiple images at once. |
| **Search & Filter** | Quickly find images in the manager. |

---

## Why Jacker OneWebP?

| Feature | Traditional API Plugins | Jacker OneWebP (Local Engine) |
| :--- | :---: | :---: |
| **Cost** | Monthly limits & subscriptions | 100% Free, Unlimited |
| **Privacy** | Images sent to external servers | 100% Private (stays on server) |
| **Speed** | Slow due to network latency | Instant local processing |
| **Frontend Bloat** | Heavy external scripts | Ultra-lightweight, zero bloat |
| **Data Limits** | Monthly quotas | Unlimited conversions |
| **Processing** | Cloud-based (third-party) | Local (your own server) |

---

## Supported Formats

| Format | Support | Notes |
|--------|---------|-------|
| **JPEG/JPG to WebP** | Fully supported | Best compression results. Up to 80% smaller. |
| **PNG to WebP** | Fully supported | Preserves transparent background. |
| **GIF to WebP** | Static only | Animated GIFs become static (first frame only). GD library limitation. |
| **WebP** | Already WebP | No conversion needed. |
| **HEIC / HEIF** | Not supported | Requires ImageMagick with libheif. Convert to JPEG before upload. |
| **AVIF** | Not supported | Not handled by the GD library. |
| **SVG** | Not supported | Vector format. Cannot be converted by GD library. |
| **BMP/TIFF** | Not recommended | Very large file sizes may cause memory issues. |

---

## Image Deletion Modes

Jacker OneWebP offers three deletion modes to give you full control over your images:

| Mode | Behavior | Best For |
|------|----------|----------|
| **Sync Delete (Recommended)** | When original images are deleted from Media Library, WebP files are automatically deleted too. | Most users. Keeps your server clean. |
| **Keep WebP After Deletion** | WebP files remain on server even when original images are deleted. | When you want to keep WebP versions for future use. |
| **Delete Original After Conversion** | Automatically delete original images after WebP conversion. Cannot re-convert! | Maximum space savings. Advanced users only. |

---

## Fixing LCP with First N Direct Load

The **First N Direct Load** setting controls how many top-of-page images skip lazy loading and receive `fetchpriority="high"`.

**Why this matters:**

- LCP images must NOT be lazy-loaded, otherwise the browser only discovers them after layout is done.
- `fetchpriority="high"` tells the browser to fetch the LCP image first.
- The optimal value is different for every site. It depends on how many images appear before your real LCP image in the DOM.
- On builder-heavy pages the value is often higher than expected, because icon and widget images come first.

**How to find the best value for your site:**

1. Run Lighthouse and note which image is flagged as the LCP candidate.
2. Increase the value until that image is covered.
3. Re-test. Adjust up or down until Performance stops improving.

On our own test site, tuning this single setting took Performance from **65 to 96** and LCP from **10.1s to 2.6s**.

---

## Works Great With

### Jacker CSS Merges Manager (JCSSMM)

[Jacker CSS Merges Manager](https://github.com/JackerArchitect/jacker-css-merges-manager) merges enqueued CSS files into a single request. It **auto-detects Jacker OneWebP** and, when active, rewrites `background-image` URLs inside the merged stylesheet to point at the `.jo.webp` files Jacker OneWebP generated.

- Install only Jacker OneWebP: only `img` and inline styles get WebP.
- Install only JCSSMM: CSS gets merged, no WebP rewriting.
- Install both: merged CSS references the WebP files automatically.

The two plugins do not depend on each other. No configuration is required beyond enabling them.

---

## Installation

### Quick Install (60 seconds)

1. Download the latest release ZIP file from the [Releases page](https://github.com/JackerArchitect/jacker-onewebp/releases).
2. Log in to your WordPress admin dashboard.
3. Navigate to **Plugins, Add New, Upload Plugin**.
4. Upload the `jacker-onewebp.zip` file and click **Install Now**.
5. Click **Activate Plugin**.
6. Go to the **Jacker OneWebP** menu in your sidebar.
7. Review settings and click **Start Optimization**.

### Manual Installation

1. Upload the `onewebp` folder to `/wp-content/plugins/` via FTP.
2. Activate the plugin through the Plugins menu in WordPress.
3. Configure settings and start optimizing.

---

## Quick Start

1. After activation, navigate to Jacker OneWebP in your WordPress admin.
2. Check the Settings tab and adjust:
   - WebP Quality (default: 82)
   - Max Resolution (default: 3000px)
   - Image Types to Convert
   - First N Direct Load (start with 5, tune for your layout)
   - Deletion Mode
3. Go to Dashboard and click **Start Optimization**.
4. Monitor real-time progress.

---

## Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| **WordPress** | 6.1+ |
| **PHP** | 7.4+ |
| **PHP Extension** | GD Library (required) |
| **Memory Limit** | 128MB+ (recommended) |
| **Disk Space** | 500MB+ free (recommended) |

---

## Performance Impact

| Metric | Before | After Jacker OneWebP | Improvement |
|--------|--------|----------------------|-------------|
| Average Image Size | 1.2 MB | 320 KB | -73% |
| LCP Score | 3.4s | 1.2s | -65% |
| Page Load Time | 2.8s | 1.1s | -61% |
| Page Weight | 4.5 MB | 1.6 MB | -64% |
| Server CPU Usage | Normal | +5-10% | Minimal |

*Results may vary based on image content, server configuration, and hosting environment.*

---

## Privacy Guarantee

Jacker OneWebP is **100% private**:

- No external API calls
- No data sent to third-party servers
- No tracking or analytics
- No user data collection
- All processing happens on your own server
- Your images never leave your hosting environment
- Your privacy is 100% protected

---

## Known Issues

| Issue | Status | Workaround |
|-------|--------|------------|
| **Animated GIF conversion** | Not supported | Static GIF only. Use a dedicated GIF optimizer. |
| **HEIC / HEIF conversion** | Not supported | Convert to JPEG before upload, or enable server-side ImageMagick with libheif support. |
| **AVIF conversion** | Not supported | GD library does not handle AVIF. |
| **SVG conversion** | Not supported | SVG is a vector format. Keep as SVG for best results. |
| **BMP/TIFF conversion** | Not recommended | Large file sizes may cause memory issues. Convert manually first. |
| **Memory exhaustion** | Possible | Increase PHP memory_limit or reduce batch size. |

---

## Contributing

We welcome contributions. Here is how you can help:

1. **Report bugs** - Open an issue on GitHub.
2. **Suggest features** - Share your ideas.
3. **Submit PRs** - Fix bugs or add features.
4. **Improve documentation** - Help others understand the plugin.
5. **Translate** - Help make Jacker OneWebP available in more languages.

---

## Support & Feedback

Jacker OneWebP is built with passion. If you find this plugin useful, consider supporting the project.

| Channel | Contact |
|---------|---------|
| **Email** | [support@jackerteo.com](mailto:support@jackerteo.com) |
| **Website** | [jackerteo.com/plugin/jacker-onewebp](https://jackerteo.com/plugin/jacker-onewebp) |
| **GitHub Issues** | [Create an issue](https://github.com/JackerArchitect/jacker-onewebp/issues) |

### Buy Me a Coffee

If you would like to support open-source development, you can donate via Solana (SOL):

```
Wallet Address: EHHPsci6pKbfL71t73KNCXrtanM1TWPrWYJZ1ik1b5FH
Network: Solana Mainnet
```

---

## License

This project is licensed under the **GNU General Public License v2.0** (GPL-2.0+).

```
Copyright (C) 2026 Jacker Architect. All rights reserved.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

---

## Star Us

If you find Jacker OneWebP useful, please consider giving us a star on GitHub. It helps others discover the project and motivates us to keep improving it.

---

## Acknowledgments

- WordPress for the amazing CMS
- PHP GD Library for image processing
- All contributors and supporters
- The open source community

---

**Made with love by Jacker Architect**

---

*Jacker OneWebP - 100% Free, Unlimited, Zero API. Convert your images to WebP locally.*
