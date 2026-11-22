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
	var RangeControl = wp.components.RangeControl;
	var TextControl = wp.components.TextControl;
	var ToggleControl = wp.components.ToggleControl;
	var ServerSideRender = wp.serverSideRender;

registerBlockType('mds-bandcamp/bandcamp-miniplayer', {
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

				createElement(TextControl, {
					label: "Track Number (0 for \"Featured Track\")",
					value: attributes.trackNumber,
					type: 'number',
					min:0,
					max:999,
					onChange: function (value) {
						if (value == undefined || value == '') { value = "0"; }
						return setAttributes({ trackNumber: value });
					}
				}),
				createElement(ToggleControl, {
					label: 'Display Track Title?', 
					checked: attributes.display_track_title, 
					onChange: function(val) { setAttributes({ display_track_title: val }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Artist?', 
					checked: attributes.display_album_artist, 
					onChange: function(val) { setAttributes({ display_album_artist: val }); } 
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
					label: 'Display Progress Bar?', 
					checked: attributes.display_progress_bar, 
					onChange: function(val) { setAttributes({ display_progress_bar: val }); } 
				})
			)
		);

		var StylesPanel = createElement(InspectorControls, { group: 'styles' },
			createElement(PanelBody, { title: 'Artwork', initialOpen: true },
				createElement( 'label', { className: 'bandcamp-label' }, 'Aspect Ratio' ),
				createElement('div', {style:{display:'grid',gridTemplateColumns:'1fr 30px 1fr',gridGap:'6px'}},
					createElement(TextControl, {
						type: 'number',
						value: attributes.aspectRatioWidth,
						onChange: function(val) { setAttributes({ aspectRatioWidth: val }); }
					}),
					createElement('div',{style:{textAlign:'center',lineHeight:'30px'}},'x'),
					createElement(TextControl, {
						type: 'number',
						value: attributes.aspectRatioHeight,
						onChange: function(val) { setAttributes({ aspectRatioHeight: val }); }
					}),
				),
				createElement(RangeControl, {
					label: 'Art Opacity', 
					value: attributes.artOpacity,
					step: 0.01,
					min: 0,
					max: 1,
					onChange: function(val) { setAttributes({ artOpacity: val }); } 
				}),
				createElement(RangeControl, {
					label: 'Art Opacity (Hover)', 
					value: attributes.artOpacityHover,
					step: 0.01,
					min: 0,
					max: 1,
					onChange: function(val) { setAttributes({ artOpacityHover: val }); } 
				})
			),
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
				createElement( 'label', { className: 'bandcamp-label' }, 'Play Button Color' ),
				createElement(ColorPalette, {
					value: attributes.buttonColor,
					onChange: function (val) {
						setAttributes({ buttonColor: val });
					},
					disableAlpha: false
				}),	
				createElement( 'label', { className: 'bandcamp-label' }, 'Progress Bar Color' ),
				createElement(ColorPalette, {
					value: attributes.progressBarColor,
					onChange: function (val) {
						setAttributes({ progressBarColor: val });
					},
					disableAlpha: false
				})
			)
		);

		return createElement(Fragment,null,
			InspectorPanel,
			StylesPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-miniplayer",
					httpMethod: "POST",
					attributes: attributes,
					urlQueryArgs: { post_id: currentPostId }
				})
			)
		);

    },
    save: function () {
        return null; // Rendered server-side
    }
});
})(window.wp);
