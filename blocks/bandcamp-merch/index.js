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

var photoVersion = [
	{ label: 'Cropped', value: 'cropped' },
	{ label: 'Uncropped', value: 'uncropped' }
];

registerBlockType('mds-bandcamp/bandcamp-merch', {
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
					label: 'Single Album or All Products?',
					value: attributes.single_or_all,
					options: [ { label: 'Single Album', value: 'single' }, { label: 'All Products', value: 'all' } ],
					onChange: function(val) { setAttributes({ single_or_all: val }); }
				}),	
				createElement(SelectControl, {
					label: 'Layout Style',
					value: attributes.layout_style,
					options: [ { label: 'Cards', value: 'cards' }, { label: 'List', value: 'list' } ],
					onChange: function(val) { setAttributes({ layout_style: val }); }
				}),	
				createElement('label', {className:'bandcamp-label'},'Photo Version'),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					photoVersion.map( function( option ) {
						return createElement(Button,{
							isPressed: attributes.photo_version === option.value,
							onClick: function() {
								setAttributes({ photo_version: option.value });
							},
							key: option.value
						},option.label);
					})
				),
				createElement('br'),
				attributes.photo_version == 'uncropped' && createElement('small',{},
					'NOTICE: Uncropped images are the full resolution photo uploaded to Bandcamp for this item. Too many will lead to large page load times.',
					createElement('br')
				),
				createElement('br'),
				createElement('label', {className:'bandcamp-label'},'DISPLAY OPTIONS'),
				createElement(ToggleControl, {
					label: 'Display Album Artist?', 
					checked: attributes.display_album_artist, 
					onChange: function(value) { setAttributes({ display_album_artist: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Album Title?', 
					checked: attributes.display_album_title, 
					onChange: function(value) { setAttributes({ display_album_title: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Description?', 
					checked: attributes.display_description, 
					onChange: function(value) { setAttributes({ display_description: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Edition Size?', 
					checked: attributes.display_edition_size, 
					onChange: function(value) { setAttributes({ display_edition_size: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Media Type?', 
					checked: attributes.display_media_type, 
					onChange: function(value) { setAttributes({ display_media_type: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Photos?', 
					checked: attributes.display_photos, 
					onChange: function(value) { setAttributes({ display_photos: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Price?', 
					checked: attributes.display_price, 
					onChange: function(value) { setAttributes({ display_price: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Quantity Available?', 
					checked: attributes.display_quantity_available, 
					onChange: function(value) { setAttributes({ display_quantity_available: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Sold Out Items?', 
					checked: attributes.display_sold_out_items, 
					onChange: function(value) { setAttributes({ display_sold_out_items: value }); } 
				}),
				createElement(ToggleControl, {
					label: 'Display Purchase Button?', 
					checked: attributes.display_purchase_button, 
					onChange: function(value) { setAttributes({ display_purchase_button: value }); } 
				})
			)
		);

		var StylesPanel = createElement(InspectorControls, { group: 'styles' },
			createElement(PanelBody, { title: 'Layout', initialOpen: true },
				attributes.layout_style == 'cards' && createElement('label', {className:'bandcamp-label'},'Layout Columns'),
				attributes.layout_style == 'cards' && createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					[1, 2, 3, 4, 5, 6].map( function( val ) {
						return createElement(Button,{
							isPressed: attributes.layout_columns === val,
							onClick: function() {
								setAttributes({ layout_columns: val });
							},
							key: val
						},val);
					})
				),
				attributes.layout_style != 'cards' && createElement('p', null, 'Column controls apply to the Cards layout.')
			),
			createElement(PanelBody, { title: 'Purchase Button', initialOpen: true },
				attributes.display_purchase_button && createElement('h3', null, "Purchase Button : BG Color"),
				attributes.display_purchase_button && createElement(ColorPalette, {
					label: "Purchase Button : BG Color",
					value: attributes.style_purchase_button_bgcolor,
					onChange: function(value) { setAttributes({ style_purchase_button_bgcolor: value }); },
					disableAlpha: true
				}),
				attributes.display_purchase_button && createElement('h3', null, "Purchase Button : Text Color"),
				attributes.display_purchase_button && createElement(ColorPalette, {
					label: "Purchase Button : Text Color",
					value: attributes.style_purchase_button_textcolor,
					onChange: function(value) { setAttributes({ style_purchase_button_textcolor: value }); },
					disableAlpha: true
				}),
				!attributes.display_purchase_button && createElement('p', null, 'Enable the purchase button to style it.')
			)
		);

		return createElement(Fragment,null,
			StylesPanel,
			InspectorPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-merch",
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
