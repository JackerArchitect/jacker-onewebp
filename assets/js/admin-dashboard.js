/**
 * OneWebP admin dashboard script.
 *
 * @package OneWebP
 */

jQuery(document).ready(function($) {
	var isRunning = false;
	var isScanning = false;
	var isResetting = false;

	/**
	 * Format a byte count into a human readable string.
	 *
	 * @param {number} bytes    Byte count.
	 * @param {number} decimals Number of decimal places.
	 * @return {string} Formatted string.
	 */
	function formatBytes(bytes, decimals) {
		if (bytes === 0) {
			return '0 Bytes';
		}
		var k = 1024;
		var dm = decimals < 0 ? 0 : decimals;
		var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));
		return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
	}

	/**
	 * Update the dashboard statistics.
	 *
	 * @param {Object} data Statistics data.
	 * @return {void}
	 */
	function updateDashboard(data) {
		if (!data) {
			return;
		}
		$('#stat-total').text(data.total || 0);
		$('#stat-converted').text(data.converted || 0);
		$('#stat-pending').text(data.pending || 0);
		$('#stat-failed').text(data.failed || 0);

		if ($('#stat-native').length > 0) {
			$('#stat-native').text(data.native || 0);
		}

		$('#saved-space').text(formatBytes(data.saved_bytes || 0));

		var progress = data.progress || 0;
		$('#main-progress-bar').css('width', progress + '%');
		$('#main-progress-text').text(progress + '%');

		if (progress >= 100) {
			$('#main-progress-bar').addClass('completed').removeClass('scanning');
			$('#main-progress-text').addClass('completed');
		} else {
			$('#main-progress-bar').removeClass('completed');
			$('#main-progress-text').removeClass('completed');
		}
	}

	/**
	 * Fetch dashboard statistics via AJAX.
	 *
	 * @return {void}
	 */
	function fetchStats() {
		$.post(onewebp_vars.ajaxurl, {
			action: 'onewebp_get_stats',
			nonce: onewebp_vars.nonce
		}, function(res) {
			if (res && res.success) {
				updateDashboard(res.data);
			}
		});
	}

	/**
	 * Animate the scan counter from 0 to the total number of files.
	 *
	 * The animation duration is capped so even very large libraries
	 * finish the animation within a few seconds. The numbers feel
	 * real-time while the backend completes in a single request.
	 *
	 * @param {number}   total      Total file count.
	 * @param {Function} onComplete Callback when the animation finishes.
	 * @return {void}
	 */
	function animateScan(total, onComplete) {
		if (total <= 0) {
			if (typeof onComplete === 'function') {
				onComplete();
			}
			return;
		}

		// Target animation duration in milliseconds.
		var targetDuration = 2500;

		// Per-step interval, clamped between 8ms and 60ms.
		var interval = Math.max(8, Math.min(60, targetDuration / total));

		var current = 0;

		$('#main-progress-bar').addClass('scanning').removeClass('completed');
		$('#main-progress-text').removeClass('completed');

		function step() {
			current++;

			if (current > total) {
				current = total;
			}

			$('#main-progress-text').text(current + ' / ' + total);

			var percent = (current / total) * 100;
			$('#main-progress-bar').css('width', percent + '%');

			$('#scan-status')
				.addClass('active')
				.removeClass('done')
				.text('Scanning library... ' + current + ' / ' + total + ' files');

			if (current < total) {
				setTimeout(step, interval);
			} else {
				if (typeof onComplete === 'function') {
					onComplete();
				}
			}
		}

		step();
	}

	/**
	 * Scan the media library.
	 *
	 * Fires a single AJAX request for the scan and starts a local
	 * animation at the same time. The scan and the animation run in
	 * parallel so the numbers feel real-time even though the backend
	 * finishes in one shot.
	 *
	 * @return {void}
	 */
	function scanLibrary() {
		if (isScanning) {
			return;
		}
		isScanning = true;

		$('#start-optimize-btn')
			.prop('disabled', true)
			.text(onewebp_vars.text_scanning)
			.css('opacity', '0.6');
		$('#rescan-btn').prop('disabled', true);

		$('#main-progress-bar').css('width', '0%').addClass('scanning').removeClass('completed');
		$('#main-progress-text').text('Scanning...').removeClass('completed');
		$('#scan-status').addClass('active').removeClass('done').text('Scanning library...');

		var scanDone = false;
		var animDone = false;

		function finishIfReady() {
			if (!scanDone || !animDone) {
				return;
			}

			$('#main-progress-bar').removeClass('scanning').addClass('completed');
			$('#main-progress-text').text('100%').addClass('completed');

			$('#scan-status')
				.removeClass('active')
				.addClass('done')
				.text('Scan complete. Ready to optimize.');

			isScanning = false;
			$('#start-optimize-btn').prop('disabled', false).text(onewebp_vars.text_start).css('opacity', '1');
			$('#rescan-btn').prop('disabled', false);

			fetchStats();
		}

		$.ajax({
			url: onewebp_vars.ajaxurl,
			type: 'POST',
			dataType: 'json',
			timeout: 120000,
			data: {
				action: 'onewebp_scan_library',
				nonce: onewebp_vars.nonce
			},
			success: function(res) {
				if (res && res.success) {
					var total = (res.data && res.data.total_files) ? res.data.total_files : 0;

					if (total > 0) {
						animateScan(total, function() {
							animDone = true;
							finishIfReady();
						});
					} else {
						$('#main-progress-bar').css('width', '100%').addClass('completed');
						$('#main-progress-text').text('0 / 0').addClass('completed');
						$('#scan-status').removeClass('active').addClass('done').text('No images found.');
						isScanning = false;
						$('#start-optimize-btn').prop('disabled', false).text(onewebp_vars.text_start).css('opacity', '1');
						$('#rescan-btn').prop('disabled', false);
					}

					scanDone = true;
					finishIfReady();
				} else {
					console.error('OneWebP scan failed:', res);
					scanDone = true;
					animDone = true;
					finishIfReady();
				}
			},
			error: function(xhr, status, error) {
				console.error('OneWebP scan AJAX error:', status, error, xhr.responseText);
				scanDone = true;
				animDone = true;
				finishIfReady();
			}
		});
	}

	if ($('#start-optimize-btn').length > 0) {
		scanLibrary();
	}

	$('#rescan-btn').on('click', function() {
		if (isRunning || isResetting) {
			return;
		}
		scanLibrary();
	});

	$('#start-optimize-btn').on('click', function() {
		if (isScanning || isResetting || $(this).prop('disabled')) {
			return;
		}
		isRunning = true;
		$(this).hide();
		$('#stop-optimize-btn').show();
		runBatch();
	});

	$('#stop-optimize-btn').on('click', function() {
		isRunning = false;
		$(this).hide();
		$('#start-optimize-btn').show().text(onewebp_vars.text_paused);
	});

	/**
	 * Run a batch conversion via AJAX.
	 *
	 * @return {void}
	 */
	function runBatch() {
		if (!isRunning) {
			return;
		}

		$.post(onewebp_vars.ajaxurl, {
			action: 'onewebp_run_batch',
			nonce: onewebp_vars.nonce
		}, function(res) {
			if (res && res.success) {
				fetchStats();
				if (res.data.done) {
					isRunning = false;
					$('#start-optimize-btn').show().text(onewebp_vars.text_completed);
					$('#stop-optimize-btn').hide();
				} else {
					setTimeout(runBatch, 400);
				}
			} else {
				isRunning = false;
				$('#start-optimize-btn').show().text(onewebp_vars.text_start);
				$('#stop-optimize-btn').hide();
			}
		}).fail(function() {
			isRunning = false;
			$('#start-optimize-btn').show().text(onewebp_vars.text_start);
			$('#stop-optimize-btn').hide();
		});
	}

	$('#reset-data-btn').on('click', function(e) {
		e.preventDefault();

		if (!confirm('WARNING: This will delete all OneWebP data and WebP files (.jo.webp and .lqip.webp). Original images are never touched. Cannot be undone!')) {
			return;
		}

		isResetting = true;
		$('#start-optimize-btn').prop('disabled', true).css('opacity', '0.6');
		$('#rescan-btn').prop('disabled', true);
		$('#reset-data-btn').prop('disabled', true);

		$('#reset-progress-container').show();
		$('#reset-progress-fill').css('width', '0%');
		$('#reset-progress-text').text('0%');
		$('#reset-status-text').text('Initializing...');

		runReset(0, '');
	});

	/**
	 * Run the reset process step by step.
	 *
	 * @param {number} offset Current offset.
	 * @param {string} phase  Current phase.
	 * @return {void}
	 */
	function runReset(offset, phase) {
		if (!isResetting) {
			return;
		}

		$.post(onewebp_vars.ajaxurl, {
			action: 'onewebp_reset_data',
			nonce: onewebp_vars.nonce,
			offset: offset,
			phase: phase || ''
		}, function(res) {
			if (res && res.success) {
				var data = res.data;
				$('#reset-progress-fill').css('width', data.progress + '%');
				$('#reset-progress-text').text(data.progress + '%');
				$('#reset-status-text').text(data.message);

				if (data.done) {
					isResetting = false;
					$('#reset-progress-fill').css('width', '100%');
					$('#reset-progress-text').text('100%');
					$('#reset-status-text').text(data.message || 'Reset complete!');
					$('#reset-data-btn').prop('disabled', false);
					$('#start-optimize-btn').prop('disabled', false).css('opacity', '1').text(onewebp_vars.text_start);
					$('#rescan-btn').prop('disabled', false);

					updateDashboard({
						total: 0,
						converted: 0,
						pending: 0,
						failed: 0,
						native: 0,
						saved_bytes: 0,
						progress: 0
					});

					setTimeout(function() {
						$('#reset-progress-container').slideUp();
					}, 4000);
				} else {
					setTimeout(function() {
						runReset(data.next_offset || 0, data.next_phase || '');
					}, 300);
				}
			} else {
				isResetting = false;
				$('#reset-data-btn').prop('disabled', false);
				$('#start-optimize-btn').prop('disabled', false).css('opacity', '1');
				$('#rescan-btn').prop('disabled', false);
			}
		}).fail(function() {
			isResetting = false;
			$('#reset-data-btn').prop('disabled', false);
			$('#start-optimize-btn').prop('disabled', false).css('opacity', '1');
			$('#rescan-btn').prop('disabled', false);
		});
	}

	$(document).on('click', '.onewebp-copy-url', function(e) {
		e.preventDefault();
		var $btn = $(this);
		var url = $btn.data('url');

		if (navigator.clipboard) {
			navigator.clipboard.writeText(url).then(function() {
				var originalText = $btn.text();
				$btn.text('Copied!');
				setTimeout(function() {
					$btn.text(originalText);
				}, 1500);
			});
		} else {
			var input = document.createElement('input');
			input.value = url;
			document.body.appendChild(input);
			input.select();
			document.execCommand('copy');
			document.body.removeChild(input);
			var originalText = $btn.text();
			$btn.text('Copied!');
			setTimeout(function() {
				$btn.text(originalText);
			}, 1500);
		}
	});
});