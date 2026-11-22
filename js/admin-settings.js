(function ($) {
	'use strict';

	if (typeof mdsBcSettings === 'undefined') {
		return;
	}

	var i18n = mdsBcSettings.i18n || {};

	function setStatus(el, type, message) {
		if (!el) {
			return;
		}
		el.textContent = message;
		el.classList.remove('is-success', 'is-error');
		if (type === 'success') {
			el.classList.add('is-success');
		} else if (type === 'error') {
			el.classList.add('is-error');
		}
		if (el._mdsBcTimer) {
			window.clearTimeout(el._mdsBcTimer);
		}
		if (type === 'success') {
			el._mdsBcTimer = window.setTimeout(function () {
				el.textContent = '';
				el.classList.remove('is-success', 'is-error');
			}, 4000);
		}
	}

	function postRest(url, body) {
		return window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': mdsBcSettings.nonce
			},
			body: body ? JSON.stringify(body) : '{}'
		}).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (data) {
				if (!response.ok || (data && data.success === false)) {
					var errMsg = (data && data.message) ? data.message : ('HTTP ' + response.status);
					throw new Error(errMsg);
				}
				return data;
			});
		});
	}

	function renderCacheTable(tableBody, totalSizeEl, transients, totalSize) {
		if (tableBody) {
			tableBody.innerHTML = '';
			if (!Array.isArray(transients) || transients.length === 0) {
				var emptyRow = document.createElement('tr');
				var emptyCell = document.createElement('td');
				emptyCell.colSpan = 5;
				emptyCell.textContent = i18n.noTransients || 'No Transients';
				emptyRow.appendChild(emptyCell);
				tableBody.appendChild(emptyRow);
			} else {
				transients.forEach(function (item) {
					var row = document.createElement('tr');
					['size_human', 'expires', 'key', 'endpoint', 'id'].forEach(function (field) {
						var td = document.createElement('td');
						td.textContent = item && typeof item[field] !== 'undefined' && item[field] !== null ? String(item[field]) : '';
						row.appendChild(td);
					});
					tableBody.appendChild(row);
				});
			}
		}

		if (totalSizeEl) {
			totalSizeEl.textContent = totalSize || '0 B';
		}
	}

	function isCachePaneVisible(tableBody) {
		if (!tableBody) {
			return false;
		}
		var pane = tableBody.closest('.mds-tab-pane');
		if (!pane) {
			return true;
		}
		return pane.classList.contains('active');
	}

	document.addEventListener('DOMContentLoaded', function () {
		var settingsForm = document.getElementById('mds-bc-settings-form');
		var saveBtn = document.getElementById('mds-bc-save-settings-btn');
		var saveStatus = document.getElementById('mds-bc-save-status');
		var transientInput = document.getElementById('mds-bc-transient-length');

		if (settingsForm && saveBtn && transientInput) {
			settingsForm.addEventListener('submit', function (event) {
				event.preventDefault();
				var value = parseInt(transientInput.value, 10);
				if (isNaN(value) || value < 1) {
					value = 1;
				} else if (value > 1440) {
					value = 1440;
				}

				saveBtn.disabled = true;
				saveBtn.textContent = i18n.saving || 'Saving…';
				setStatus(saveStatus, '', '');

				postRest(mdsBcSettings.settingsUrl, { transient: value })
					.then(function (data) {
						if (data && data.settings && typeof data.settings.transient !== 'undefined') {
							transientInput.value = String(data.settings.transient);
						}
						setStatus(saveStatus, 'success', (data && data.message) || i18n.saved || 'Settings saved.');
					})
					.catch(function (err) {
						setStatus(saveStatus, 'error', err.message || i18n.saveError || 'Failed to save settings.');
					})
					.finally(function () {
						saveBtn.disabled = false;
						saveBtn.textContent = i18n.saveSettings || 'Save Settings';
					});
			});
		}

		var clearForm = document.getElementById('mds-bc-clear-cache-form');
		var clearBtn = document.getElementById('mds-bc-clear-cache-btn');
		var cacheStatus = document.getElementById('mds-bc-cache-status');
		var tableBody = document.getElementById('mds-bc-cache-table-body');
		var totalSizeEl = document.getElementById('mds-bc-cache-total-size');

		if (clearForm && clearBtn) {
			clearForm.addEventListener('submit', function (event) {
				event.preventDefault();

				clearBtn.disabled = true;
				clearBtn.textContent = i18n.clearing || 'Clearing…';
				setStatus(cacheStatus, '', '');

				postRest(mdsBcSettings.clearCacheUrl, {})
					.then(function (data) {
						renderCacheTable(tableBody, totalSizeEl, [], (data && data.total_size) ? data.total_size : '0 B');
						setStatus(cacheStatus, 'success', (data && data.message) || i18n.cleared || 'Cache cleared successfully!');
					})
					.catch(function (err) {
						setStatus(cacheStatus, 'error', err.message || i18n.clearError || 'Failed to clear cache.');
					})
					.finally(function () {
						clearBtn.disabled = false;
						clearBtn.textContent = i18n.clearCache || 'Clear Cache';
					});
			});
		}

		// Native WordPress Heartbeat API integration for live cache table updates.
		if ($ && tableBody) {
			$(document).on('heartbeat-send', function (event, data) {
				if (isCachePaneVisible(tableBody)) {
					data.mds_bc_refresh_cache = true;
				}
			});

			$(document).on('heartbeat-tick', function (event, data) {
				if (data && data.mds_bc_cache) {
					renderCacheTable(
						tableBody,
						totalSizeEl,
						data.mds_bc_cache.transients || [],
						data.mds_bc_cache.total_size || '0 B'
					);
				}
			});

			// Trigger an immediate heartbeat refresh when switching to the Bandcamp tab.
			$(document).on('click', '#mds-settings-tabs a[href="#tab-bandcamp"]', function () {
				if (window.wp && window.wp.heartbeat && typeof window.wp.heartbeat.connectNow === 'function') {
					window.wp.heartbeat.connectNow();
				}
			});
		}
	});
})(window.jQuery);
