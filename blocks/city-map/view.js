/**
 * City Map — frontend view (ES module).
 *
 * Mirrors the mancorutas pattern: map container is guaranteed to have
 * non-zero width before Leaflet initialises by forcing inline styles.
 *
 * @since 0.1
 */

/* eslint-disable no-console */

// ── SVG icons ──────────────────────────────────────────────────────────────

const LOCK_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6 22q-.825 0-1.412-.587T4 20V10q0-.825.588-1.412T6 8h1V6q0-2.075 1.463-3.537T12 1t3.538 1.463T17 6v2h1q.825 0 1.413.588T20 10v10q0 .825-.587 1.413T18 22zm7.413-5.587Q14 15.825 14 15t-.587-1.412T12 13t-1.412.588T10 15t.588 1.413T12 17t1.413-.587M9 8h6V6q0-1.25-.875-2.125T12 3t-2.125.875T9 6z"/></svg>`;

const LOCATION_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.3 21.2q-.35-.125-.625-.375Q9.05 19.325 7.8 17.9t-2.087-2.762t-1.275-2.575T4 10.2q0-3.75 2.413-5.975T12 2t5.588 2.225T20 10.2q0 1.125-.437 2.363t-1.275 2.575T16.2 17.9t-2.875 2.925q-.275.25-.625.375t-.7.125t-.7-.125m2.113-9.787Q14 10.825 14 10t-.587-1.412T12 8t-1.412.588T10 10t.588 1.413T12 12t1.413-.587"/></svg>`;

const COMPLETED_SVG = `<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="m10.95 11.175l-.725-.725q-.3-.275-.7-.275t-.7.3t-.3.7t.3.7l1.4 1.425q.3.3.713.3t.712-.3l3.525-3.55q.3-.3.3-.7t-.3-.7t-.7-.3t-.7.3zM12 18l-4.2 1.8q-1 .425-1.9-.162T5 17.975V5q0-.825.588-1.412T7 3h10q.825 0 1.413.588T19 5v12.975q0 1.075-.9 1.663t-1.9.162z"/></svg>`;

const USER_ICON_HTML = `
	<div class="city-user-icon">
		<div class="city-user-wave"></div>
		<div class="city-user-wave delay"></div>
		<div class="city-user-dot">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true">
				<path d="M256 32C167.67 32 96 96.51 96 176c0 128 160 304 160 304s160-176 160-304c0-79.49-71.67-144-160-144m0 224a64 64 0 1 1 64-64 64.07 64.07 0 0 1-64 64"/>
			</svg>
		</div>
	</div>`;

// ── Helpers ────────────────────────────────────────────────────────────────

