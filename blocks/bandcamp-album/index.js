(function (wp) {
	'use strict';
	var useEffect = wp.element.useEffect;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ColorPalette = wp.blockEditor.ColorPalette || wp.components.ColorPalette;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var Button = wp.components.Button;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

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

		var releasePicker = window.mdsBcUseReleasePicker({
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
})(window.wp);
