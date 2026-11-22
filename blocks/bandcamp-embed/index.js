(function (wp) {
	'use strict';
	var createElement = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var registerBlockType = wp.blocks.registerBlockType;
	var AlignmentToolbar = wp.blockEditor.AlignmentToolbar;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextareaControl = wp.components.TextareaControl;
	var ServerSideRender = wp.serverSideRender;

registerBlockType('mds-bandcamp/bandcamp-embed', {
 	icon: 'album',
    edit: function (props) {

        var attributes = props.attributes;
        var setAttributes = props.setAttributes;

var previewToggle = createElement(Fragment, null,
			createElement(BlockControls, null,
				createElement(AlignmentToolbar, {
					value: attributes.textAlign,
					onChange: function (value) {
						return setAttributes({ textAlign: value });
					}
				})
			)
		);

		var InspectorPanel = createElement(InspectorControls, null,
			createElement(PanelBody, null,
				createElement(SelectControl, {
					label: "Embed Type",
					value: attributes.embedType,
					options: [
						{ label: 'Shortcode', value: 'shortcode' },
						{ label: 'iFrame', value: 'iframe' },
						{ label: 'URL', value: 'url' }
					],
					onChange: function (value) {
						return setAttributes({ embedType: value });
					}
				}),
				createElement(TextareaControl, {
					label: "Embed Code",
					value: attributes.code,
					onChange: function (value) {
						return setAttributes({ code: value });
					}
				})				
			)
		);

		return createElement(Fragment,null,
			previewToggle,
			InspectorPanel,
			createElement('div', useBlockProps(),
				createElement(ServerSideRender, {
					block: "mds-bandcamp/bandcamp-embed",
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