function escapeHtml( str ) {
	return ( str || '' ).replace( /[&<>"']/g, ( s ) =>
		( { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' } )[ s ]
	);
}

function haversine( lat1, lng1, lat2, lng2 ) {
	const R  = 6371000;
	const φ1 = ( lat1 * Math.PI ) / 180;
	const φ2 = ( lat2 * Math.PI ) / 180;
	const Δφ = ( ( lat2 - lat1 ) * Math.PI ) / 180;
	const Δλ = ( ( lng2 - lng1 ) * Math.PI ) / 180;
	const a  =
		Math.sin( Δφ / 2 ) ** 2 +
		Math.cos( φ1 ) * Math.cos( φ2 ) * Math.sin( Δλ / 2 ) ** 2;
	return R * 2 * Math.atan2( Math.sqrt( a ), Math.sqrt( 1 - a ) );
}

// ── localStorage persistence ───────────────────────────────────────────────

function storageKey( citySlug ) {
	return `city_poi_unlocked_${ citySlug }`;
}

function loadUnlocked( citySlug ) {
	try {
		const raw = localStorage.getItem( storageKey( citySlug ) );
		return raw ? new Set( JSON.parse( raw ) ) : new Set();
	} catch {
		return new Set();
	}
}

function saveUnlocked( citySlug, slugSet ) {
	try {
		localStorage.setItem( storageKey( citySlug ), JSON.stringify( [ ...slugSet ] ) );
	} catch {}
}

function completedStorageKey( citySlug ) {
	return `city_poi_completed_${ citySlug }`;
}

function loadCompleted( citySlug ) {
	try {
		const raw = localStorage.getItem( completedStorageKey( citySlug ) );
		return raw ? new Set( JSON.parse( raw ) ) : new Set();
	} catch {
		return new Set();
	}
}

function saveCompleted( citySlug, slugSet ) {
	try {
		localStorage.setItem( completedStorageKey( citySlug ), JSON.stringify( [ ...slugSet ] ) );
	} catch {}
}

// ── Force full-width before Leaflet initialises ─────────────────────────────

/**
 * Breaks the map out of the theme's constrained layout.
 *
 * @param {Element} mapEl - The #city-map element.
 */
function forceBlockFullWidth( mapEl ) {
	if ( ! mapEl ) return;

	var wrapper = mapEl.parentElement;
	if ( ! wrapper ) return;

	wrapper.style.setProperty( 'position', 'fixed', 'important' );
	wrapper.style.setProperty( 'top', '0', 'important' );
	wrapper.style.setProperty( 'left', '0', 'important' );
	wrapper.style.setProperty( 'width', '100vw', 'important' );
	wrapper.style.setProperty( 'height', '100vh', 'important' );
	wrapper.style.setProperty( 'z-index', '1', 'important' );

	mapEl.style.width = '100%';
	mapEl.style.height = '100vh';
}

// ── Leaflet icon factories ─────────────────────────────────────────────────

function lockedIcon( color ) {
	const style = color ? ` style="color:${ color }"` : '';
	return L.divIcon( {
		className:   '',
		html:        `<div class="city-poi-marker city-poi-marker--locked"${ style }>${ LOCK_SVG }</div>`,
		iconSize:    [ 36, 44 ],
		iconAnchor:  [ 18, 44 ],
		popupAnchor: [ 0, -46 ],
	} );
}

function unlockedIcon( color, categorySvg ) {
	const style = color ? ` style="color:${ color }"` : '';
	const svg   = categorySvg || LOCATION_SVG;
	return L.divIcon( {
		className:   '',
		html:        `<div class="city-poi-marker city-poi-marker--unlocked"${ style }>${ svg }</div>`,
		iconSize:    [ 36, 44 ],
		iconAnchor:  [ 18, 44 ],
		popupAnchor: [ 0, -46 ],
	} );
}

function completedIcon( color ) {
	const style = color ? ` style="color:${ color }"` : '';
	return L.divIcon( {
		className:   '',
		html:        `<div class="city-poi-marker city-poi-marker--completed"${ style }>${ COMPLETED_SVG }</div>`,
		iconSize:    [ 36, 44 ],
		iconAnchor:  [ 18, 44 ],
		popupAnchor: [ 0, -46 ],
	} );
}

// ── Map initialisation ─────────────────────────────────────────────────────

async function initCityMap() {
	// ── Read config from inline JSON ──────────────────────────────────────────
	const jsonTag = document.getElementById( 'city-map-data' );
	if ( ! jsonTag ) {
		console.warn( '[city-map] #city-map-data not found' );
		return;
	}

	let config;
	try {
		config = JSON.parse( jsonTag.textContent );
	} catch ( err ) {
		console.error( '[city-map] Failed to parse map data:', err );
		return;
	}

	const citySlug  = config.citySlug    || '';
	const poisData = config.pois         || [];
	const i18n     = config.i18n         || {};
	const viewMore  = i18n.viewMore      || 'View more';
	const lockedMsg = i18n.lockedMsg     || 'Get closer to unlock this point of interest.';

	// ── Wait for Leaflet to be available ─────────────────────────────────────
	if ( typeof L === 'undefined' ) {
		setTimeout( initCityMap, 50 );
		return;
	}

	const mapEl = document.getElementById( 'city-map' );
	if ( ! mapEl ) {
		console.warn( '[city-map] #city-map not found' );
		return;
	}

	// ── CRITICAL: force width BEFORE L.map() reads offsetWidth ───────────────
	forceBlockFullWidth( mapEl );

	// ── ?poi=slug deep-link ─────────────────────────────────────────────────
	const params  = new URL( window.location.href ).searchParams;
	const deepPoi = ( params.get( 'poi' ) || '' ).trim().toLowerCase();

	// ── Load persisted unlock state ───────────────────────────────────────────
	const unlockedSlugs  = loadUnlocked( citySlug );
	const completedSlugs = loadCompleted( citySlug );

	// ── Create Leaflet map ───────────────────────────────────────────────────
	const map = L.map( 'city-map', { zoomControl: false } );

	L.tileLayer(
		'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
		{ maxZoom: 19, attribution: '&copy; OpenStreetMap contributors &copy; CARTO' }
	).addTo( map );

	// ── Popup HTML helpers ────────────────────────────────────────────────────
	function getMarkerIcon( poi, isUnlocked, isCompleted ) {
		if ( isCompleted ) {
			return completedIcon( poi.color );
		}
		if ( isUnlocked ) {
			return unlockedIcon( poi.color, poi.categorySvg );
		}
		return lockedIcon( poi.color );
	}

	function unlockedPopup( poi ) {
		return `<div class="city-popup"><div class="city-popup-inner">
			<div class="city-popup-title">${ escapeHtml( poi.name ) }</div>
			<a class="city-popup-link" href="${ escapeHtml( poi.url ) }">${ escapeHtml( viewMore ) }</a>
		</div></div>`;
	}

	function lockedPopup( poi ) {
		return `<div class="city-popup city-popup--locked"><div class="city-popup-inner">
			<div class="city-poi-marker city-poi-marker--locked city-poi-marker--inline">${ LOCK_SVG }</div>
			<div class="city-popup-title">${ escapeHtml( poi.name ) }</div>
			<p class="city-popup-locked-msg">${ escapeHtml( lockedMsg ) }</p>
		</div></div>`;
	}

	function completedPopup( poi ) {
		return `<div class="city-popup city-popup--completed"><div class="city-popup-inner">
			<div class="city-poi-marker city-poi-marker--completed city-poi-marker--inline">${ COMPLETED_SVG }</div>
			<div class="city-popup-title">${ escapeHtml( poi.name ) }</div>
			<p class="city-popup-completed-msg">Completed!</p>
			<a class="city-popup-link" href="${ escapeHtml( poi.url ) }">${ escapeHtml( viewMore ) }</a>
		</div></div>`;
	}

	function getPopupContent( poi, isUnlocked, isCompleted ) {
		if ( isCompleted ) {
			return completedPopup( poi );
		}
		if ( isUnlocked ) {
			return unlockedPopup( poi );
		}
		return lockedPopup( poi );
	}

	// ── Create markers ─────────────────────────────────────────────────────
	const markers = {};

	for ( const poi of poisData ) {
		const isUnlocked  = unlockedSlugs.has( poi.slug );
		const isCompleted = completedSlugs.has( poi.slug );
		const marker      = L.marker(
			[ poi.lat, poi.lng ],
			{ icon: getMarkerIcon( poi, isUnlocked, isCompleted ) }
		).addTo( map );

		marker.bindPopup(
			getPopupContent( poi, isUnlocked, isCompleted ),
			{ closeButton: false, offset: L.point( 0, -10 ) }
		);

		markers[ poi.slug ] = { marker, poi, unlocked: isUnlocked, completed: isCompleted };

		if ( deepPoi && poi.slug === deepPoi && ( isUnlocked || isCompleted ) ) {
			marker.openPopup();
		}
	}

	// ── Fit all markers ──────────────────────────────────────────────────────
	const allMarkers = Object.values( markers ).map( ( m ) => m.marker );

	function fitAll() {
		forceBlockFullWidth( mapEl );
		map.invalidateSize();
		if ( deepPoi && markers[ deepPoi ] ) {
			map.setView( markers[ deepPoi ].marker.getLatLng(), 16 );
		} else if ( allMarkers.length ) {
			map.fitBounds( L.featureGroup( allMarkers ).getBounds(), { padding: [ 50, 50 ] } );
		} else {
			map.setView( [ 0, 0 ], 2 );
		}
	}

	fitAll();

	// Invalidate after CSS has painted.
	setTimeout( () => { forceBlockFullWidth( mapEl ); map.invalidateSize(); }, 250 );
	window.addEventListener( 'load', () => { setTimeout( () => { map.invalidateSize(); fitAll(); }, 300 ); } );

	// ── Proximity unlock/lock ───────────────────────────────────────────────
	function checkProximity( userLat, userLng ) {
		let anyChange = false;

		for ( const slug in markers ) {
			const item      = markers[ slug ];

			// Completed POIs are permanent — never re-lock them.
			if ( item.completed ) {
				continue;
			}

			const dist      = haversine( userLat, userLng, item.poi.lat, item.poi.lng );
			const tolerance = item.poi.tolerance || 50;

			if ( item.unlocked ) {
				if ( dist > tolerance ) {
					item.unlocked = false;
					unlockedSlugs.delete( slug );
					anyChange = true;

					item.marker.setIcon( lockedIcon( item.poi.color ) );
					item.marker.setPopupContent( lockedPopup( item.poi ) );
					item.marker.closePopup();
				}
			} else {
				if ( dist <= tolerance ) {
					item.unlocked = true;
					unlockedSlugs.add( slug );
					anyChange = true;

					item.marker.setIcon( unlockedIcon( item.poi.color, item.poi.categorySvg ) );
					item.marker.setPopupContent( unlockedPopup( item.poi ) );
					item.marker.openPopup();
				}
			}
		}

		if ( anyChange ) {
			saveUnlocked( citySlug, unlockedSlugs );
		}
	}

	// ── User location marker ──────────────────────────────────────────────────
	const userLeafletIcon = L.divIcon( {
		className:  '',
		html:        USER_ICON_HTML,
		iconSize:   [ 26, 26 ],
		iconAnchor: [ 13, 13 ],
	} );

	let userMarker        = null;
	let warmupWatchId     = null;
	let continuousWatchId = null;

	function stopWarmup() {
		if ( warmupWatchId !== null ) {
			navigator.geolocation.clearWatch( warmupWatchId );
			warmupWatchId = null;
		}
	}

	function stopContinuous() {
		if ( continuousWatchId !== null ) {
			navigator.geolocation.clearWatch( continuousWatchId );
			continuousWatchId = null;
		}
	}

	function fitWithUser() {
		map.invalidateSize();
		const items = allMarkers.concat( userMarker ? [ userMarker ] : [] );
		if ( items.length ) {
			map.fitBounds( L.featureGroup( items ).getBounds(), { padding: [ 80, 80 ] } );
		}
	}

	function updateUserMarker( lat, lng, shouldFit, accuracy ) {
		if ( ! userMarker ) {
			userMarker = L.marker( [ lat, lng ], { icon: userLeafletIcon } ).addTo( map );
		} else {
			userMarker.setLatLng( [ lat, lng ] );
		}

		checkProximity( lat, lng );

		if ( shouldFit ) fitWithUser();
	}

	// ── Continuous tracking (always on after init) ──────────────────────────
	function startContinuousTracking() {
		if ( ! ( 'geolocation' in navigator ) ) return;
		stopContinuous();

		continuousWatchId = navigator.geolocation.watchPosition(
			function( p ) {
				updateUserMarker(
					p.coords.latitude,
					p.coords.longitude,
					false,
					p.coords.accuracy
				);
			},
			function( err ) {
				console.warn( '[city-map] Geolocation error:', err.message );
			},
			{ enableHighAccuracy: false, maximumAge: 3000, timeout: 10000 }
		);
	}

	// ── High-accuracy warmup (on demand) ────────────────────────────────────
	function startWarmup( opts ) {
		if ( ! ( 'geolocation' in navigator ) ) return;
		stopWarmup();

		const startTime = Date.now();
		let firstFix    = false;

		warmupWatchId = navigator.geolocation.watchPosition(
			function( p ) {
				const lat = p.coords.latitude;
				const lng = p.coords.longitude;
				const acc = p.coords.accuracy;

				if ( ! firstFix ) {
					firstFix = true;
					updateUserMarker( lat, lng, opts.fitOnStart, acc );
				} else {
					updateUserMarker( lat, lng, false, acc );
				}

				if ( opts.onAccuracyWarning && acc > opts.targetAccuracyM * 2 ) {
					opts.onAccuracyWarning( acc );
				}

				const timeUp          = Date.now() - startTime > opts.maxTimeMs;
				const accurateEnough  = acc <= opts.targetAccuracyM;

				if ( accurateEnough || timeUp ) {
					stopWarmup();
					startContinuousTracking();
					if ( opts.fitOnEnd ) fitWithUser();
				}
			},
			function() {
				stopWarmup();
				startContinuousTracking();
				if ( opts.fitOnEnd ) fitWithUser();
			},
			{ enableHighAccuracy: true, maximumAge: 0, timeout: opts.maxTimeMs }
		);

		setTimeout( function() {
			if ( warmupWatchId !== null ) {
				stopWarmup();
				startContinuousTracking();
				if ( opts.fitOnEnd ) fitWithUser();
			}
		}, opts.maxTimeMs + 250 );
	}

	// Initial warmup — then switches to continuous tracking.
	startWarmup( { maxTimeMs: 25000, targetAccuracyM: 20, fitOnStart: true, fitOnEnd: false } );

	// ── Center-user button ─────────────────────────────────────────────────
	const centerBtn = document.getElementById( 'city-center-user-btn' );
	function showAccuracyWarning() {
		// GPS accuracy is low — user may need to be closer to the POI.
	}
		if ( centerBtn ) {
		centerBtn.addEventListener( 'click', function( e ) {
			e.preventDefault();
			centerBtn.classList.add( 'tap' );
			setTimeout( function() { centerBtn.classList.remove( 'tap' ); }, 120 );
			stopContinuous();
			startWarmup( {
				maxTimeMs: 20000,
				targetAccuracyM: 15,
				fitOnStart: true,
				fitOnEnd: true,
				onAccuracyWarning: showAccuracyWarning,
			} );
		} );
	}

	// ── Listen for quiz-completed events (from quiz block) ───────────────
	window.addEventListener( 'cityPoiCompleted', function( e ) {
		var slug    = e.detail.poiSlug;
		var item    = markers[ slug ];
		if ( ! item ) return;

		// Mark as completed (never re-locks).
		item.completed = true;
		item.unlocked  = true;
		completedSlugs.add( slug );
		unlockedSlugs.add( slug );
		saveCompleted( citySlug, completedSlugs );
		saveUnlocked( citySlug, unlockedSlugs );

		// Update marker icon and popup.
		item.marker.setIcon( completedIcon( item.poi.color ) );
		item.marker.setPopupContent( completedPopup( item.poi ) );
		item.marker.openPopup();
	} );
}

// ── Boot ───────────────────────────────────────────────────────────────────

/**
 * Defers map init until after the first paint, so CSS has been applied.
 *
 * @param {Function} fn - Function to call after first paint.
 */
function afterFirstPaint( fn ) {
	if ( document.readyState === 'complete' ) {
		requestAnimationFrame( function () {
			setTimeout( fn, 0 );
		} );
	} else {
		window.addEventListener( 'load', function () {
			requestAnimationFrame( function () {
				setTimeout( fn, 0 );
			} );
		} );
	}
}

afterFirstPaint( initCityMap );
