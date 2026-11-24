(function (wp, $) {
	'use strict';

	if (!wp || !wp.element || !wp.components || typeof window.mdsBcSettings === 'undefined') {
		return;
	}

	const { createElement: el, Component, Fragment, useState, useEffect, useCallback, useMemo } = wp.element;
	const {
		Button,
		Card,
		CardHeader,
		CardBody,
		ColorPalette,
		ColorPicker,
		Dropdown,
		PanelBody,
		RangeControl,
		SelectControl,
		SnackbarList,
		TabPanel,
		TextControl,
		ToggleControl,
		BoxControl,
		BorderControl,
		__experimentalVStack: VStack,
		__experimentalHStack: HStack,
		__experimentalUnitControl: ExperimentalUnitControl,
		__experimentalToggleGroupControl: ToggleGroupControl,
		__experimentalToggleGroupControlOption: ToggleGroupControlOption,
	} = wp.components;

	const UnitControl = wp.components.UnitControl || ExperimentalUnitControl;
	const { __ } = wp.i18n || { __: (s) => s };
	const speak = wp.a11y && typeof wp.a11y.speak === 'function' ? wp.a11y.speak : function () {};

	const config = window.mdsBcSettings;
	const DEFAULT_COLORS = [
		{ name: __('Charcoal', 'mds-bandcamp-integration'), color: '#171717' },
		{ name: __('Black', 'mds-bandcamp-integration'), color: '#000000' },
		{ name: __('White', 'mds-bandcamp-integration'), color: '#ffffff' },
		{ name: __('Bandcamp Teal', 'mds-bandcamp-integration'), color: '#1da0c3' },
		{ name: __('Slate', 'mds-bandcamp-integration'), color: '#334155' },
		{ name: __('Warm Gray', 'mds-bandcamp-integration'), color: '#f3f4f6' },
	];

	function Stack({ direction, spacing, alignment, justify, children }) {
		if (direction === 'row' && HStack) {
			return el(HStack, { spacing: spacing || 3, alignment: alignment || 'center', justify: justify || 'flex-start', wrap: true }, children);
		}
		if (direction !== 'row' && VStack) {
			return el(VStack, { spacing: spacing || 4 }, children);
		}
		return el('div', { style: { display: 'flex', flexDirection: direction === 'row' ? 'row' : 'column', gap: '12px', flexWrap: 'wrap', alignItems: direction === 'row' ? 'center' : 'stretch' } }, children);
	}

	function contrastColor(hex) {
		const clean = String(hex || '').replace('#', '');
		if (clean.length !== 6) {
			return '#ffffff';
		}
		const r = parseInt(clean.slice(0, 2), 16);
		const g = parseInt(clean.slice(2, 4), 16);
		const b = parseInt(clean.slice(4, 6), 16);
		return ((r * 299) + (g * 587) + (b * 114)) / 1000 >= 128 ? '#000000' : '#ffffff';
	}

	function ColorField({ label, help, value, onChange }) {
		const current = value || '';
		if (!Dropdown) {
			return el(
				'div',
				{ className: 'mds-bc-admin-app__color-field' },
				el('span', { className: 'mds-bc-admin-app__color-label' }, label),
				el(ColorPalette, {
					colors: DEFAULT_COLORS,
					value: current,
					onChange: (next) => onChange(next || ''),
					clearable: false,
				}),
				help && el('p', { className: 'components-base-control__help', style: { margin: 0 } }, help)
			);
		}
		return el(
			'div',
			{ className: 'mds-bc-admin-app__color-field' },
			el('span', { className: 'mds-bc-admin-app__color-label' }, label),
			el(Dropdown, {
				popoverProps: { placement: 'bottom-start' },
				renderToggle: ({ isOpen, onToggle }) =>
					el(
						Button,
						{
							variant: 'secondary',
							className: 'mds-bc-admin-app__color-trigger',
							onClick: onToggle,
							'aria-expanded': isOpen,
							'aria-label': label + (current ? ': ' + current : ''),
						},
						el('span', {
							className: 'mds-bc-admin-app__color-swatch',
							'aria-hidden': 'true',
							style: { backgroundColor: current || 'transparent' },
						}),
						el('span', null, current || __('Choose color', 'mds-bandcamp-integration'))
					),
				renderContent: () =>
					el(
						'div',
						{ className: 'mds-bc-admin-app__color-popover' },
						ColorPicker
							? el(
								Stack,
								{ direction: 'column', spacing: 3 },
								el(ColorPicker, {
									color: current || '#000000',
									enableAlpha: false,
									onChange: (next) => onChange(next || ''),
								}),
								el(ColorPalette, {
									colors: DEFAULT_COLORS,
									value: current,
									disableCustomColors: true,
									clearable: false,
									onChange: (next) => onChange(next || ''),
								})
							)
							: el(ColorPalette, {
								colors: DEFAULT_COLORS,
								value: current,
								clearable: false,
								onChange: (next) => onChange(next || ''),
							})
					),
			}),
			help && el('p', { className: 'components-base-control__help', style: { margin: 0 } }, help)
		);
	}

	function postRest(url, body) {
		return window.fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify(body || {}),
		}).then((response) =>
			response.json().catch(() => ({})).then((data) => {
				if (!response.ok || (data && data.success === false)) {
					throw new Error((data && data.message) || ('HTTP ' + response.status));
				}
				return data;
			})
		);
	}

	function LivePreview({ activeBlock, defaults }) {
		const album = defaults['bandcamp-album'] || {};
		const disco = defaults['bandcamp-discography'] || {};
		const mini = defaults['bandcamp-miniplayer'] || {};

		if (activeBlock === 'bandcamp-album') {
			const btnText = contrastColor(album.buttonColor || '#000000');
			return el(
				'section',
				{
					className: 'bandcamp-album',
					style: {
						backgroundColor: album.backgroundColor || '#171717',
						color: album.textColor || '#ffffff',
						borderRadius: '4px',
						overflow: 'hidden',
					},
				},
				el(
					'div',
					{ className: 'bandcamp-album-header-wrap', style: { gridTemplateColumns: album.display_album_art ? '120px 1fr' : '1fr', padding: '16px', gap: '16px' } },
					album.display_album_art && el(
						'div',
						{
							style: {
								width: '120px',
								height: '120px',
								background: 'linear-gradient(135deg, #1da0c3, #0f172a)',
								display: 'flex',
								alignItems: 'center',
								justifyContent: 'center',
								color: '#fff',
								fontSize: '36px',
								borderRadius: '2px',
							},
						},
						el('i', { className: 'fab fa-bandcamp', 'aria-hidden': 'true' })
					),
					el(
						'div',
						{ className: 'bandcamp-album-titles' },
						el(
							'div',
							null,
							album.display_album_title && el('h3', { style: { color: album.textColor || '#ffffff' } }, el('i', null, __('Midnight Echoes', 'mds-bandcamp-integration'))),
							album.display_album_artist && el('h4', { style: { color: album.textColor || '#ffffff' } }, __('Sample Artist', 'mds-bandcamp-integration')),
							album.display_album_link && el(
								'span',
								{
									className: 'album-link',
									style: {
										backgroundColor: album.buttonColor || '#000000',
										color: btnText,
										display: 'inline-flex',
										alignItems: 'center',
										gap: '6px',
										padding: '5px 8px',
										borderRadius: '2px',
										marginTop: '8px',
										fontSize: '12px',
									},
								},
								__('Listen on Bandcamp', 'mds-bandcamp-integration'),
								el('i', { className: 'fab fa-bandcamp', 'aria-hidden': 'true', style: { color: btnText } })
							)
						)
					)
				),
				album.display_audio && album.embedType !== 'bandcamp' && el(
					'div',
					{ className: 'bandcamp-album-audio', style: { padding: '0 16px 12px' } },
					el('span', { style: { color: album.playerControlsColor || '#ffffff', fontWeight: 700 } }, '▶'),
					el(
						'div',
						{ className: 'bandcamp-player-progress-wrap', style: { flex: 1 } },
						el('div', { className: 'bandcamp-player-progress', style: { width: '48%', background: album.progressBarColor || '#ffffff' } })
					),
					el('div', { className: 'bandcamp-player-time', style: { color: album.textColor || '#ffffff' } }, '01:42 / 03:35')
				),
				album.display_audio && album.display_playlist && album.embedType !== 'bandcamp' && el(
					'div',
					{ className: 'bandcamp-album-tracks', style: { padding: '0 16px 12px', color: album.trackColor || '#ffffff' } },
					el('div', { style: { fontSize: '12px', opacity: 0.9, padding: '4px 0', borderBottom: '1px solid rgba(255,255,255,0.12)' } }, '1. Sample Artist : Opening Theme (03:35)'),
					el('div', { style: { fontSize: '12px', opacity: 0.9, padding: '4px 0' } }, '2. Sample Artist : Neon Horizon (04:12)')
				)
			);
		}

		if (activeBlock === 'bandcamp-discography') {
			const border = disco.release_border || {};
			const cardStyle = {
				borderColor: border.color || '#dddddd',
				borderStyle: border.style || 'solid',
				borderWidth: border.width || '1px',
				borderRadius: border.radius || '0px',
				background: '#fff',
			};
			const cols = Math.min(3, Math.max(1, Number(disco.layout_columns) || 3));
			return el(
				'section',
				{ className: 'bandcamp-discography' },
				el(
					'div',
					{
						className: 'bandcamp-grid',
						style: {
							gridTemplateColumns: 'repeat(' + cols + ', minmax(0, 1fr))',
							gridGap: disco.layout_grid_gap || '1rem',
						},
					},
					[1, 2, 3].slice(0, cols).map((idx) =>
						el(
							'div',
							{ key: idx, className: 'bandcamp-release', style: cardStyle },
							disco.display_album_art && el(
								'div',
								{
									style: {
										aspectRatio: '1/1',
										background: idx % 2 === 0 ? '#0f172a' : '#1da0c3',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'center',
										color: '#fff',
										fontSize: '28px',
									},
								},
								el('i', { className: 'fab fa-bandcamp', 'aria-hidden': 'true' })
							),
							el(
								'div',
								{
									style: {
										padding: '10px',
										textAlign: disco.release_titles_align || 'left',
										color: disco.text_color || '#000000',
									},
								},
								disco.display_album_artist && el('h5', { style: { color: disco.text_color || '#000000' } }, __('Sample Artist', 'mds-bandcamp-integration')),
								disco.display_album_title && el('h6', { style: { color: disco.text_color || '#000000' } }, el('i', null, __('Release', 'mds-bandcamp-integration') + ' #' + idx)),
								disco.display_album_link && el(
									'span',
									{
										style: {
											backgroundColor: disco.button_color || '#000000',
											color: disco.button_text_color || '#ffffff',
											display: 'inline-flex',
											alignItems: 'center',
											gap: '5px',
											padding: '4px 8px',
											borderRadius: '2px',
											fontSize: '11px',
											fontWeight: 600,
										},
									},
									__('Listen', 'mds-bandcamp-integration'),
									el('i', { className: 'fab fa-bandcamp', 'aria-hidden': 'true', style: { color: disco.button_text_color || '#ffffff' } })
								)
							)
						)
					)
				)
			);
		}

		// Miniplayer preview.
		return el(
			'section',
			{
				className: 'bandcamp-miniplayer',
				style: {
					backgroundColor: mini.backgroundColor || '#171717',
					aspectRatio: (mini.aspectRatioWidth || '1') + ' / ' + (mini.aspectRatioHeight || '1'),
					maxWidth: '280px',
					borderRadius: '4px',
					position: 'relative',
					overflow: 'hidden',
				},
			},
			el('div', {
				style: {
					position: 'absolute',
					inset: 0,
					background: 'linear-gradient(135deg, #1da0c3, #0f172a)',
					opacity: typeof mini.artOpacity === 'number' ? mini.artOpacity : 0.5,
				},
			}),
			el(
				'div',
				{ style: { position: 'relative', zIndex: 2, fontSize: '42px', color: mini.buttonColor || '#000000' } },
				'▶'
			),
			el(
				'div',
				{ style: { position: 'absolute', left: '12px', bottom: '14px', zIndex: 3, color: mini.textColor || '#ffffff' } },
				mini.display_track_title && el('div', { style: { fontWeight: 700, fontSize: '14px' } }, __('Opening Theme', 'mds-bandcamp-integration')),
				mini.display_album_artist && el('div', { style: { fontSize: '12px', opacity: 0.9 } }, __('Sample Artist', 'mds-bandcamp-integration')),
				mini.display_album_title && el('div', { style: { fontSize: '11px', opacity: 0.8 } }, __('from "Midnight Echoes"', 'mds-bandcamp-integration'))
			),
			mini.display_album_link && el(
				'span',
				{
					style: {
						position: 'absolute',
						right: '12px',
						bottom: '14px',
						zIndex: 3,
						background: '#fff',
						color: '#000',
						padding: '3px 6px',
						borderRadius: '2px',
						fontSize: '11px',
						display: 'inline-flex',
						alignItems: 'center',
						gap: '4px',
					},
				},
				el('i', { className: 'fab fa-bandcamp', 'aria-hidden': 'true' })
			),
			mini.display_progress_bar && el('div', {
				style: {
					position: 'absolute',
					left: 0,
					bottom: 0,
					height: '5px',
					width: '55%',
					background: mini.progressBarColor || '#ffffff',
					zIndex: 4,
				},
			})
		);
	}

	function BandcampAdminApp({ isStandalone }) {
		const [activeTab, setActiveTab] = useState('admin');
		const [activeBlock, setActiveBlock] = useState('bandcamp-album');
		const [isPreviewOpen, setIsPreviewOpen] = useState(false);

		const [transientTtl, setTransientTtl] = useState(
			Number((config.settings && config.settings.transient) || 60)
		);
		const [blockDefaults, setBlockDefaults] = useState(() =>
			JSON.parse(JSON.stringify(config.blockDefaults || {}))
		);
		const [cacheRows, setCacheRows] = useState(() =>
			Array.isArray(config.cache && config.cache.transients) ? config.cache.transients : []
		);
		const [cacheTotalSize, setCacheTotalSize] = useState(
			(config.cache && config.cache.totalSize) || '0 B'
		);
		const [cacheSearch, setCacheSearch] = useState('');
		const [isSaving, setIsSaving] = useState(false);
		const [isClearing, setIsClearing] = useState(false);
		const [notices, setNotices] = useState([]);

		const addNotice = useCallback((content, status) => {
			const id = 'mds-bc-' + Date.now() + '-' + Math.random().toString(36).slice(2, 6);
			setNotices((prev) => [...prev.slice(-3), { id, content, status: status || 'info' }]);
			speak(content, status === 'error' ? 'assertive' : 'polite');
		}, []);

		const removeNotice = useCallback((id) => {
			setNotices((prev) => prev.filter((item) => item.id !== id));
		}, []);

		const togglePreview = useCallback(() => {
			setIsPreviewOpen((prev) => {
				const next = !prev;
				speak(
					next
						? __('Live preview opened.', 'mds-bandcamp-integration')
						: __('Live preview closed.', 'mds-bandcamp-integration'),
					'polite'
				);
				return next;
			});
		}, []);

		const updateBlockAttr = useCallback((slug, key, value) => {
			setBlockDefaults((prev) => ({
				...prev,
				[slug]: {
					...(prev[slug] || {}),
					[key]: value,
				},
			}));
		}, []);

		const handleResetBlock = useCallback((slug) => {
			const baseline = (config.schemaDefaults && config.schemaDefaults[slug]) || {};
			setBlockDefaults((prev) => ({
				...prev,
				[slug]: JSON.parse(JSON.stringify(baseline)),
			}));
			addNotice(__('Block defaults reset to baseline. Click Save Changes to persist.', 'mds-bandcamp-integration'), 'info');
		}, [addNotice]);

		const handleSave = useCallback(() => {
			setIsSaving(true);
			const clampedTtl = Math.max(1, Math.min(1440, parseInt(transientTtl, 10) || 60));
			postRest(config.settingsUrl, {
				transient: clampedTtl,
				block_defaults: blockDefaults,
			})
				.then((data) => {
					if (data && data.settings && typeof data.settings.transient !== 'undefined') {
						setTransientTtl(Number(data.settings.transient));
					}
					if (data && data.block_defaults) {
						setBlockDefaults(data.block_defaults);
					}
					addNotice((data && data.message) || __('Settings saved.', 'mds-bandcamp-integration'), 'success');
				})
				.catch((err) => {
					addNotice(err.message || __('Failed to save settings.', 'mds-bandcamp-integration'), 'error');
				})
				.finally(() => {
					setIsSaving(false);
				});
		}, [transientTtl, blockDefaults, addNotice]);

		const handleClearCache = useCallback(() => {
			setIsClearing(true);
			postRest(config.clearCacheUrl, {})
				.then((data) => {
					setCacheRows([]);
					setCacheTotalSize((data && data.total_size) || '0 B');
					addNotice((data && data.message) || __('Cache cleared successfully!', 'mds-bandcamp-integration'), 'success');
				})
				.catch((err) => {
					addNotice(err.message || __('Failed to clear cache.', 'mds-bandcamp-integration'), 'error');
				})
				.finally(() => {
					setIsClearing(false);
				});
		}, [addNotice]);

		// Native WordPress Heartbeat live refresh for Cached Data.
		useEffect(() => {
			if (!$) {
				return undefined;
			}
			const onSend = (event, data) => {
				const root = document.getElementById('mds-bandcamp-integration-admin-root');
				const pane = root ? root.closest('.mds-tab-pane') : null;
				if (!pane || pane.classList.contains('active')) {
					data.mds_bc_refresh_cache = true;
				}
			};
			const onTick = (event, data) => {
				if (data && data.mds_bc_cache) {
					setCacheRows(Array.isArray(data.mds_bc_cache.transients) ? data.mds_bc_cache.transients : []);
					setCacheTotalSize(data.mds_bc_cache.total_size || '0 B');
				}
			};
			$(document).on('heartbeat-send.mdsBcAdmin', onSend);
			$(document).on('heartbeat-tick.mdsBcAdmin', onTick);
			$(document).on('click.mdsBcAdmin', '#mds-settings-tabs a[href="#tab-bandcamp"]', () => {
				if (window.wp && window.wp.heartbeat && typeof window.wp.heartbeat.connectNow === 'function') {
					window.wp.heartbeat.connectNow();
				}
			});
			return () => {
				$(document).off('.mdsBcAdmin');
			};
		}, []);

		// Optional WordPress 7.x Command Palette registration inside useEffect (safe when store is absent).
		useEffect(() => {
			try {
				const dispatch = wp.data && typeof wp.data.dispatch === 'function' ? wp.data.dispatch('core/commands') : null;
				if (!dispatch || typeof dispatch.registerCommand !== 'function') {
					return undefined;
				}
				dispatch.registerCommand({
					name: 'mds-bandcamp/toggle-preview',
					label: __('Bandcamp: Toggle Block Defaults Live Preview', 'mds-bandcamp-integration'),
					callback: ({ close }) => {
						setActiveTab('designer');
						togglePreview();
						if (typeof close === 'function') {
							close();
						}
					},
				});
				dispatch.registerCommand({
					name: 'mds-bandcamp/clear-cache',
					label: __('Bandcamp: Clear Cached API Data', 'mds-bandcamp-integration'),
					callback: ({ close }) => {
						handleClearCache();
						if (typeof close === 'function') {
							close();
						}
					},
				});
				return () => {
					if (typeof dispatch.unregisterCommand === 'function') {
						dispatch.unregisterCommand('mds-bandcamp/toggle-preview');
						dispatch.unregisterCommand('mds-bandcamp/clear-cache');
					}
				};
			} catch (err) {
				return undefined;
			}
		}, [togglePreview, handleClearCache]);

		const filteredCache = useMemo(() => {
			const needle = cacheSearch.trim().toLowerCase();
			if (!needle) {
				return cacheRows;
			}
			return cacheRows.filter((row) =>
				[row.key, row.endpoint, row.id, row.expires].some((field) =>
					String(field || '').toLowerCase().includes(needle)
				)
			);
		}, [cacheRows, cacheSearch]);

		const album = blockDefaults['bandcamp-album'] || {};
		const disco = blockDefaults['bandcamp-discography'] || {};
		const mini = blockDefaults['bandcamp-miniplayer'] || {};

		const blockTabs = [
			{ value: 'bandcamp-album', label: __('Bandcamp Album', 'mds-bandcamp-integration') },
			{ value: 'bandcamp-discography', label: __('Bandcamp Discography', 'mds-bandcamp-integration') },
			{ value: 'bandcamp-miniplayer', label: __('Bandcamp Miniplayer', 'mds-bandcamp-integration') },
		];

		function renderAdminTab() {
			return el(
				Stack,
				{ direction: 'column', spacing: 4 },
				el(
					Card,
					null,
					el(
						CardHeader,
						null,
						el('strong', null, __('API Cache Policy', 'mds-bandcamp-integration'))
					),
					el(
						CardBody,
						null,
						el(RangeControl, {
							label: __('Transient Cache TTL (Minutes)', 'mds-bandcamp-integration'),
							help: __('Controls how long fetched Bandcamp album and discography payloads are cached before refreshing from Bandcamp.', 'mds-bandcamp-integration'),
							value: transientTtl,
							min: 1,
							max: 1440,
							step: 1,
							onChange: (val) => setTransientTtl(Number(val) || 60),
						})
					)
				),
				el(
					Card,
					null,
					el(
						CardHeader,
						null,
						el(
							Stack,
							{ direction: 'row', justify: 'space-between' },
							el('strong', null, __('Cached Data Diagnostics', 'mds-bandcamp-integration') + ' (' + cacheTotalSize + ')'),
							el(
								Button,
								{
									variant: 'secondary',
									isDestructive: true,
									isBusy: isClearing,
									disabled: isClearing,
									onClick: handleClearCache,
								},
								isClearing ? __('Clearing…', 'mds-bandcamp-integration') : __('Clear Cache', 'mds-bandcamp-integration')
							)
						)
					),
					el(
						CardBody,
						null,
						el(
							Stack,
							{ direction: 'column', spacing: 3 },
							el(TextControl, {
								label: __('Filter Cached Entries', 'mds-bandcamp-integration'),
								help: __('Filter active transients by cache key, endpoint, or Bandcamp ID. Updates automatically via WordPress Heartbeat.', 'mds-bandcamp-integration'),
								value: cacheSearch,
								onChange: setCacheSearch,
								placeholder: __('Search endpoint, key, or ID…', 'mds-bandcamp-integration'),
							}),
							el(
								'div',
								{ className: 'mds-bc-admin-app__cache-table-wrap' },
								el(
									'table',
									{ className: 'widefat fixed striped' },
									el(
										'thead',
										null,
										el(
											'tr',
											null,
											el('th', { style: { width: '80px' } }, __('Size', 'mds-bandcamp-integration')),
											el('th', { style: { width: '170px' } }, __('Expires', 'mds-bandcamp-integration')),
											el('th', null, __('Key', 'mds-bandcamp-integration')),
											el('th', { style: { width: '110px' } }, __('Endpoint', 'mds-bandcamp-integration')),
											el('th', { style: { width: '140px' } }, __('ID', 'mds-bandcamp-integration'))
										)
									),
									el(
										'tbody',
										null,
										filteredCache.length === 0
											? el('tr', null, el('td', { colSpan: 5 }, __('No cached transients found.', 'mds-bandcamp-integration')))
											: filteredCache.map((row, idx) =>
												el(
													'tr',
													{ key: (row.key || 'row') + '-' + idx },
													el('td', null, row.size_human || ''),
													el('td', null, row.expires || ''),
													el('td', null, row.key || ''),
													el('td', null, row.endpoint || ''),
													el('td', null, row.id || '')
												)
											)
									)
								)
							)
						)
					)
				)
			);
		}

		function renderAlbumDesigner() {
			const slug = 'bandcamp-album';
			return el(
				Fragment,
				null,
				el(
					PanelBody,
					{ title: __('Display & Player', 'mds-bandcamp-integration'), initialOpen: true },
					el(SelectControl, {
						label: __('Default Player Mode', 'mds-bandcamp-integration'),
						help: __('Choose between the custom HTML5 audio player or Bandcamp’s embedded iframe player.', 'mds-bandcamp-integration'),
						value: album.embedType || 'custom',
						options: [
							{ label: __('Custom HTML Player', 'mds-bandcamp-integration'), value: 'custom' },
							{ label: __('Bandcamp Embed', 'mds-bandcamp-integration'), value: 'bandcamp' },
						],
						onChange: (val) => updateBlockAttr(slug, 'embedType', val),
					}),
					el(ToggleControl, {
						label: __('Show Album Title', 'mds-bandcamp-integration'),
						help: __('Display the release title in the header.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_album_title),
						onChange: (val) => updateBlockAttr(slug, 'display_album_title', val),
					}),
					el(ToggleControl, {
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						help: __('Display the artist name beneath the album title.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_album_artist),
						onChange: (val) => updateBlockAttr(slug, 'display_album_artist', val),
					}),
					el(ToggleControl, {
						label: __('Show Cover Artwork', 'mds-bandcamp-integration'),
						help: __('Display the album cover art and background tint.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_album_art),
						onChange: (val) => updateBlockAttr(slug, 'display_album_art', val),
					}),
					el(ToggleControl, {
						label: __('Show Bandcamp Button', 'mds-bandcamp-integration'),
						help: __('Display the "Listen on Bandcamp" link button.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_album_link),
						onChange: (val) => updateBlockAttr(slug, 'display_album_link', val),
					}),
					el(ToggleControl, {
						label: __('Show Audio Player', 'mds-bandcamp-integration'),
						help: __('Enable inline audio playback for the featured track.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_audio),
						onChange: (val) => updateBlockAttr(slug, 'display_audio', val),
					}),
					album.embedType !== 'bandcamp' && el(ToggleControl, {
						label: __('Show Tracklist', 'mds-bandcamp-integration'),
						help: __('List playable album tracks below the player.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_playlist),
						onChange: (val) => updateBlockAttr(slug, 'display_playlist', val),
					}),
					el(ToggleControl, {
						label: __('Show Merchandise', 'mds-bandcamp-integration'),
						help: __('Display Bandcamp physical packages when available for the album.', 'mds-bandcamp-integration'),
						checked: Boolean(album.display_merch),
						onChange: (val) => updateBlockAttr(slug, 'display_merch', val),
					})
				),
				el(
					PanelBody,
					{ title: __('Colors & Controls', 'mds-bandcamp-integration'), initialOpen: false },
					el(
						'div',
						{ className: 'mds-bc-admin-app__color-grid' },
						el(ColorField, { label: __('Background Color', 'mds-bandcamp-integration'), value: album.backgroundColor, onChange: (val) => updateBlockAttr(slug, 'backgroundColor', val) }),
						el(ColorField, { label: __('Text Color', 'mds-bandcamp-integration'), value: album.textColor, onChange: (val) => updateBlockAttr(slug, 'textColor', val) }),
						el(ColorField, { label: __('Tracklist Color', 'mds-bandcamp-integration'), value: album.trackColor, onChange: (val) => updateBlockAttr(slug, 'trackColor', val) }),
						el(ColorField, { label: __('Button Color', 'mds-bandcamp-integration'), value: album.buttonColor, onChange: (val) => updateBlockAttr(slug, 'buttonColor', val) }),
						el(ColorField, { label: __('Progress Bar Color', 'mds-bandcamp-integration'), value: album.progressBarColor, onChange: (val) => updateBlockAttr(slug, 'progressBarColor', val) }),
						el(ColorField, { label: __('Player Controls Color', 'mds-bandcamp-integration'), value: album.playerControlsColor, onChange: (val) => updateBlockAttr(slug, 'playerControlsColor', val) })
					)
				)
			);
		}

		function renderDiscographyDesigner() {
			const slug = 'bandcamp-discography';
			return el(
				Fragment,
				null,
				el(
					PanelBody,
					{ title: __('Card Display & Grid Layout', 'mds-bandcamp-integration'), initialOpen: true },
					el(RangeControl, {
						label: __('Desktop Columns', 'mds-bandcamp-integration'),
						help: __('Number of release cards per row on desktop screens.', 'mds-bandcamp-integration'),
						value: Number(disco.layout_columns) || 3,
						min: 1,
						max: 6,
						onChange: (val) => updateBlockAttr(slug, 'layout_columns', Number(val) || 3),
					}),
					el(RangeControl, {
						label: __('Mobile Columns', 'mds-bandcamp-integration'),
						help: __('Number of release cards per row on mobile screens.', 'mds-bandcamp-integration'),
						value: Number(disco.layout_columns_mobile) || 2,
						min: 1,
						max: 6,
						onChange: (val) => updateBlockAttr(slug, 'layout_columns_mobile', Number(val) || 2),
					}),
					UnitControl
						? el(UnitControl, {
							label: __('Grid Gap', 'mds-bandcamp-integration'),
							help: __('Spacing between release cards in the discography grid.', 'mds-bandcamp-integration'),
							value: disco.layout_grid_gap || '2rem',
							onChange: (val) => updateBlockAttr(slug, 'layout_grid_gap', val || '2rem'),
						})
						: el(TextControl, {
							label: __('Grid Gap', 'mds-bandcamp-integration'),
							value: disco.layout_grid_gap || '2rem',
							onChange: (val) => updateBlockAttr(slug, 'layout_grid_gap', val),
						}),
					el(SelectControl, {
						label: __('Title Alignment', 'mds-bandcamp-integration'),
						help: __('Horizontal alignment for artist and release titles.', 'mds-bandcamp-integration'),
						value: disco.release_titles_align || 'left',
						options: [
							{ label: __('Left', 'mds-bandcamp-integration'), value: 'left' },
							{ label: __('Center', 'mds-bandcamp-integration'), value: 'center' },
							{ label: __('Right', 'mds-bandcamp-integration'), value: 'right' },
							{ label: __('Justify', 'mds-bandcamp-integration'), value: 'justify' },
						],
						onChange: (val) => updateBlockAttr(slug, 'release_titles_align', val),
					}),
					el(ToggleControl, {
						label: __('Show Album Title', 'mds-bandcamp-integration'),
						checked: Boolean(disco.display_album_title),
						onChange: (val) => updateBlockAttr(slug, 'display_album_title', val),
					}),
					el(ToggleControl, {
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						checked: Boolean(disco.display_album_artist),
						onChange: (val) => updateBlockAttr(slug, 'display_album_artist', val),
					}),
					el(ToggleControl, {
						label: __('Show Cover Artwork', 'mds-bandcamp-integration'),
						checked: Boolean(disco.display_album_art),
						onChange: (val) => updateBlockAttr(slug, 'display_album_art', val),
					}),
					el(ToggleControl, {
						label: __('Show Bandcamp Button', 'mds-bandcamp-integration'),
						checked: Boolean(disco.display_album_link),
						onChange: (val) => updateBlockAttr(slug, 'display_album_link', val),
					})
				),
				el(
					PanelBody,
					{ title: __('Colors, Borders & Padding', 'mds-bandcamp-integration'), initialOpen: false },
					el(
						Stack,
						{ direction: 'column', spacing: 4 },
						el(
							'div',
							{ className: 'mds-bc-admin-app__color-grid' },
							el(ColorField, { label: __('Text Color', 'mds-bandcamp-integration'), value: disco.text_color, onChange: (val) => updateBlockAttr(slug, 'text_color', val) }),
							el(ColorField, { label: __('Button Color', 'mds-bandcamp-integration'), value: disco.button_color, onChange: (val) => updateBlockAttr(slug, 'button_color', val) }),
							el(ColorField, { label: __('Button Text Color', 'mds-bandcamp-integration'), value: disco.button_text_color, onChange: (val) => updateBlockAttr(slug, 'button_text_color', val) })
						),
						BorderControl && el(BorderControl, {
							label: __('Card Border', 'mds-bandcamp-integration'),
							value: disco.release_border || {},
							onChange: (val) => updateBlockAttr(slug, 'release_border', val || {}),
						}),
						BoxControl && el(BoxControl, {
							label: __('Title Padding', 'mds-bandcamp-integration'),
							values: disco.release_titles_padding || {},
							onChange: (val) => updateBlockAttr(slug, 'release_titles_padding', val || {}),
						}),
						BoxControl && el(BoxControl, {
							label: __('Image Padding', 'mds-bandcamp-integration'),
							values: disco.release_image_padding || {},
							onChange: (val) => updateBlockAttr(slug, 'release_image_padding', val || {}),
						})
					)
				)
			);
		}

		function renderMiniplayerDesigner() {
			const slug = 'bandcamp-miniplayer';
			return el(
				Fragment,
				null,
				el(
					PanelBody,
					{ title: __('Playback & Display', 'mds-bandcamp-integration'), initialOpen: true },
					el(TextControl, {
						label: __('Default Track Number', 'mds-bandcamp-integration'),
						help: __('Enter 0 to play the release’s featured Bandcamp track, or a track number (1–999).', 'mds-bandcamp-integration'),
						type: 'number',
						min: 0,
						max: 999,
						value: String(mini.trackNumber ?? '1'),
						onChange: (val) => updateBlockAttr(slug, 'trackNumber', val === '' ? '0' : String(val)),
					}),
					el(ToggleControl, {
						label: __('Show Track Title', 'mds-bandcamp-integration'),
						checked: Boolean(mini.display_track_title),
						onChange: (val) => updateBlockAttr(slug, 'display_track_title', val),
					}),
					el(ToggleControl, {
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						checked: Boolean(mini.display_album_artist),
						onChange: (val) => updateBlockAttr(slug, 'display_album_artist', val),
					}),
					el(ToggleControl, {
						label: __('Show Album Title', 'mds-bandcamp-integration'),
						checked: Boolean(mini.display_album_title),
						onChange: (val) => updateBlockAttr(slug, 'display_album_title', val),
					}),
					el(ToggleControl, {
						label: __('Show Bandcamp Button', 'mds-bandcamp-integration'),
						checked: Boolean(mini.display_album_link),
						onChange: (val) => updateBlockAttr(slug, 'display_album_link', val),
					}),
					el(ToggleControl, {
						label: __('Show Progress Bar', 'mds-bandcamp-integration'),
						checked: Boolean(mini.display_progress_bar),
						onChange: (val) => updateBlockAttr(slug, 'display_progress_bar', val),
					})
				),
				el(
					PanelBody,
					{ title: __('Artwork & Colors', 'mds-bandcamp-integration'), initialOpen: false },
					el(
						Stack,
						{ direction: 'column', spacing: 4 },
						el(
							Stack,
							{ direction: 'row', spacing: 3 },
							el(TextControl, {
								label: __('Aspect Ratio Width', 'mds-bandcamp-integration'),
								type: 'number',
								min: 1,
								max: 99,
								value: String(mini.aspectRatioWidth || '1'),
								onChange: (val) => updateBlockAttr(slug, 'aspectRatioWidth', String(val || '1')),
							}),
							el(TextControl, {
								label: __('Aspect Ratio Height', 'mds-bandcamp-integration'),
								type: 'number',
								min: 1,
								max: 99,
								value: String(mini.aspectRatioHeight || '1'),
								onChange: (val) => updateBlockAttr(slug, 'aspectRatioHeight', String(val || '1')),
							})
						),
						el(RangeControl, {
							label: __('Artwork Opacity', 'mds-bandcamp-integration'),
							value: typeof mini.artOpacity === 'number' ? mini.artOpacity : 0.5,
							min: 0,
							max: 1,
							step: 0.05,
							onChange: (val) => updateBlockAttr(slug, 'artOpacity', Number(val)),
						}),
						el(RangeControl, {
							label: __('Artwork Hover Opacity', 'mds-bandcamp-integration'),
							value: typeof mini.artOpacityHover === 'number' ? mini.artOpacityHover : 0.2,
							min: 0,
							max: 1,
							step: 0.05,
							onChange: (val) => updateBlockAttr(slug, 'artOpacityHover', Number(val)),
						}),
						el(
							'div',
							{ className: 'mds-bc-admin-app__color-grid' },
							el(ColorField, { label: __('Background Color', 'mds-bandcamp-integration'), value: mini.backgroundColor, onChange: (val) => updateBlockAttr(slug, 'backgroundColor', val) }),
							el(ColorField, { label: __('Text Color', 'mds-bandcamp-integration'), value: mini.textColor, onChange: (val) => updateBlockAttr(slug, 'textColor', val) }),
							el(ColorField, { label: __('Play Button Color', 'mds-bandcamp-integration'), value: mini.buttonColor, onChange: (val) => updateBlockAttr(slug, 'buttonColor', val) }),
							el(ColorField, { label: __('Progress Bar Color', 'mds-bandcamp-integration'), value: mini.progressBarColor, onChange: (val) => updateBlockAttr(slug, 'progressBarColor', val) })
						)
					)
				)
			);
		}

		function renderDesignerTab() {
			return el(
				'div',
				{ className: 'mds-bc-admin-app__workspace' + (isPreviewOpen ? ' has-preview' : '') },
				el(
					Card,
					null,
					el(
						CardHeader,
						null,
						el(
							Stack,
							{ direction: 'row', justify: 'space-between' },
							ToggleGroupControl && ToggleGroupControlOption
								? el(
									ToggleGroupControl,
									{
										label: __('Bandcamp Block', 'mds-bandcamp-integration'),
										hideLabelFromVision: true,
										value: activeBlock,
										onChange: (val) => setActiveBlock(String(val)),
										isBlock: false,
									},
									blockTabs.map((item) =>
										el(ToggleGroupControlOption, { key: item.value, value: item.value, label: item.label })
									)
								)
								: el(
									Stack,
									{ direction: 'row', spacing: 2 },
									blockTabs.map((item) =>
										el(
											Button,
											{
												key: item.value,
												variant: activeBlock === item.value ? 'primary' : 'secondary',
												onClick: () => setActiveBlock(item.value),
											},
											item.label
										)
									)
								),
							el(
								Stack,
								{ direction: 'row', spacing: 2 },
								el(
									Button,
									{
										variant: 'secondary',
										'aria-expanded': isPreviewOpen,
										onClick: togglePreview,
									},
									isPreviewOpen
										? __('Hide Live Preview', 'mds-bandcamp-integration')
										: __('Show Live Preview', 'mds-bandcamp-integration')
								),
								el(
									Button,
									{
										variant: 'tertiary',
										isDestructive: true,
										onClick: () => handleResetBlock(activeBlock),
									},
									__('Reset Block Defaults', 'mds-bandcamp-integration')
								)
							)
						)
					),
					el(
						CardBody,
						null,
						el(
							'p',
							{ className: 'description', style: { marginTop: 0 } },
							__('Defaults apply across Gutenberg blocks, shortcodes ([bandcamp-album], [bandcamp-discography], [bandcamp-miniplayer]), and Elementor widgets unless overridden per instance.', 'mds-bandcamp-integration')
						),
						activeBlock === 'bandcamp-album' && renderAlbumDesigner(),
						activeBlock === 'bandcamp-discography' && renderDiscographyDesigner(),
						activeBlock === 'bandcamp-miniplayer' && renderMiniplayerDesigner()
					)
				),
				isPreviewOpen && el(
					'aside',
					{ className: 'mds-bc-admin-app__preview', 'aria-label': __('Live Preview', 'mds-bandcamp-integration') },
					el(
						Card,
						null,
						el(CardHeader, null, el('strong', null, __('Live Preview', 'mds-bandcamp-integration'))),
						el(
							CardBody,
							null,
							el(
								'div',
								{ className: 'mds-bc-admin-app__preview-stage' },
								el(LivePreview, { activeBlock, defaults: blockDefaults })
							)
						)
					)
				)
			);
		}

		return el(
			'div',
			{ className: 'mds-bc-admin-app' },
			el(
				'div',
				{ className: 'mds-bc-admin-app__header' },
				el(
					Stack,
					{ direction: 'row', justify: 'space-between' },
					el(
						'div',
						null,
						el(
							isStandalone ? 'h1' : 'h2',
							{ className: 'mds-bc-admin-app__title' },
							__('Bandcamp Integration', 'mds-bandcamp-integration')
						),
						el(
							'p',
							{ className: 'mds-bc-admin-app__subtitle' },
							__('Manage Bandcamp API caching, diagnostics, and site-wide default styling for blocks, shortcodes, and Elementor widgets.', 'mds-bandcamp-integration')
						)
					),
					el(
						Stack,
						{ direction: 'row', spacing: 2 },
						el(
							Button,
							{
								variant: 'secondary',
								'aria-expanded': activeTab === 'designer' && isPreviewOpen,
								onClick: () => {
									if (activeTab !== 'designer') {
										setActiveTab('designer');
										if (!isPreviewOpen) {
											togglePreview();
										}
									} else {
										togglePreview();
									}
								},
							},
							activeTab === 'designer' && isPreviewOpen
								? __('Hide Live Preview', 'mds-bandcamp-integration')
								: __('Show Live Preview', 'mds-bandcamp-integration')
						),
						el(
							Button,
							{
								variant: 'primary',
								isBusy: isSaving,
								disabled: isSaving,
								onClick: handleSave,
							},
							isSaving ? __('Saving…', 'mds-bandcamp-integration') : __('Save Changes', 'mds-bandcamp-integration')
						)
					)
				)
			),
			el(
				TabPanel,
				{
					key: activeTab,
					activeClass: 'is-active',
					initialTabName: activeTab,
					onSelect: (tabName) => setActiveTab(tabName),
					tabs: [
						{ name: 'admin', title: __('Sync & Cache', 'mds-bandcamp-integration') },
						{ name: 'designer', title: __('Block Defaults', 'mds-bandcamp-integration') },
					],
				},
				(tab) => (tab.name === 'admin' ? renderAdminTab() : renderDesignerTab())
			),
			el(
				'div',
				{ className: 'mds-bc-admin-app__snackbars' },
				el(SnackbarList, {
					notices,
					onRemove: removeNotice,
				})
			)
		);
	}

	class AdminErrorBoundary extends Component {
		constructor(props) {
			super(props);
			this.state = { error: null };
		}

		static getDerivedStateFromError(error) {
			return { error };
		}

		componentDidCatch(error) {
			if (window.console && typeof window.console.error === 'function') {
				window.console.error('MDS Bandcamp Integration Admin UI error:', error);
			}
		}

		render() {
			if (this.state.error) {
				return el(
					'div',
					{ className: 'notice notice-error inline' },
					el('p', null, __('Bandcamp Integration settings encountered a rendering error: ', 'mds-bandcamp-integration') + String(this.state.error && this.state.error.message ? this.state.error.message : this.state.error))
				);
			}
			return this.props.children;
		}
	}

	function mountBandcampAdminApp() {
		const rootEl = document.getElementById('mds-bandcamp-integration-admin-root');
		if (!rootEl || rootEl.dataset.mdsBcMounted === '1') {
			return;
		}
		rootEl.dataset.mdsBcMounted = '1';
		const isStandalone = rootEl.getAttribute('data-standalone') === '1';
		const tree = el(AdminErrorBoundary, null, el(BandcampAdminApp, { isStandalone }));
		if (typeof wp.element.createRoot === 'function') {
			wp.element.createRoot(rootEl).render(tree);
		} else if (typeof wp.element.render === 'function') {
			wp.element.render(tree, rootEl);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', mountBandcampAdminApp);
	} else {
		mountBandcampAdminApp();
	}
})(window.wp, window.jQuery);
