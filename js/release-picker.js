(function (wp) {
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var ComboboxControl = wp.components.ComboboxControl;
	var TextControl = wp.components.TextControl;
	var Button = wp.components.Button;
	var Notice = wp.components.Notice;
	var __ = wp.i18n.__;
	var requestTimeout = 30000;
	var cacheTtl = 60000;
	var lookups = {};
	var savedValue = '__mds-bandcamp-saved';

	function fetchReleases(path) {
		var entry = lookups[path];
		if (entry && (entry.pending || Date.now() - entry.time < cacheTtl)) {
			return entry.promise;
		}
		var record = { pending: true, time: Date.now() };
		record.promise = wp.apiFetch({ path: path }).then(
			function (data) { record.pending = false; record.time = Date.now(); return data; },
			function (error) { delete lookups[path]; throw error; }
		);
		lookups[path] = record;
		return record.promise;
	}

	function useReleases(search, page, enabled) {
		var path = wp.url.addQueryArgs('/mds-bandcamp/v1/releases', { search: search, page: page });
		var state = wp.element.useState({});
		var result = state[0];
		var setResult = state[1];
		wp.element.useEffect(function () {
			if (!enabled) {
				return undefined;
			}
			var active = true;
			var timers = [];
			// Retry transient failures: editor boot can saturate PHP workers
			// with block previews, delaying this lookup past the timeout.
			function fail(error, attempt) {
				if (!active) { return; }
				if (attempt < 2) {
					timers.push(setTimeout(function () { if (active) { run(attempt + 1); } }, 2000 * (attempt + 1)));
					return;
				}
				setResult({ path: path, error: error });
			}
			function run(attempt) {
				var timedOut = false;
				var timer = setTimeout(function () { timedOut = true; fail({ code: 'timeout' }, attempt); }, requestTimeout);
				timers.push(timer);
				fetchReleases(path).then(
					function (data) { clearTimeout(timer); if (active && !timedOut) { setResult({ path: path, data: data }); } },
					function (error) { clearTimeout(timer); if (active && !timedOut) { fail(error, attempt); } }
				);
			}
			timers.push(setTimeout(function () { run(0); }, 250));
			return function () {
				active = false;
				timers.forEach(clearTimeout);
			};
		}, [path, enabled]);
		return result.path === path ? result : { loading: true };
	}

	function label(item) {
		var title = wp.htmlEntities.decodeEntities(item.title) || __('Untitled Release', 'mds-bandcamp-integration');
		var artist = wp.htmlEntities.decodeEntities(item.artist || '');
		return artist ? title + ' \u2014 ' + artist : title;
	}

	/**
	 * Searchable Release picker shared by the Bandcamp blocks.
	 *
	 * Lists published Releases with a verified Bandcamp album and copies the
	 * mapped snapshot value into the block through onSelect.
	 *
	 * @param {Object}   config            Picker configuration.
	 * @param {string}   config.value      Snapshot value currently stored by the block.
	 * @param {Function} config.onSelect   Receives the picked REST item, or null when cleared.
	 * @param {Function} config.mapValue   Maps a REST item to its snapshot value.
	 * @param {string}   config.label      Control label.
	 * @param {string}   [config.help]     Control help text.
	 * @param {string}   [config.debugLabel] Label for the WP_DEBUG read-only snapshot field.
	 * @param {boolean}  [config.enabled]  Fetch only while true (pass isSelected).
	 * @return {Object} React element rendering the picker controls.
	 */
	function canUsePreloadedToolkit() {
		return Boolean(
			window.mdsRmToolkit
			&& typeof window.mdsRmToolkit.getReleases === 'function'
			&& window.mdsRmCatalog
			&& Array.isArray(window.mdsRmCatalog.items)
			&& !window.mdsRmCatalog.truncated
		);
	}

	window.mdsBandcampUseReleasePicker = function (config) {
		var enabled = config.enabled !== false;
		var searchState = wp.element.useState('');
		var search = searchState[0];
		var setSearch = searchState[1];
		var pageState = wp.element.useState(1);
		var page = pageState[0];
		var setPage = pageState[1];
		var usePreloaded = canUsePreloadedToolkit();
		var restResults = useReleases(search, page, enabled && !usePreloaded);
		var results = usePreloaded
			? {
				loading: false,
				data: window.mdsRmToolkit.getReleases({
					search: search,
					page: page,
					filter: function (item) { return Number(item.album_id) > 0; }
				})
			}
			: restResults;
		var items = (results.data && results.data.items) || [];
		var pages = (results.data && results.data.pages) || 0;
		var mapValue = config.mapValue;
		var value = config.value ? String(config.value) : '';
		var selected = null;
		var options = items.map(function (item) {
			if (value && String(mapValue(item) || '') === value) { selected = item; }
			return { value: String(item.id), label: label(item) };
		});
		if (value && !selected) {
			options.unshift({ value: savedValue, label: __('Saved value:', 'mds-bandcamp-integration') + ' ' + value });
		}
		return el(Fragment, null,
			el(ComboboxControl, {
				label: config.label,
				help: config.help,
				value: selected ? String(selected.id) : (value ? savedValue : null),
				options: options,
				isLoading: !!results.loading,
				onFilterValueChange: function (next) {
					setSearch(String(next || '').trim().slice(0, 160));
					setPage(1);
				},
				onChange: function (next) {
					if (!next) {
						config.onSelect(null);
						return;
					}
					if (next === savedValue) {
						return; // Re-selecting the saved placeholder keeps the stored value.
					}
					var picked = null;
					items.forEach(function (item) {
						if (String(item.id) === next) { picked = item; }
					});
					if (picked) {
						config.onSelect(picked);
					}
				}
			}),
			window.mdsBandcampDebug && config.debugLabel ? el(TextControl, {
				label: config.debugLabel,
				help: __('Shown because WP_DEBUG is enabled. The block renders from this stored value.', 'mds-bandcamp-integration'),
				value: value,
				readOnly: true,
				onChange: function () {}
			}) : null,
			page > 1 && el(Button, { variant: 'tertiary', onClick: function () { setPage(page - 1); } }, __('Previous results', 'mds-bandcamp-integration')),
			pages > page && el(Button, { variant: 'tertiary', onClick: function () { setPage(page + 1); } }, __('Next results', 'mds-bandcamp-integration')),
			results.error ? el(Notice, { status: 'warning', isDismissible: false }, __('Release search failed. Change the search to try again.', 'mds-bandcamp-integration')) : null
		);
	};

	function canUsePreloadedArtistToolkit() {
		return Boolean(
			window.mdsRmToolkit
			&& typeof window.mdsRmToolkit.getArtists === 'function'
			&& window.mdsRmCatalog
			&& Array.isArray(window.mdsRmCatalog.artists)
			&& !window.mdsRmCatalog.artistsTruncated
		);
	}

	/**
	 * Searchable Artist picker for the Bandcamp Discography block.
	 *
	 * Lists published Artists that have a configured Bandcamp Artist URL.
	 * Leaving the selection blank uses the current context (Artist or Release).
	 *
	 * @param {Object}   config            Picker configuration.
	 * @param {number}   [config.artistId] Selected Artist post ID (0 for current context).
	 * @param {string}   [config.value]    Stored Bandcamp Artist URL or subdomain.
	 * @param {Function} config.onSelect   Receives the picked Artist item, or null when cleared.
	 * @param {string}   [config.label]    Control label.
	 * @param {string}   [config.help]     Control help text.
	 * @param {string}   [config.debugLabel] Label for the WP_DEBUG read-only snapshot field.
	 * @return {Object} React element rendering the Artist picker controls.
	 */
	window.mdsBandcampUseArtistPicker = function (config) {
		var searchState = wp.element.useState('');
		var search = searchState[0];
		var setSearch = searchState[1];
		var pageState = wp.element.useState(1);
		var page = pageState[0];
		var setPage = pageState[1];
		var usePreloaded = canUsePreloadedArtistToolkit();
		var results = usePreloaded
			? window.mdsRmToolkit.getArtists({
				search: search,
				page: page,
				filter: function (artist) { return Boolean(artist.subdomain || artist.artist_url); }
			})
			: { items: [], pages: 0 };
		var items = results.items || [];
		var pages = results.pages || 0;
		var artistId = Number(config.artistId) || 0;
		var value = config.value ? String(config.value).trim() : '';
		var selected = null;
		var allArtists = usePreloaded && typeof window.mdsRmToolkit.getArtistCatalog === 'function'
			? window.mdsRmToolkit.getArtistCatalog()
			: items;

		allArtists.forEach(function (artist) {
			if (artistId && Number(artist.id) === artistId) {
				selected = artist;
			} else if (!selected && value && (artist.artist_url === value || artist.subdomain === value)) {
				selected = artist;
			}
		});

		var options = items.map(function (artist) {
			return {
				value: String(artist.id),
				label: wp.htmlEntities.decodeEntities(artist.title) || __('Untitled Artist', 'mds-bandcamp-integration')
			};
		});
		if (selected && !options.some(function (opt) { return opt.value === String(selected.id); })) {
			options.unshift({
				value: String(selected.id),
				label: wp.htmlEntities.decodeEntities(selected.title) || __('Untitled Artist', 'mds-bandcamp-integration')
			});
		} else if (!selected && value) {
			options.unshift({
				value: savedValue,
				label: __('Saved value:', 'mds-bandcamp-integration') + ' ' + value
			});
		}

		var hasSelection = Boolean(artistId || value);
		var debugValue = (selected && (selected.artist_url || selected.subdomain)) || value;

		return el(Fragment, null,
			el(ComboboxControl, {
				label: config.label || __('Artist', 'mds-bandcamp-integration'),
				help: config.help || __('Search published artists with a configured Bandcamp Artist URL. Leave blank to use the current context.', 'mds-bandcamp-integration'),
				value: selected ? String(selected.id) : (value ? savedValue : null),
				options: options,
				isLoading: false,
				onFilterValueChange: function (next) {
					setSearch(String(next || '').trim().slice(0, 160));
					setPage(1);
				},
				onChange: function (next) {
					if (!next) {
						config.onSelect(null);
						return;
					}
					if (next === savedValue) {
						return;
					}
					var picked = null;
					allArtists.forEach(function (artist) {
						if (String(artist.id) === next) { picked = artist; }
					});
					if (picked) {
						config.onSelect(picked);
					}
				}
			}),
			hasSelection ? el(Button, {
				variant: 'secondary',
				onClick: function () { config.onSelect(null); }
			}, __('Use current context', 'mds-bandcamp-integration')) : null,
			window.mdsBandcampDebug && config.debugLabel ? el(TextControl, {
				label: config.debugLabel,
				help: __('Shown because WP_DEBUG is enabled. The block resolves the artist discography from this value.', 'mds-bandcamp-integration'),
				value: debugValue,
				readOnly: true,
				onChange: function () {}
			}) : null,
			page > 1 && el(Button, { variant: 'tertiary', onClick: function () { setPage(page - 1); } }, __('Previous results', 'mds-bandcamp-integration')),
			pages > page && el(Button, { variant: 'tertiary', onClick: function () { setPage(page + 1); } }, __('Next results', 'mds-bandcamp-integration'))
		);
	};
})(window.wp);
