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
	var BorderControl = wp.components.BorderControl;
	var BoxControl = wp.components.BoxControl;
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var UnitControl = wp.components.UnitControl || wp.components.__experimentalUnitControl;
	var VStack = wp.components.__experimentalVStack;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	function getSiteDefaults() {
		return (window.mdsBcBlockDefaults && window.mdsBcBlockDefaults['bandcamp-discography']) || {};
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

	registerBlockType('mds-bandcamp/bandcamp-discography', {
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

			var artistPicker = window.mdsBcUseArtistPicker({
				artistId: attributes.artistId,
				value: attributes.artist_url,
				label: __('Artist', 'mds-bandcamp-integration'),
				help: __('Search published artists with a configured Bandcamp Artist URL. Leave blank to use the current context.', 'mds-bandcamp-integration'),
				enabled: props.isSelected,
				debugLabel: __('Bandcamp Artist URL', 'mds-bandcamp-integration'),
				onSelect: function (artist) {
					setAttributes({
						artistId: artist && artist.id ? Number(artist.id) : 0,
						artist_url: artist && (artist.artist_url || artist.subdomain) ? String(artist.artist_url || artist.subdomain) : ''
					});
				}
			});

			var InspectorPanel = createElement(
				InspectorControls,
				null,
				createElement(
					PanelBody,
					{ title: __('Artist Source', 'mds-bandcamp-integration'), initialOpen: true },
					artistPicker
				),
				createElement(
					PanelBody,
					{ title: __('Display Options', 'mds-bandcamp-integration'), initialOpen: false },
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
						label: __('Show Artist Name', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_album_artist', true)),
						onChange: function (val) { setAttributes({ display_album_artist: val }); }
					}),
					createElement(ToggleControl, {
						label: __('Show Cover Artwork', 'mds-bandcamp-integration'),
						checked: Boolean(resolveAttr(attributes, defaults, 'display_album_art', true)),
						onChange: function (val) { setAttributes({ display_album_art: val }); }
					})
				)
			);

			var StylesPanel = createElement(
				InspectorControls,
				{ group: 'styles' },
				createElement(
					PanelBody,
					{ title: __('Grid & Card Layout', 'mds-bandcamp-integration'), initialOpen: true },
					createElement(
						Stack,
						{ spacing: 3 },
						createElement(RangeControl, {
							label: __('Desktop Columns', 'mds-bandcamp-integration'),
							value: Number(resolveAttr(attributes, defaults, 'layout_columns', 3)),
							min: 1,
							max: 6,
							onChange: function (val) { setAttributes({ layout_columns: Number(val) }); }
						}),
						createElement(RangeControl, {
							label: __('Mobile Columns', 'mds-bandcamp-integration'),
							value: Number(resolveAttr(attributes, defaults, 'layout_columns_mobile', 2)),
							min: 1,
							max: 6,
							onChange: function (val) { setAttributes({ layout_columns_mobile: Number(val) }); }
						}),
						UnitControl && createElement(UnitControl, {
							label: __('Grid Gap', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'layout_grid_gap', '2rem'),
							units: [
								{ value: 'px', label: 'px' },
								{ value: 'em', label: 'em' },
								{ value: 'rem', label: 'rem' },
								{ value: 'vw', label: 'vw' },
								{ value: 'vh', label: 'vh' },
								{ value: '%', label: '%' }
							],
							onChange: function (value) { setAttributes({ layout_grid_gap: value || undefined }); }
						}),
						BorderControl && createElement(BorderControl, {
							label: __('Card Border', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'release_border', {}),
							onChange: function (newValues) { setAttributes({ release_border: newValues }); },
							colors: wp.data.select('core/block-editor').getSettings().colors
						})
					)
				),
				createElement(
					PanelBody,
					{ title: __('Titles & Spacing', 'mds-bandcamp-integration'), initialOpen: false },
					createElement(
						Stack,
						{ spacing: 3 },
						createElement(SelectControl, {
							label: __('Title Alignment', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'release_titles_align', 'left'),
							options: [
								{ label: __('Left', 'mds-bandcamp-integration'), value: 'left' },
								{ label: __('Center', 'mds-bandcamp-integration'), value: 'center' },
								{ label: __('Right', 'mds-bandcamp-integration'), value: 'right' },
								{ label: __('Justify', 'mds-bandcamp-integration'), value: 'justify' }
							],
							onChange: function (value) { setAttributes({ release_titles_align: value }); }
						}),
						BoxControl && createElement(BoxControl, {
							label: __('Title Padding', 'mds-bandcamp-integration'),
							values: resolveAttr(attributes, defaults, 'release_titles_padding', {}),
							onChange: function (nextValues) { setAttributes({ release_titles_padding: nextValues }); }
						}),
						BoxControl && createElement(BoxControl, {
							label: __('Image Padding', 'mds-bandcamp-integration'),
							values: resolveAttr(attributes, defaults, 'release_image_padding', {}),
							onChange: function (nextValues) { setAttributes({ release_image_padding: nextValues }); }
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
							label: __('Text Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'text_color', '#000000'),
							onChange: function (val) { setAttributes({ text_color: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Button Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'button_color', '#000000'),
							onChange: function (val) { setAttributes({ button_color: val }); }
						}),
						createElement(CompactColorField, {
							label: __('Button Text Color', 'mds-bandcamp-integration'),
							value: resolveAttr(attributes, defaults, 'button_text_color', '#ffffff'),
							onChange: function (val) { setAttributes({ button_text_color: val }); }
						}),
						createElement(Button, {
							variant: 'secondary',
							isDestructive: true,
							onClick: function () {
								setAttributes({
									layout_columns: undefined,
									layout_columns_mobile: undefined,
									layout_grid_gap: undefined,
									release_border: undefined,
									release_titles_align: undefined,
									release_titles_padding: undefined,
									release_image_padding: undefined,
									text_color: undefined,
									button_color: undefined,
									button_text_color: undefined
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
						block: 'mds-bandcamp/bandcamp-discography',
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
