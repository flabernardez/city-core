( function () {
	var el  = wp.element.createElement;
	var __  = wp.i18n.__;

	wp.blocks.registerBlockType( 'city-core/map', {
		edit: function () {
			return el(
				'div',
				{
					style: {
						padding:       '40px 20px',
						background:    '#e8f4f0',
						border:        '2px dashed #00624D',
						borderRadius:  '8px',
						textAlign:     'center',
						color:         '#063b2f',
					}
				},
				el( 'span', {
					style: { fontSize: '32px', display: 'block', marginBottom: '8px' },
					'aria-hidden': 'true'
				}, '🗺️' ),
				el( 'strong', null, __( 'City Map', 'city-core' ) ),
				el(
					'p',
					{ style: { margin: '6px 0 0', fontSize: '13px', opacity: '.65' } },
					__( 'The map is rendered on the frontend with the POIs for this city.', 'city-core' )
				)
			);
		},

		// Dynamic block — PHP renders the frontend output.
		save: function () {
			return null;
		}
	} );
} )();
