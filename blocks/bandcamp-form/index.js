(function (wp) {
	'use strict';
	var useEffect = wp.element.useEffect;
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var ColorPalette = wp.blockEditor.ColorPalette || wp.components.ColorPalette;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var BorderControl = wp.components.BorderControl;
	var BoxControl = wp.components.BoxControl;
	var Button = wp.components.Button;
	var ButtonGroup = wp.components.ButtonGroup;
	var PanelBody = wp.components.PanelBody;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

var fontSizeOptions = [
    { name: 'Small',  slug: 'small',  size: 12  },
    { name: 'Normal', slug: 'normal', size: 16  },
    { name: 'Large',  slug: 'large',  size: 36  }
];
var layoutOptions = [
	{ label: 'Stacked', value: 'stacked' },
	{ label: 'Side by Side', value: 'sidebyside' }
];
var alignmentOptions = [
	{ label: 'Left', value: 'left' },
	{ label: 'Center', value: 'center' },
	{ label: 'Right', value: 'right' }
];


registerBlockType('mds-bandcamp/bandcamp-form', {
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
					label: 'Button Text',
					value: attributes.button_text,
					onChange: function(val) { setAttributes({ button_text: val }); }
				}),
				createElement(TextControl, {
					label: 'Input Placeholder Text',
					value: attributes.input_placeholder_text,
					onChange: function(val) { setAttributes({ input_placeholder_text: val }); }
				}),
				createElement( 'label', { className: 'bandcamp-label' }, 'Layout' ),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					layoutOptions.map( function( option ) {
						return createElement(Button,{
							isPressed: attributes.layout_style === option.value,
							onClick: function() {
								setAttributes({ layout_style: option.value });
							},
							key: option.value
						},option.label);
					})
				),
			),

			/////////////////////////////////////////////////////////////////////////////////
			// â€”â€”â€” â€œInput Stylesâ€ panel â€”â€”â€”
			/////////////////////////////////////////////////////////////////////////////////
            createElement( PanelBody, { title: 'Input Styles', initialOpen: false },

				// Text align
				createElement( 'label', { className: 'bandcamp-label' }, 'Text Align' ),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					alignmentOptions.map( function( option ) {
						return createElement(Button,{
							isPressed: attributes.inputTextAlign === option.value,
							onClick: function() {
								setAttributes({ inputTextAlign: option.value });
							},
							key: option.value
						},option.label);
					})
				),

                // Padding
				createElement(BoxControl, {
					label: "Padding",
					values: attributes.inputPadding,
					onChange: function (newValues) {
						setAttributes({ inputPadding: newValues });
					},
					showUnit: true,
					__experimentalUnitControl: {
						units: ['px', 'em', 'rem', '%']
					}
				}),

                // Border (width / style / color / radius)
				createElement(BorderControl, {
					label: 'Border',
					value: attributes.inputBorder,
					onChange: function (newValues) {
						setAttributes({ inputBorder: newValues });
					},
					enable: ['width', 'style', 'color', 'radius'],
					colors: wp.data.select('core/block-editor').getSettings().colors
				}),
				createElement('br'),

                // Font Family
				createElement(TextControl, {
					label: 'Font Family',
					value: attributes.inputFontFamily,
					onChange: function(newFontFamily) {
						setAttributes({ inputFontFamily: newFontFamily });
					}
				}),	

                // Font Size
				createElement("div", {style: {display:'grid',gridTemplateColumns:'2fr 1fr'}}, 
					createElement(RangeControl, {
						label: "Font Size",
						value: attributes.inputFontSize,
						min: 1,
						max: 500,
						onChange: function (value) {
							return setAttributes({ inputFontSize: value });
						},
						style: {margin:0}
					}),
					createElement(SelectControl, {
						label: "Unit",
						value: attributes.inputFontUnit,
						options: [
							{ label: 'px', value: 'px' },
							{ label: 'rem',value: 'rem' },
							{ label: 'em', value: 'em' },
							{ label: 'vw', value: 'vw' },
							{ label: 'vh', value: 'vh' }
						],
						onChange: function (value) {
							return setAttributes({ inputFontUnit: value });
						}
					})
				),

                // Line Height
				createElement(RangeControl, {
					label: "Line Height",
					value: attributes.inputLineHeight,
					onChange: function (newLH) {
						setAttributes({ inputLineHeight: newLH });
					},
					min: 1,
					max: 5,
					step: 0.1
				}),

                // Background Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Background Color' ),
				createElement(ColorPalette, {
					value: attributes.inputBackgroundColor,
					onChange: function (colorValue) {
						setAttributes({ inputBackgroundColor: colorValue });
					},
					disableAlpha: false
				}),

                // Text Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Text Color' ),
				createElement(ColorPalette, {
					value: attributes.inputTextColor,
					onChange: function (colorValue) {
						setAttributes({ inputTextColor: colorValue });
					},
					disableAlpha: false
				}),

                // Hover Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Hover Color' ),
				createElement(ColorPalette, {
					value: attributes.inputHoverColor,
					onChange: function (colorValue) {
						setAttributes({ inputHoverColor: colorValue });
					},
					disableAlpha: false
				})
            ),

			/////////////////////////////////////////////////////////////////////////////////
            // â€”â€”â€” â€œButton Stylesâ€ panel â€”â€”â€”
			/////////////////////////////////////////////////////////////////////////////////
            createElement( PanelBody, { title: 'Button Styles', initialOpen: false },

				// Text align
				createElement( 'label', { className: 'bandcamp-label' }, 'Text Align' ),
				createElement(ButtonGroup, {style:{marginBottom:'16px'}},
					alignmentOptions.map( function( option ) {
						return createElement(Button,{
							isPressed: attributes.buttonTextAlign === option.value,
							onClick: function() {
								setAttributes({ buttonTextAlign: option.value });
							},
							key: option.value
						},option.label);
					})
				),	

                // Padding
				createElement(BoxControl, {
					label: "Padding",
					values: attributes.buttonPadding,
					onChange: function (newValues) {
						setAttributes({ buttonPadding: newValues });
					},
					showUnit: true,
					__experimentalUnitControl: {
						units: ['px', 'em', 'rem', '%']
					}
				}),

                // Border (width / style / color / radius)
				createElement(BorderControl, {
					label: 'Border',
					value: attributes.buttonBorder,
					onChange: function (newValues) {
						setAttributes({ buttonBorder: newValues });
					},
					enable: ['width', 'style', 'color', 'radius'],
					colors: wp.data.select('core/block-editor').getSettings().colors

				}),
				createElement('br'),

                // Font Family
				createElement(TextControl, {
					label: "Font Family",
					value: attributes.buttonFontFamily,
					onChange: function (newFamily) {
						setAttributes({ buttonFontFamily: newFamily });
					}
				}),
				
                // Font Size
				createElement("div", {style: {display:'grid',gridTemplateColumns:'2fr 1fr'}}, 
					createElement(RangeControl, {
						label: "Font Size",
						value: attributes.buttonFontSize,
						min: 1,
						max: 500,
						onChange: function (value) {
							return setAttributes({ buttonFontSize: value });
						},
						style: {margin:0}
					}),
					createElement(SelectControl, {
						label: "Unit",
						value: attributes.buttonFontUnit,
						options: [
							{ label: 'px', value: 'px' },
							{ label: 'rem',value: 'rem' },
							{ label: 'em', value: 'em' },
							{ label: 'vw', value: 'vw' },
							{ label: 'vh', value: 'vh' }
						],
						onChange: function (value) {
							return setAttributes({ buttonFontUnit: value });
						}
					})
				),

                // Font Style (normal / italic / oblique)
				createElement(SelectControl, {
					label: "Font Style",
					value: attributes.buttonFontStyle,
					options: [
						{ label: 'Normal',  value: 'normal'  },
						{ label: 'Italic',  value: 'italic'  },
						{ label: 'Bold', 	value: 'bold' }
					],
					onChange: function (newStyle) {
						setAttributes({ buttonFontStyle: newStyle });
					}
				}),

                // Line Height
				createElement(RangeControl, {
					label: "Line Height",
					value: attributes.buttonLineHeight,
					onChange: function (newLH) {
						setAttributes({ buttonLineHeight: newLH });
					},
					min: 1,
					max: 5,
					step: 0.1
				}),

                // Background Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Backaground Color' ),
				createElement(ColorPalette, {
					value: attributes.buttonBackgroundColor,
					onChange: function (colorValue) {
						setAttributes({ buttonBackgroundColor: colorValue });
					},
					disableAlpha: false
				}),

                // Text Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Text Color' ),
				createElement(ColorPalette, {
					value: attributes.buttonTextColor,
					onChange: function (colorValue) {
						setAttributes({ buttonTextColor: colorValue });
					},
					disableAlpha: false
				}),

                // Hover Color
				createElement( 'label', { className: 'bandcamp-label' }, 'Hover Color' ),
				createElement(ColorPalette, {
					value: attributes.buttonHoverColor,
					onChange: function (colorValue) {
						setAttributes({ buttonHoverColor: colorValue });
					},
					disableAlpha: false
				})
            )


		);


		return createElement(Fragment,null,
			InspectorPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-form",
					httpMethod: "POST",
					attributes: attributes
				})
			)
		);

    },
    save: function () {
        return null; // Rendered server-side
    }
});
})(window.wp);
