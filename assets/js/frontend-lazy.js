/**
 * OneWebP - Smart Lazy Load with Progressive Loading.
 *
 * @package OneWebP
 */

(function() {
	'use strict';

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initLazyLoad);
	} else {
		initLazyLoad();
	}

	/**
	 * Initialize lazy loading.
	 *
	 * @return {void}
	 */
	function initLazyLoad() {
		var MAX_CONCURRENT = 3;
		var loadingCount = 0;
		var queue = [];
		var observer = null;

		/**
		 * Process the next items in the queue.
		 *
		 * @return {void}
		 */
		function processQueue() {
			if (loadingCount >= MAX_CONCURRENT || queue.length === 0) {
				return;
			}
			loadingCount++;
			var img = queue.shift();
			loadImage(img, true);
		}

		/**
		 * Load an image.
		 *
		 * @param {HTMLElement} img        Image element.
		 * @param {boolean}     isCounted  Whether the concurrency counter was incremented.
		 * @return {void}
		 */
		function loadImage(img, isCounted) {
			if (!img) {
				if (isCounted) {
					loadingCount--;
				}
				processQueue();
				return;
			}

			var realSrc = img.getAttribute('data-src');
			var picture = img.closest('picture');
			var isProgressive = img.getAttribute('data-progressive') === 'true';

			if (picture) {
				var webpSource = picture.querySelector('source[type="image/webp"][data-srcset]');
				if (webpSource) {
					webpSource.srcset = webpSource.getAttribute('data-srcset');
					webpSource.removeAttribute('data-srcset');
				}
			}

			var dataSrcset = img.getAttribute('data-srcset');
			if (dataSrcset) {
				img.srcset = dataSrcset;
				img.removeAttribute('data-srcset');
			}

			if (realSrc) {
				var imgElement = img;

				if (isProgressive) {
					imgElement.style.filter = 'blur(4px)';
					imgElement.style.transition = 'filter 0.3s ease-out, opacity 0.2s ease-out';
					imgElement.style.willChange = 'filter, opacity';
					imgElement.style.opacity = '1';
					imgElement.style.backfaceVisibility = 'hidden';
				}

				imgElement.src = realSrc;
				imgElement.removeAttribute('data-src');
				imgElement.removeAttribute('data-progressive');

				imgElement.onload = function() {
					if (isProgressive) {
						imgElement.style.filter = 'blur(0px)';
					}
					imgElement.style.opacity = '1';
					imgElement.classList.remove('onewebp-lazy-img');

					setTimeout(function() {
						imgElement.style.willChange = 'auto';
					}, 350);

					imgElement.onload = null;
					imgElement.onerror = null;
					if (isCounted) {
						loadingCount--;
					}
					processQueue();
				};

				imgElement.onerror = function() {
					if (isProgressive) {
						imgElement.style.filter = 'blur(0px)';
					}
					imgElement.style.opacity = '1';
					imgElement.classList.remove('onewebp-lazy-img');
					imgElement.style.willChange = 'auto';
					imgElement.onload = null;
					imgElement.onerror = null;
					if (isCounted) {
						loadingCount--;
					}
					processQueue();
				};

				setTimeout(function() {
					if (isProgressive && imgElement.style.filter === 'blur(4px)') {
						imgElement.style.filter = 'blur(0px)';
						imgElement.style.willChange = 'auto';
					}
				}, 2000);
			} else {
				if (isCounted) {
					loadingCount--;
				}
				processQueue();
			}
		}

		// Progressive images above the fold load immediately.
		var immediateImages = document.querySelectorAll('.onewebp-lazy-img[data-progressive="true"]');
		immediateImages.forEach(function(img) {
			loadImage(img, false);
			img.classList.remove('onewebp-lazy-img');
		});

		// Other images use lazy loading.
		var lazyImages = document.querySelectorAll('.onewebp-lazy-img:not([data-progressive="true"])');
		if ('IntersectionObserver' in window) {
			observer = new IntersectionObserver(function(entries) {
				entries.forEach(function(entry) {
					if (entry.isIntersecting) {
						queue.push(entry.target);
						observer.unobserve(entry.target);
						processQueue();
					}
				});
			}, {
				rootMargin: '100px',
				threshold: 0.01
			});

			lazyImages.forEach(function(img) {
				observer.observe(img);
			});
		} else {
			lazyImages.forEach(function(img) {
				loadImage(img, false);
			});
		}
	}
})();