/**
 * HW WP FAQ – Block Editor Script
 *
 * Uses the globally available wp.* namespace (no build step required).
 * WP enqueues the correct dependencies thanks to editor.asset.php.
 */
( function () {
	'use strict';

	var registerBlockType  = wp.blocks.registerBlockType;
	var el                 = wp.element.createElement;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var InspectorControls  = wp.blockEditor.InspectorControls;
	var PanelBody          = wp.components.PanelBody;
	var TextControl        = wp.components.TextControl;
	var __                 = wp.i18n.__;

	registerBlockType( 'hwwpfaq/faq', {
		/**
		 * Editor representation.
		 * The actual rendered output comes from render.php (server-side).
		 */
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'hwwpfaq-editor-preview' } );
			var category   = props.attributes.category;

			return el(
				'div',
				blockProps,

				// Sidebar panel --------------------------------------------------
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{
							title: __( 'FAQ Settings', 'hwwpfaq' ),
							initialOpen: true,
						},
						el( TextControl, {
							label:    __( 'Category', 'hwwpfaq' ),
							value:    category,
							onChange: function ( val ) {
								props.setAttributes( { category: val } );
							},
							help: __(
								'Filter by category. Leave empty to display all FAQ entries.',
								'hwwpfaq'
							),
						} )
					)
				),

				// Editor placeholder preview -------------------------------------
				el(
					'div',
					{ className: 'hwwpfaq-editor-placeholder' },
					el( 'span', { className: 'dashicons dashicons-editor-help' } ),
					el(
						'p',
						null,
						category
							? __( 'FAQ Block – Category: ', 'hwwpfaq' ) + '"' + category + '"'
							: __( 'FAQ Block – All categories', 'hwwpfaq' )
					)
				)
			);
		},

		/**
		 * No static save – output is fully server-side rendered.
		 */
		save: function () {
			return null;
		},
	} );
} )();
