( function() {
	var el = wp.element.createElement;
	var BlockControls = wp.blockEditor.BlockControls;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var serverSideRender = wp.serverSideRender;
	var TextControl = wp.components.TextControl;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;

	wp.blocks.registerBlockType( 'city-core/quiz', {
		edit: function( props ) {
			var attributes = props.attributes;

			// Get all POIs for the select dropdown.
			var poiOptions = [ { value: 0, label: '— Select a POI —' } ];
			var selectedPoiId = attributes.poiId || 0;

			return [
				el( InspectorControls, { key: 'inspector' },
					el( PanelBody, { title: 'Quiz Settings' },
						el( 'p', { style: { fontSize: '12px', color: '#666' } },
							'Select the Point of Interest this quiz belongs to.'
						)
					)
				),
				el( 'div', { key: 'placeholder', className: 'city-quiz-placeholder', style: { padding: '20px', background: '#f0f0f0', textAlign: 'center' } },
					el( 'span', { style: { fontSize: '48px' } }, '❓' ),
					el( 'p', null, 'POI Quiz Block' ),
					el( 'p', { style: { fontSize: '12px', color: '#666' } },
						'This block will display a quiz question with 3 options. The user must answer correctly to mark the POI as completed.'
					)
				)
			];
		},
		save: function() {
			// Dynamic block - rendered on frontend.
			return null;
		},
	} );
} )();
