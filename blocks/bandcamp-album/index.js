var { useState, useEffect } = wp.element;
var apiFetch = wp.apiFetch;
var AlignmentToolbar = wp.blockEditor.AlignmentToolbar;
var BlockControls = wp.blockEditor.BlockControls;
var BorderControl = wp.components.BorderControl;
var BoxControl = wp.components.BoxControl;
var Button = wp.components.Button;
var ButtonGroup = wp.components.ButtonGroup;
var ColorPalette = wp.blockEditor.ColorPalette || wp.components.ColorPalette;
var ColorPicker = wp.components.ColorPicker;
var createElement = wp.element.createElement;
var Fragment = wp.element.Fragment;
var FontSizePicker  = wp.components.FontSizePicker;
var InnerBlocks = wp.blockEditor.InnerBlocks;
var InspectorControls = wp.blockEditor.InspectorControls;
var MediaUpload = wp.blockEditor.MediaUpload;
var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
var PanelBody = wp.components.PanelBody;
var registerBlockType = wp.blocks.registerBlockType;
var RangeControl = wp.components.RangeControl;
var RichText = wp.blockEditor.RichText;
var SelectControl = wp.components.SelectControl;
var ServerSideRender  = wp.serverSideRender;
var TextareaControl = wp.components.TextareaControl;
var TextControl = wp.components.TextControl;
var ToggleControl = wp.components.ToggleControl;
var Toolbar = wp.components.Toolbar;
var UnitControl = wp.components.__experimentalUnitControl || wp.components.UnitControl;
var useBlockProps = wp.blockEditor.useBlockProps;
var useSelect = wp.data.useSelect;
var Spinner = wp.components.Spinner;
var Notice = wp.components.Notice;

registerBlockType('mds-bandcamp/bandcamp-album', {
	icon: 'album',
	edit: function (props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		useEffect(() => {
			if (!attributes.blockID) {
				setAttributes({ blockID: 'wp-block-' + Math.random().toString(36).slice(2, 12) });
			}
		}, []);

		var editorStore = wp.data.select('core/editor');
		var currentPostId = editorStore && editorStore.getCurrentPostId ? (editorStore.getCurrentPostId() || 0) : 0;

		var releasePicker = window.mdsBandcampUseReleasePicker({
			value: attributes.albumID,
			label: 'Release',
			help: 'Search published releases with a verified Bandcamp album by title or artist.',
			enabled: props.isSelected,
			debugLabel: 'Bandcamp Album ID',
			mapValue: function (item) { return item.album_id; },
			onSelect: function (item) {
				setAttributes({
					albumID: item && item.album_id ? String(item.album_id) : '',
					url: item && item.url ? item.url : ''
				});
			}
		});

		var InspectorPanel = createElement(InspectorControls, null,
			createElement(PanelBody, null,
				releasePicker,
				createElement(SelectControl, {
					label: "Embed Type",
					value: attributes.embedType,
					options: [
						{ label: 'Bandcamp Embed', value: 'bandcamp' },
						{ label: 'Custom HTML Player', value: 'custom' }
					],
					onChange: function (value) {
						return setAttributes({ embedType: value });
					}
				}),
				createElement(ToggleControl, {
					label: 'Display Album Title?',
					checked: attributes.display_album_title,
					onChange: function(val) { setAttributes({ display_album_title: val }); }
				}),
				createElement(ToggleControl, {
					label: 'Display Album Link?',
					checked: attributes.display_album_link,
					onChange: function(val) { setAttributes({ display_album_link: val }); }
				}),
				createElement(ToggleControl, {
					label: 'Display Artist?',
					checked: attributes.display_album_artist,
					onChange: function(val) { setAttributes({ display_album_artist: val }); }
				}),
				createElement(ToggleControl, {
					label: 'Display Album Art?',
					checked: attributes.display_album_art,
					onChange: function(val) { setAttributes({ display_album_art: val }); }
				}),
				createElement(ToggleControl, {
					label: 'Display Audio?',
					checked: attributes.display_audio,
					onChange: function(val) { setAttributes({ display_audio: val }); }
				}),
				attributes.embedType == 'custom' && createElement(ToggleControl, {
					label: 'Display Playlist?',
					checked: attributes.display_playlist,
					onChange: function(val) { setAttributes({ display_playlist: val }); }
				}),
				createElement(ToggleControl, {
					label: 'Display Merchandise?',
					checked: attributes.display_merch,
					onChange: function(val) { setAttributes({ display_merch: val }); }
				})
			)
		);

		var StylesPanel = createElement(InspectorControls, { group: 'styles' },
			createElement(PanelBody, { title: 'Colors', initialOpen: true },
				createElement( 'label', { className: 'bandcamp-label' }, 'Background Color' ),
				createElement(ColorPalette, {
					value: attributes.backgroundColor,
					onChange: function (val) {
						setAttributes({ backgroundColor: val });
					},
					disableAlpha: false
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Text Color' ),
				createElement(ColorPalette, {
					value: attributes.textColor,
					onChange: function (val) {
						setAttributes({ textColor: val });
					},
					disableAlpha: false
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Track Color' ),
				createElement(ColorPalette, {
					value: attributes.trackColor,
					onChange: function (val) {
						setAttributes({ trackColor: val });
					},
					disableAlpha: false
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Button Color' ),
				createElement(ColorPalette, {
					value: attributes.buttonColor,
					onChange: function (val) {
						setAttributes({ buttonColor: val });
					},
					disableAlpha: false
				}),
				attributes.embedType == 'custom' && createElement( 'label', { className: 'bandcamp-label' }, 'Progress Bar Color' ),
				attributes.embedType == 'custom' && createElement(ColorPalette, {
					value: attributes.progressBarColor,
					onChange: function (val) {
						setAttributes({ progressBarColor: val });
					},
					disableAlpha: false
				}),
				attributes.embedType == 'custom' && createElement( 'label', { className: 'bandcamp-label' }, 'Player Controls Color' ),
				attributes.embedType == 'custom' && createElement(ColorPalette, {
					value: attributes.playerControlsColor,
					onChange: function (val) {
						setAttributes({ playerControlsColor: val });
					},
					disableAlpha: false
				})
			)
		);

		return createElement(Fragment, null,
			InspectorPanel,
			StylesPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-album",
					httpMethod: "POST",
					attributes: attributes,
					urlQueryArgs: { post_id: currentPostId }
				})
			)
		);
	},
	save: function () { return null; }
});
