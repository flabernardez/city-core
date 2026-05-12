( function ( $ ) {
	$( function () {
		var palette = ( window.cityPoiCategoryAdmin && window.cityPoiCategoryAdmin.themePalette ) || [];
		$( '.city-color-picker' ).wpColorPicker({
			palettes: palette.length ? palette : true
		});
	} );
} )( jQuery );
