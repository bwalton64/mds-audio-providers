(function (wp) {
	'use strict';

	var useEffect = wp.element.useEffect;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ColorPalette = wp.blockEditor.ColorPalette || wp.components.ColorPalette;
	var ColorPicker = wp.components.ColorPicker;
	var Dropdown = wp.components.Dropdown;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var VStack = wp.components.__experimentalVStack;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	function getSiteDefaults() {
		return (window.mdsBcBlockDefaults && window.mdsBcBlockDefaults['bandcamp-album']) || {};
	}

	function resolveAttr(attributes, defaults, key, fallback) {
		return attributes[key] !== undefined ? attributes[key] : (defaults[key] !== undefined ? defaults[key] : fallback);
	}

	function Stack(props) {
		if (VStack) {
			return createElement(VStack, { spacing: props.spacing || 3 }, props.children);
		}
		return createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '12px' } }, props.children);
	}

	function CompactColorField(props) {
		var current = props.value || '';
		if (!Dropdown) {
			return createElement(Fragment, null,
				createElement('span', { className: 'bandcamp-label' }, props.label),
				createElement(ColorPalette, { value: current, onChange: props.onChange, disableAlpha: false })
			);
		}
		return createElement('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
			createElement('span', { className: 'bandcamp-label', style: { margin: 0 } }, props.label),
			createElement(Dropdown, {
				popoverProps: { placement: 'left-start' },
				renderToggle: function (toggleProps) {
					return createElement(Button, {
						variant: 'secondary',
						onClick: toggleProps.onToggle,
						'aria-expanded': toggleProps.isOpen,
						style: { display: 'inline-flex', alignItems: 'center', gap: '8px', width: '100%', justifyContent: 'flex-start' }
					},
						createElement('span', {
							'aria-hidden': 'true',
							style: { display: 'inline-block', width: '16px', height: '16px', borderRadius: '3px', border: '1px solid rgba(0,0,0,0.25)', backgroundColor: current || 'transparent' }
						}),
						createElement('span', null, current || __('Choose color', 'mds-bandcamp-integration'))
					);
				},
				renderContent: function () {
					return createElement('div', { style: { padding: '12px', minWidth: '230px' } },
						ColorPicker
							? createElement(ColorPicker, { color: current || '#000000', enableAlpha: false, onChange: props.onChange })
							: createElement(ColorPalette, { value: current, onChange: props.onChange, disableAlpha: false })
					);
				}
			})
		);
	}

	registerBlockType('mds-bandcamp/bandcamp-album', {
		icon: 'album',
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var defaults = getSiteDefaults();

			useEffect(function () {
				if (!attributes.blockID) {
					setAttributes({ blockID: 'wp-block-' + Math.random().toString(36).slice(2, 12) });
				}
			}, []);

			var embedType = resolveAttr(attributes, defaults, 'embedType', 'custom');
			var displayAlbumTitle = resolveAttr(attributes, defaults, 'display_album_title', true);
			var displayAlbumLink = resolveAttr(attributes, defaults, 'display_album_link', true);
			var displayAlbumArtist = resolveAttr(attributes, defaults, 'display_album_artist', true);
			var displayAlbumArt = resolveAttr(attributes, defaults, 'display_album_art', true);
			var displayAudio = resolveAttr(attributes, defaults, 'display_audio', true);
			var displayPlaylist = resolveAttr(attributes, defaults, 'display_playlist', true);
			var displayMerch = resolveAttr(attributes, defaults, 'display_merch', true);

			var releasePicker = window.mdsBcUseReleasePicker({
				value: attributes.albumID,
				label: __('Release', 'mds-bandcamp-integration'),
				help: __('Search published releases with a verified Bandcamp album, or leave blank to use the current release.', 'mds-bandcamp-integration'),
				enabled: props.isSelected,
				debugLabel: __('Bandcamp Album ID', 'mds-bandcamp-integration'),
				mapValue: function (item) { return item.album_id; },
				onSelect: function (item) {
					setAttributes({
						albumID: item && item.album_id ? String(item.album_id) : '',
						url: item && item.url ? item.url : ''
					});
				}
			});

			var InspectorPanel = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __('Release & Player', 'mds-bandcamp-integration'), initialOpen: true },
					createElement(
						Stack,
						{ spacing: 3 },
						releasePicker,
						createElement(SelectControl, {
							label: __('Player Mode', 'mds-bandcamp-integration'),
							help: __('Choose between the custom HTML5 audio player or Bandcamp’s embedded iframe.', 'mds-bandcamp-integration'),
							value: embedType,
							options: [
								{ label: __('Custom HTML Player', 'mds-bandcamp-integration'), value: 'custom' },
								{ label: __('Bandcamp Embed', 'mds-bandcamp-integration'), value: 'bandcamp' }
							],
							onChange: function (value) { setAttributes({ embedType: value }); }
						})
					)
				),
				createElement(
					PanelBody,
					{ title: __('Display Options', 'mds-bandcamp-integration'), initialOpen: false },
					createElement(ToggleControl, {
						label: __('Show Album Title', 'mds-bandcamp-integration'),
						checked: Boolean(displayAlbumTitle),
						onChange: function (val) { setAttributes({ display_album_title: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Bandcamp Button', 'mds-bandcamp-integration'),
						checked: Boolean(displayAlbumLink),
						onChange: function (val) { setAttributes({ display_album_link: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						checked: Boolean(displayAlbumArtist),
						onChange: function (val) { setAttributes({ display_album_artist: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Cover Artwork', 'mds-bandcamp-integration'),
						checked: Boolean(displayAlbumArt),
						onChange: function (val) { setAttributes({ display_album_art: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Audio Player', 'mds-bandcamp-integration'),
						checked: Boolean(displayAudio),
						onChange: function (val) { setAttributes({ display_audio: val }); }
					}),
					embedType === 'custom' && createElement(ToggleControl, {
						label: __('Show Tracklist', 'mds-bandcamp-integration'),
						checked: Boolean(displayPlaylist),
						onChange: function (val) { setAttributes({ display_playlist: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Merchandise', 'mds-bandcamp-integration'),
						checked: Boolean(displayMerch),
						onChange: function (val) { setAttributes({ display_merch: val }); }
					})
				)
			);

			var StylesPanel = createElement(
				InspectorControls,
				{ group: 'styles' },
				createElement(
					PanelBody,
					{ title: __('Colors', 'mds-bandcamp-integration'), initialOpen: true },
					createElement(
						Stack,
						{ spacing: 3 },
						createElement('p', { className: 'components-base-control__help', style: { margin: 0 } }, __('Unset colors inherit from Releases → Settings → Bandcamp Integration.', 'mds-bandcamp-integration')),
						createElement(CompactColorField, {
							label: __('Background Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'backgroundColor', '#171717'),
							onChange: function (val) { setAttributes({ backgroundColor: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Text Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'textColor', '#ffffff'),
							onChange: function (val) { setAttributes({ textColor: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Tracklist Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'trackColor', '#ffffff'),
							onChange: function (val) { setAttributes({ trackColor: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Button Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'buttonColor', '#000000'),
							onChange: function (val) { setAttributes({ buttonColor: val }); }
						}),
						embedType === 'custom' && createElement(CompactColorField, {
							label: __('Progress Bar Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'progressBarColor', '#ffffff'),
							onChange: function (val) { setAttributes({ progressBarColor: val }); }
						}),
						embedType === 'custom' && createElement(CompactColorField, {
							label: __('Player Controls Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'playerControlsColor', '#ffffff'),
							onChange: function (val) { setAttributes({ playerControlsColor: val }); }
						}),
						createElement(Button, {
							variant: 'secondary',
							isDestructive: true,
							onClick: function () {
								setAttributes({
									backgroundColor: undefined,
									textColor: undefined,
									trackColor: undefined,
									buttonColor: undefined,
									progressBarColor: undefined,
									playerControlsColor: undefined
								});
							}
						}, __('Reset Colors to Site Defaults', 'mds-bandcamp-integration'))
					)
				)
			);

			var editorStore = wp.data.select('core/editor');
			var currentPostId = editorStore && editorStore.getCurrentPostId ? (editorStore.getCurrentPostId() || 0) : 0;

			return createElement(
				Fragment,
				null,
				InspectorPanel,
				StylesPanel,
				createElement(
					'div',
					useBlockProps(),
					createElement(ServerSideRender, {
						block: 'mds-bandcamp/bandcamp-album',
						attributes: attributes,
						urlQueryArgs: currentPostId ? { post_id: currentPostId } : {}
					})
				)
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp);
