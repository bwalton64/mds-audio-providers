(function ($) {
	$(document).ready(function () {
		const $urlInput = $('#mds_bandcamp_url');
		const $albumIdInput = $('#mds_bandcamp_album_id');
		const $verifyBtn = $('#mds_bandcamp_verify_btn');
		const $statusDiv = $('#mds_bandcamp_status');

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
				url: mdsBandcampMetabox.ajaxUrl,
				type: 'POST',
				data: {
					action: 'mds_bandcamp_verify_url',
					nonce: mdsBandcampMetabox.nonce,
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
				},
				error: function () {
					showStatus('error', 'Network error. Please try again.');
					$albumIdInput.val('');
				},
				complete: function () {
					$verifyBtn.prop('disabled', false).text('Verify');
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

			// Auto-hide success messages after 5 seconds
			if ('success' === type) {
				setTimeout(function () {
					$statusDiv.slideUp();
				}, 5000);
			}
		}
	});
})(jQuery);
