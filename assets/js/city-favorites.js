/**
 * City Core — Favorites toggle (global, runs on every page).
 *
 * Handles .add-fav / .added-fav button pairs that live outside the quiz block.
 * Filters POI listing blocks on /tus-sitios/ based on localStorage state.
 *
 * @since 0.4
 */
( function() {
	'use strict';

	// ── localStorage helpers ─────────────────────────────────────────────────

	function storageGet( key ) {
		try {
			var raw = localStorage.getItem( key );
			return raw ? JSON.parse( raw ) : [];
		} catch ( e ) {
			return [];
		}
	}

	// ── Extract POI slug from a list item element ─────────────────────────────

	function getSlugFromLi( li ) {
		var a = li.querySelector( 'a[href*="/poi/"]' );
		if ( ! a ) return '';
		var m = a.getAttribute( 'href' ).match( /\/poi\/([^\/]+)\/?/ );
		return m ? m[ 1 ] : '';
	}

	// ── Collect slugs from ALL matching keys in localStorage ─────────────────
	//
	// Handles both singular and plural key formats for compatibility:
	//   favorites (old) and favorite (new)

	function getAllSlugs( prefix ) {
		var all = [];
		var prefixes = [ prefix ];
		if ( prefix.indexOf( 'favorite' ) !== -1 && prefix.indexOf( 'favorite_' ) === -1 ) {
			// "city_poi_favorites_" → also check "city_poi_favorite_"
			prefixes.push( prefix.replace( 'favorites_', 'favorite_' ) );
		}
		prefixes.forEach( function( p ) {
			for ( var key in localStorage ) {
				if ( key.indexOf( p ) === 0 ) {
					var val = storageGet( key );
					if ( Array.isArray( val ) ) {
						for ( var j = 0; j < val.length; j++ ) {
							if ( all.indexOf( val[ j ] ) === -1 ) {
								all.push( val[ j ] );
							}
						}
					}
				}
			}
		} );
		return all;
	}

	// ── Init on tus-sitios page ─────────────────────────────────────────────
	//
	// Any .wp-block-query with a class starting with "city-" is a POI listing
	// block. Example classes:
	//   city-completed → city_poi_completed_{citySlug}
	//   city-favorite  → city_poi_favorites_{citySlug}
	// Add more types here as needed — no other JS changes required.

	function initListing() {
		// Detect all POI listing blocks (any .wp-block-query with a "city-" class).
		var blocks = Array.prototype.slice.call(
			document.querySelectorAll( '.wp-block-query[class*="city-"]' )
		);
		if ( ! blocks.length ) return;

		// Detect citySlug from page context.
		var citySlug = '';
		if ( window.cityPoiData && window.cityPoiData.citySlug ) {
			citySlug = window.cityPoiData.citySlug;
		} else {
			var params = new URL( window.location.href ).searchParams;
			var p = params.get( 'city' );
			if ( p ) citySlug = p.trim();
		}

		console.log( '[city-favorites] initListing — blocks:', blocks.length, 'citySlug:', citySlug );

		blocks.forEach( function( block ) {
			// Derive key prefix from class, e.g. "city-completed" → "city_poi_completed_"
			var prefix = null;
			var blockClasses = block.className.split( /\s+/ );
			for ( var i = 0; i < blockClasses.length; i++ ) {
				if ( blockClasses[ i ].indexOf( 'city-' ) === 0 ) {
					// Normalize: always use singular form for the key.
					var type = blockClasses[ i ].replace( 'city-', '' );
					if ( type === 'favorites' ) type = 'favorite';
					prefix = 'city_poi_' + type + '_';
					break;
				}
			}
			if ( ! prefix ) return;

			console.log( '[city-favorites] block class prefix:', prefix, 'citySlug:', citySlug );

			// With citySlug → exact key;  without → aggregate all cities.
			var slugs = citySlug
				? storageGet( prefix + citySlug )
				: getAllSlugs( prefix );

			console.log( '[city-favorites] slugs to show:', slugs );

			var items = block.querySelectorAll( 'li.wp-block-post' );
			items.forEach( function( li ) {
				var slug = getSlugFromLi( li );
				if ( ! slug ) return;
				var show = slugs.indexOf( slug ) !== -1;
				console.log( '[city-favorites] item:', slug, '→ display:', show ? 'show' : 'hide' );
				li.style.display = show ? '' : 'none';
			} );
		} );
	}

	// ── Favorite buttons on single POI pages ──────────────────────────────────

	function favKey( citySlug ) {
		return 'city_poi_favorite_' + ( citySlug || 'default' );
	}

	function loadFavs( citySlug ) {
		return storageGet( favKey( citySlug ) );
	}

	function saveFavs( citySlug, arr ) {
		try {
			localStorage.setItem( favKey( citySlug ), JSON.stringify( arr ) );
		} catch ( e ) {}
	}

	function getPoiSlugFromUrl() {
		var params = new URL( window.location.href ).searchParams;
		var p = params.get( 'poi' );
		if ( p ) return p.trim().toLowerCase();
		var m = window.location.pathname.match( /\/poi\/([^\/]+)\/?/ );
		if ( m ) return m[ 1 ].toLowerCase();
		return '';
	}

	function syncFavButtons( addBtn, addedBtn, poiSlug, citySlug, nonce ) {
		if ( ! addBtn || ! addedBtn ) return;

		var favs  = loadFavs( citySlug );
		var isFav = favs.indexOf( poiSlug ) !== -1;

		console.log( '[city-favorites] syncFavButtons — poiSlug:', poiSlug, 'citySlug:', citySlug, 'isFav:', isFav );

		addBtn.style.display   = isFav ? 'none' : '';
		addedBtn.style.display = isFav ? '' : 'none';

		addBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			var f = loadFavs( citySlug );
			if ( f.indexOf( poiSlug ) === -1 ) f.push( poiSlug );
			saveFavs( citySlug, f );
			addBtn.style.display = 'none';
			addedBtn.style.display = '';
			syncToServer( 'city_toggle_favorite', nonce, poiSlug );
		} );

		addedBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			saveFavs( citySlug, loadFavs( citySlug ).filter( function( s ) { return s !== poiSlug; } ) );
			addedBtn.style.display = 'none';
			addBtn.style.display = '';
			syncToServer( 'city_toggle_favorite', nonce, poiSlug );
		} );
	}

	function syncToServer( action, nonce, poiId ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', cityCoreAjax.url, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.send( 'action=' + action + '&nonce=' + encodeURIComponent( nonce ) + '&poi_id=' + poiId );
	}

	function initPoiPage() {
		var poiData   = window.cityPoiData || {};
		var poiSlug   = poiData.slug || getPoiSlugFromUrl();
		var citySlug  = poiData.citySlug || '';
		if ( ! poiSlug ) return;

		console.log( '[city-favorites] initPoiPage — poiSlug:', poiSlug, 'citySlug:', citySlug );

		var nonce    = typeof cityCoreAjax !== 'undefined' ? cityCoreAjax.nonce : '';
		var addBtns  = document.querySelectorAll( '.add-fav' );
		var addedBtns = document.querySelectorAll( '.added-fav' );

		if ( addBtns.length === addedBtns.length && addBtns.length > 0 ) {
			for ( var i = 0; i < addBtns.length; i++ ) {
				syncFavButtons( addBtns[ i ], addedBtns[ i ], poiSlug, citySlug, nonce );
			}
		}
	}

	// ── Boot ─────────────────────────────────────────────────────────────────

	function init() {
		console.log( '[city-favorites] init — path:', window.location.pathname );
		initListing();
		initPoiPage();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

} )();
