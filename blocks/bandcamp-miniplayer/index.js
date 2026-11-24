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
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var VStack = wp.components.__experimentalVStack;
	var HStack = wp.components.__experimentalHStack;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	function getSiteDefaults() {
		return (window.mdsBcBlockDefaults && window.mdsBcBlockDefaults['bandcamp-miniplayer']) || {};
	}

	function resolveAttr(attributes, defaults, key, fallback) {
		return attributes[key] !== undefined ? attributes[key] : (defaults[key] !== undefined ? defaults[key] : fallback);
	}

	function Stack(props) {
		if (props.direction === 'row' && HStack) {
			return createElement(HStack, { spacing: props.spacing || 2, alignment: 'center' }, props.children);
		}
		if (VStack) {
			return createElement(VStack, { spacing: props.spacing || 3 }, props.children);
		}
		return createElement('div', { style: { display: 'flex', flexDirection: props.direction === 'row' ? 'row' : 'column', gap: '12px' } }, props.children);
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

	registerBlockType('mds-bandcamp/bandcamp-miniplayer', {
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

			var editorStore = wp.data.select('core/editor');
			var currentPostId = editorStore && editorStore.getCurrentPostId ? (editorStore.getCurrentPostId() || 0) : 0;

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
					{ title: __('Release & Track', 'mds-bandcamp-integration'), initialOpen: true },
					createElement(
						Stack,
						{ spacing: 3 },
						releasePicker,
						createElement(TextControl, {
							label: __('Track Number', 'mds-bandcamp-integration'),
							help: __('Enter 0 to play the release’s featured Bandcamp track, or a track number (1–999).', 'mds-bandcamp-integration'),
							value: String(resolveAttr(attributes, defaults, 'trackNumber', '1')),
							type: 'number',
							min: 0,
							max: 999,
							onChange: function (value) {
								setAttributes({ trackNumber: value === '' || value === undefined ? '0' : String(value) });
							}
						})
					)
				),
				createElement(
					PanelBody,
					{ title: __('Display Options', 'mds-bandcamp-integration'), initialOpen: false },
					createElement(ToggleControl, {
						label: __('Show Track Title', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_track_title', true)),
						onChange: function (val) { setAttributes({ display_track_title: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_album_artist', true)),
						onChange: function (val) { setAttributes({ display_album_artist: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Album Title', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_album_title', true)),
						onChange: function (val) { setAttributes({ display_album_title: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Bandcamp Button', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_album_link', true)),
						onChange: function (val) { setAttributes({ display_album_link: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Progress Bar', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_progress_bar', true)),
						onChange: function (val) { setAttributes({ display_progress_bar: val }); }
					})
				)
			);

			var StylesPanel = createElement(
				InspectorControls,
				{ group: 'styles' },
				createElement(
					PanelBody,
					{ title: __('Artwork', 'mds-bandcamp-integration'), initialOpen: true },
					createElement(
						Stack,
						{ spacing: 3 },
						createElement(
							Stack,
							{ direction: 'row', spacing: 2 },
							createElement(TextControl, {
								label: __('Aspect Width', 'mds-bandcamp-integration'),
								type: 'number',
								min: 1,
								max: 99,
								value: String(resolveAttr(attributes, defaults, 'aspectRatioWidth', '1')),
								onChange: function (val) { setAttributes({ aspectRatioWidth: String(val || '1') }); }
							}),
							createElement(TextControl, {
								label: __('Aspect Height', 'mds-bandcamp-integration'),
								type: 'number',
								min: 1,
								max: 99,
								value: String(resolveAttr(attributes, defaults, 'aspectRatioHeight', '1')),
								onChange: function (val) { setAttributes({ aspectRatioHeight: String(val || '1') }); }
							})
						),
						createElement(RangeControl, {
							label: __('Artwork Opacity', 'mds-bandcamp-integration'),
							value: Number(resolveAttr(attributes, defaults, 'artOpacity', 0.5)),
							step: 0.05,
							min: 0,
							max: 1,
							onChange: function (val) { setAttributes({ artOpacity: Number(val) }); }
						}),
						createElement(RangeControl, {
							label: __('Artwork Opacity (Hover)', 'mds-bandcamp-integration'),
							value: Number(resolveAttr(attributes, defaults, 'artOpacityHover', 0.2)),
							step: 0.05,
							min: 0,
							max: 1,
							onChange: function (val) { setAttributes({ artOpacityHover: Number(val) }); }
						})
					)
				),
				createElement(
					PanelBody,
					{ title: __('Colors', 'mds-bandcamp-integration'), initialOpen: false },
					createElement(
						Stack,
						{ spacing: 3 },
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
							label: __('Play Button Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'buttonColor', '#000000'),
							onChange: function (val) { setAttributes({ buttonColor: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Progress Bar Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'progressBarColor', '#ffffff'),
							onChange: function (val) { setAttributes({ progressBarColor: val }); }
						}),
						createElement(Button, {
							variant: 'secondary',
							isDestructive: true,
							onClick: function () {
								setAttributes({
									aspectRatioWidth: undefined,
									aspectRatioHeight: undefined,
									artOpacity: undefined,
									artOpacityHover: undefined,
									backgroundColor: undefined,
									textColor: undefined,
									buttonColor: undefined,
									progressBarColor: undefined
								});
							}
						}, __('Reset Styles to Site Defaults', 'mds-bandcamp-integration'))
					)
				)
			);

			return createElement(
				Fragment,
				null,
				InspectorPanel,
				StylesPanel,
				createElement(
					'div',
					useBlockProps(),
					createElement(ServerSideRender, {
						block: 'mds-bandcamp/bandcamp-miniplayer',
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
