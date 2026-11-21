var useEffect = wp.element.useEffect;
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

registerBlockType('mds-bandcamp/bandcamp-discography', {
	icon: 'album',
	edit: function (props) {
		var attributes = props.attributes;
		var setAttributes = props.setAttributes;

		useEffect(() => {
			if (!attributes.blockID) {
				setAttributes({ blockID: 'wp-block-' + Math.random().toString(36).slice(2, 12) });
			}
		}, []);

		var InspectorPanel = createElement(InspectorControls, null,
			createElement(PanelBody, null,
				createElement(TextControl, {
					label: "Artist subdomain / URL",
					value: attributes.artist_url,
					onChange: function (value) {
						return setAttributes({ artist_url: value });
					},
				}),
				createElement('small',{},
					'Enter account name from Bandcamp URL, not including any other part. Example:',
					createElement('br'),
					createElement('span',{style:{color:'#888888',textDecoration:'line-through'}},'https://'),
					createElement('span',{style:{color:'#000000',fontWeight:'bold'}},'artistname'),
					createElement('span',{style:{color:'#888888',textDecoration:'line-through'}},'.bandcamp.com'),

				),

				createElement('hr', null),
				createElement('label',{style:{display:'block',marginBottom:'10px'}},'Layout Options'),

				createElement('label', {className:'bandcamp-label'},'Layout Columns (Desktop)'),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
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
				createElement('label', {className:'bandcamp-label'},'Layout Columns (Mobile)'),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					[1, 2, 3, 4, 5, 6].map( function( val ) {
						return createElement(Button,{
							isPressed: attributes.layout_columns_mobile === val,
							onClick: function() {
								setAttributes({ layout_columns_mobile: val });
							},
							key: val
						},val);
					})
				),
				createElement(UnitControl, {
					label: "Grid Gap",
					value: attributes.layout_grid_gap || '',
					units: [
						{ value: 'px', label: 'px' },
						{ value: 'em', label: 'em' },
						{ value: 'rem', label: 'rem' },
						{ value: 'vw', label: 'vw' },
						{ value: 'vh', label: 'vh' },
						{ value: '%', label: '%' }
					],
					onChange: function(value) {
						return setAttributes({ layout_grid_gap: value || '2vw' });
					}
				}),
                // Border (width / style / color / radius)
				createElement(BorderControl, {
					label: 'Border',
					value: attributes.release_border,
					onChange: function (newValues) {
						setAttributes({ release_border: newValues });
					},
					enable: ['width', 'style', 'color', 'radius'],
					colors: wp.data.select('core/block-editor').getSettings().colors
				}),


				createElement('hr', null),
				createElement('label',{style:{display:'block',marginBottom:'10px'}},'Display Options'),


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


				createElement('hr', null),
				createElement('label',{style:{display:'block',marginBottom:'10px'}},'Title Options'),


				createElement(SelectControl, {
					type: "string",
					label: "Title Alignment",
					value: attributes.release_titles_align,
					options: [
						{ label: "Center",value: "center" },
						{ label: "Left",value: "left" },
						{ label: "Right", value: "right" },
						{ label: "Justify", value: "justify" }
					],
					onChange: function (value) {
						return setAttributes({ release_titles_align: value });
					},
				}),
				createElement(BoxControl, {
					label: "Title Padding",
					values: attributes.release_titles_padding || {},
					onChange: function(value) {
						return setAttributes({ release_titles_padding: value });
					}
				}),		
				createElement(BoxControl, {
					label: "Image Padding",
					values: attributes.release_image_padding || {},
					onChange: function(value) {
						return setAttributes({ release_image_padding: value });
					}
				}),		


				createElement('hr', null),
				createElement('label',{style:{display:'block',marginBottom:'10px'}},'Color Options'),


				createElement( 'label', { className: 'bandcamp-label' }, 'Text Color' ),
				createElement(ColorPalette, {
					value: attributes.text_color,
					onChange: function (val) {
						setAttributes({ text_color: val });
					},
					disableAlpha: false
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Button Color' ),
				createElement(ColorPalette, {
					value: attributes.button_color,
					onChange: function (val) {
						setAttributes({ button_color: val });
					},
					disableAlpha: false
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Button Text Color' ),
				createElement(ColorPalette, {
					value: attributes.button_text_color,
					onChange: function (val) {
						setAttributes({ button_text_color: val });
					},
					disableAlpha: false
				})
			)
		);

		return createElement(Fragment, null,
			InspectorPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-discography",
					httpMethod: "POST",
					attributes: attributes
				})
			)
		);
	},
	save: function () { return null; }
});
