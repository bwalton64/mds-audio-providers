(function ($) {
	$(document).ready(function () {
		const config = typeof mdsBcMetabox !== 'undefined' ? mdsBcMetabox : {};
		const messages = config.messages || {};

		const $urlInput = $('#mds_bc_url');
		const $albumIdInput = $('#mds_bc_album_id');
		const $verifyBtn = $('#mds_bc_verify_btn');
		const $statusDiv = $('#mds_bc_status');
		const $useBandcampBtn = $('#mds_use_bandcamp_url_btn');

		function speak(text, priority) {
			if (window.wp && window.wp.a11y && window.wp.a11y.speak) {
				window.wp.a11y.speak(text, priority || 'polite');
			}
		}

		function getVerifiedBandcampUrl() {
			if (!$urlInput.length || !$albumIdInput.length) {
				return '';
			}
			const url = String($urlInput.val() || '').trim();
			const albumId = parseInt(String($albumIdInput.val() || '').trim(), 10);
			return url && albumId > 0 ? url : '';
		}

		function syncBuyNowButtonState() {
			if (!$useBandcampBtn.length) {
				return;
			}
			$useBandcampBtn.prop('disabled', !getVerifiedBandcampUrl());
		}

		function reindexBuyNowPrimary() {
			const rows = document.querySelectorAll('#mds_buy_now_links_wrapper .mds-buy-now-row');
			let hasChecked = false;
			rows.forEach(function (row, index) {
				const radio = row.querySelector('input[name="mds_buy_now_primary"]');
				if (radio) {
					radio.value = String(index);
					if (radio.checked) {
						hasChecked = true;
					}
				}
			});
			if (!hasChecked && rows.length) {
				const firstRadio = rows[0].querySelector('input[name="mds_buy_now_primary"]');
				if (firstRadio) {
					firstRadio.checked = true;
				}
			}
		}

		function fillBuyNowRow(row, storeName, url) {
			const formatSelect = row.querySelector('select[name="mds_buy_now_format[]"]');
			const storeInput = row.querySelector('input[name="mds_buy_now_store[]"]');
			const urlInput = row.querySelector('input[name="mds_buy_now_url[]"]');

			if (formatSelect) {
				let digitalOption = formatSelect.querySelector('option[value="digital-download"]');
				if (!digitalOption) {
					digitalOption = document.createElement('option');
					digitalOption.value = 'digital-download';
					digitalOption.textContent = messages.digitalDownloadLabel || 'Digital Download';
					formatSelect.appendChild(digitalOption);
				}
				formatSelect.value = 'digital-download';
			}
			if (storeInput) {
				storeInput.value = storeName;
			}
			if (urlInput) {
				urlInput.value = url;
			}
		}

		if ($useBandcampBtn.length) {
			syncBuyNowButtonState();

			$urlInput.on('input change', function () {
				syncBuyNowButtonState();
			});

			$useBandcampBtn.on('click', function () {
				const url = getVerifiedBandcampUrl();
				if (!url) {
					return;
				}

				const storeName = messages.storeName || 'Bandcamp';

				// Prefer the MDS Release Manager React Buy Now bridge when available.
				const addEvent = new CustomEvent('mds-rm:add-buy-now-link', {
					cancelable: true,
					detail: {
						format: 'digital-download',
						store: storeName,
						url: url,
						addedMessage: messages.buyNowAdded || 'Added Bandcamp URL to Buy Now formats.',
						existsMessage: messages.buyNowExists || 'This Bandcamp URL is already listed in Buy Now.',
					},
				});
				window.dispatchEvent(addEvent);
				if (addEvent.defaultPrevented) {
					return;
				}

				const repeater = document.getElementById('mds_buy_now_links_wrapper');
				const template = document.getElementById('mds_buy_now_row_template');
				if (!repeater || !template) {
					return;
				}

				const existingUrls = new Set(
					Array.from(repeater.querySelectorAll('input[name="mds_buy_now_url[]"]'))
						.map(function (input) {
							return input.value.trim();
						})
						.filter(Boolean)
				);

				if (existingUrls.has(url)) {
					speak(
						messages.buyNowExists || 'This Bandcamp URL is already listed in Buy Now.',
						'polite'
					);
					return;
				}
				const emptyRow = Array.from(repeater.querySelectorAll('.mds-buy-now-row')).find(
					function (row) {
						const u = row.querySelector('input[name="mds_buy_now_url[]"]');
						const f = row.querySelector('select[name="mds_buy_now_format[]"]');
						return u && '' === u.value.trim() && (!f || '' === f.value);
					}
				);

				if (emptyRow) {
					fillBuyNowRow(emptyRow, storeName, url);
				} else {
					const index = repeater.querySelectorAll('.mds-buy-now-row').length;
					const tempContainer = document.createElement('div');
					tempContainer.innerHTML = template.innerHTML.replace(/__INDEX__/g, String(index));
					const newRow = tempContainer.firstElementChild;
					if (newRow) {
						fillBuyNowRow(newRow, storeName, url);
						repeater.appendChild(newRow);
					}
				}

				reindexBuyNowPrimary();
				speak(
					messages.buyNowAdded || 'Added Bandcamp URL to Buy Now formats.',
					'polite'
				);
			});
		}

		$verifyBtn.on('click', function () {
			const url = $urlInput.val().trim();

			if (!url) {
				showStatus('error', 'Please enter a Bandcamp URL.');
				return;
			}

			verifyUrl(url);
		});

		function verifyUrl(url) {
			$verifyBtn.prop('disabled', true).text('Verifying...');
			$statusDiv.hide();

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				data: {
					action: 'mds_bc_verify_url',
					nonce: config.nonce,
					url: url,
				},
				success: function (response) {
					if (response.success) {
						$albumIdInput.val(response.data.album_id);
						showStatus('success', 'Album ID found: ' + response.data.album_id);
					} else {
						showStatus('error', response.data.message || 'Failed to verify URL.');
						$albumIdInput.val('');
					}
					syncBuyNowButtonState();
				},
				error: function () {
					showStatus('error', 'Network error. Please try again.');
					$albumIdInput.val('');
					syncBuyNowButtonState();
				},
				complete: function () {
					$verifyBtn.prop('disabled', false).text('Verify URL');
				},
			});
		}

		function showStatus(type, message) {
			$statusDiv.removeClass('updated error notice-success notice-error');

			if ('success' === type) {
				$statusDiv.addClass('updated notice-success');
			} else {
				$statusDiv.addClass('error notice-error');
			}

			$statusDiv
				.html('<p><strong>' + (type === 'success' ? '✓ ' : '✗ ') + message + '</strong></p>')
				.slideDown();

			// Auto-hide success messages after 5 seconds.
			if ('success' === type) {
				setTimeout(function () {
					$statusDiv.slideUp();
				}, 5000);
			}
		}
	});
})(jQuery);
