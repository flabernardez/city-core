( function () {
	var el                    = wp.element.createElement;
	var __                    = wp.i18n.__;
	var useSelect             = wp.data.useSelect;
	var useDispatch           = wp.data.useDispatch;
	var TextControl           = wp.components.TextControl;
	var SelectControl         = wp.components.SelectControl;
	var registerPlugin        = wp.plugins.registerPlugin;
	var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;

	function CityPoiCoordinatesPanel() {
		var postType = useSelect( function ( select ) {
			return select( 'core/editor' ).getCurrentPostType();
		} );

		// Get meta from the post edit context.
		var meta = useSelect( function ( select ) {
			var edited = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return edited || {};
		}, [] );

		var editPost = useDispatch( 'core/editor' ).editPost;

		// Only render for Points of Interest.
		if ( postType !== 'poi' ) {
			return null;
		}

		// Handle empty or invalid values - convert to empty string.
		function safeString( value ) {
			if ( value === undefined || value === null || value === '' ) {
				return '';
			}
			if ( typeof value === 'number' ) {
				return String( value );
			}
			var parsed = parseFloat( value );
			if ( isNaN( parsed ) ) {
				return '';
			}
			return String( parsed );
		}

		// Extracted coordinates.
		var lat    = safeString( meta.city_poi_lat );
		var lng    = safeString( meta.city_poi_lng );

		// Manual coordinates (priority).
		var manualLat = safeString( meta.city_poi_manual_lat );
		var manualLng = safeString( meta.city_poi_manual_lng );

		// Maps URL.
		var mapsUrl = meta.city_poi_maps_url || '';

		// Tolerance.
		var tolerance = meta.city_poi_tolerance || 'normal';

		function updateMeta( key, value ) {
			var update = {};
			if ( value === '' ) {
				update[ key ] = '';
				editPost( { meta: update } );
				return;
			}
			// For maps_url, just save as string.
			if ( key === 'city_poi_maps_url' ) {
				update[ key ] = String( value );
				editPost( { meta: update } );
				return;
			}
			// For tolerance, validate allowed values.
			if ( key === 'city_poi_tolerance' ) {
				var allowed = [ 'strict', 'normal', 'amplio' ];
				if ( allowed.indexOf( value ) === -1 ) {
					value = 'normal';
				}
				update[ key ] = String( value );
				editPost( { meta: update } );
				return;
			}
			// For coordinates, validate as numbers.
			var parsed = parseFloat( value );
			if ( isNaN( parsed ) ) {
				return;
			}
			update[ key ] = String( parsed );
			editPost( { meta: update } );
		}

		return el(
			PluginDocumentSettingPanel,
			{
				name:  'city-poi-coordinates',
				title: __( 'Coordinates & Geolocation', 'city-core' ),
				icon:  'location',
			},
			// Manual coordinates (PRIORITY).
			el(
				'div',
				{ style: { marginBottom: '16px' } },
				el(
					'label',
					{ style: { fontWeight: '600', display: 'block', marginBottom: '4px' } },
					__( 'Manual Coordinates', 'city-core' ) + ' (' + __( 'PRIORITY', 'city-core' ) + ')'
				),
				el( TextControl, {
					label:       __( 'Latitude', 'city-core' ),
					type:        'number',
					step:        'any',
					value:       manualLat,
					onChange:    function ( val ) { updateMeta( 'city_poi_manual_lat', val ); },
					placeholder: '41.4036299',
				} ),
				el( TextControl, {
					label:       __( 'Longitude', 'city-core' ),
					type:        'number',
					step:        'any',
					value:       manualLng,
					onChange:    function ( val ) { updateMeta( 'city_poi_manual_lng', val ); },
					placeholder: '2.1743558',
				} ),
				el(
					'p',
					{ style: { fontSize: '12px', color: '#757575', marginTop: '4px' } },
					__( 'If you enter manual coordinates, they will take priority over extracted ones.', 'city-core' )
				)
			),
			// Google Maps URL.
			el(
				'div',
				{ style: { marginBottom: '16px' } },
				el( TextControl, {
					label:       __( 'Google Maps URL', 'city-core' ),
					type:        'url',
					value:       mapsUrl,
					onChange:    function ( val ) { updateMeta( 'city_poi_maps_url', val ); },
					placeholder: 'https://www.google.com/maps/@...',
				} ),
				el(
					'p',
					{ style: { fontSize: '12px', color: '#757575', marginTop: '4px' } },
					__( 'Search the location on Google Maps and copy the full URL.', 'city-core' )
				)
			),
			// Extracted coordinates (read-only display).
			el(
				'div',
				{ style: { marginBottom: '16px', padding: '10px', background: '#f0f0f0', borderRadius: '4px' } },
				el(
					'label',
					{ style: { fontWeight: '600', display: 'block', marginBottom: '4px' } },
					__( 'Extracted Coordinates', 'city-core' )
				),
				el(
					'p',
					{ style: { fontSize: '13px', margin: '0 0 4px' } },
					__( 'Latitude:', 'city-core' ) + ' ' + ( lat || '—' )
				),
				el(
					'p',
					{ style: { fontSize: '13px', margin: '0' } },
					__( 'Longitude:', 'city-core' ) + ' ' + ( lng || '—' )
				),
				el(
					'p',
					{ style: { fontSize: '11px', color: '#757575', marginTop: '4px' } },
					__( 'Extracted from Google Maps URL. Leave empty if using manual coordinates.', 'city-core' )
				)
			),
			// Location tolerance.
			el(
				'div',
				{ style: { marginBottom: '8px' } },
				el(
					'label',
					{ style: { fontWeight: '600', display: 'block', marginBottom: '6px' } },
					__( 'Location Tolerance', 'city-core' )
				),
				el( SelectControl, {
					value:    tolerance,
					onChange: function ( val ) { updateMeta( 'city_poi_tolerance', val ); },
					options: [
						{ label: __( 'Strict (5m)', 'city-core' ), value: 'strict' },
						{ label: __( 'Normal (50m)', 'city-core' ), value: 'normal' },
						{ label: __( 'Wide (500m)', 'city-core' ), value: 'amplio' },
					],
				} )
			)
		);
	}

	registerPlugin( 'city-poi-coordinates', {
		render: CityPoiCoordinatesPanel,
	} );
} )();