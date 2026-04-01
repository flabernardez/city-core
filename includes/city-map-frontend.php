<?php
/**
 * Map frontend: template override, Leaflet assets, and inline JS.
 *
 * When a singular "map" post is viewed, this file:
 *  1. Overrides the theme template with a full-screen Leaflet map.
 *  2. Enqueues Leaflet CSS/JS from the unpkg CDN.
 *  3. Outputs inline CSS and JS (with localised data) in the footer.
 *
 * The JS fetches POIs via the REST endpoint registered in
 * city-map-geojson.php and renders them as circle markers.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Template override
// -------------------------------------------------------------------------

/**
 * Loads the plugin's map template for singular map posts.
 *
 * @since 0.1
 *
 * @param string $template Default template path.
 * @return string Filtered template path.
 */
function city_map_template_include( $template ) {
	if ( is_singular( 'map' ) ) {
		$plugin_template = CITY_CORE_DIR . 'templates/single-map.php';
		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}
	}
	return $template;
}
add_filter( 'template_include', 'city_map_template_include' );

// -------------------------------------------------------------------------
// Enqueue Leaflet assets
// -------------------------------------------------------------------------

/**
 * Enqueues Leaflet CSS and JS on singular map pages.
 *
 * @since 0.1
 */
function city_map_enqueue_assets() {
	if ( ! is_singular( 'map' ) ) {
		return;
	}

	wp_enqueue_style(
		'leaflet',
		'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
		array(),
		'1.9.4'
	);

	wp_enqueue_script(
		'leaflet',
		'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
		array(),
		'1.9.4',
		true
	);
}
add_action( 'wp_enqueue_scripts', 'city_map_enqueue_assets' );

// -------------------------------------------------------------------------
// Inline CSS
// -------------------------------------------------------------------------

/**
 * Outputs the map CSS in the <head>.
 *
 * @since 0.1
 */
function city_map_inline_css() {
	if ( ! is_singular( 'map' ) ) {
		return;
	}
	?>
	<style id="city-map-css">
		/* Map wrapper */
		#city-map-wrapper{
			position: relative;
			width: 100%;
			height: calc(100svh - 80px);
			min-height: 400px;
		}
		#city-map{ width:100%; height:100%; }

		/* Voyager tile filter */
		#city-map .leaflet-tile{
			filter: saturate(0.65) hue-rotate(12deg) brightness(1.02) contrast(1.06);
		}

		/* Center-user button */
		.city-map-button{
			position: absolute;
			left: 50%;
			bottom: 40px;
			transform: translateX(-50%);
			z-index: 3000;
			display: flex;
			flex-direction: column;
			align-items: center;
		}

		#city-center-user-btn{
			display: inline-flex;
			align-items: center;
			gap: 8px;
			white-space: nowrap;
			background: rgba(255,255,255,.95);
			color: #063b2f;
			border-radius: 999px;
			border: 2px solid #00624D;
			box-shadow: 0 12px 24px rgba(0,0,0,.25);
			backdrop-filter: blur(8px);
			padding: 12px 18px;
			font-weight: 400;
			transition: transform .12s ease, box-shadow .12s ease;
			cursor: pointer;
			text-decoration: none;
		}
		#city-center-user-btn:active,
		#city-center-user-btn.tap{
			transform: scale(.96);
			box-shadow: 0 6px 14px rgba(0,0,0,.22);
		}

		/* User location icon */
		.city-user-icon{ position: relative; width: 26px; height: 26px; }
		.city-user-wave{
			position: absolute;
			top: 50%;
			left: 50%;
			width: 26px;
			height: 26px;
			border-radius: 50%;
			background: rgba(0,98,77,.22);
			transform: translate(-50%, -50%) scale(1);
			z-index: 1;
			animation: cityWave 2.8s ease-out infinite;
		}
		.city-user-wave.delay{ animation-delay: 1.4s; }

		@keyframes cityWave{
			0%{ transform: translate(-50%,-50%) scale(1); opacity:.55; }
			70%{ opacity:.15; }
			100%{ transform: translate(-50%,-50%) scale(3); opacity:0; }
		}

		.city-user-dot{
			width: 26px;
			height: 26px;
			border-radius: 50%;
			background: rgba(255,255,255,.95);
			border: 2px solid #00624D;
			box-shadow: 0 4px 10px rgba(0,0,0,.25);
			display: flex;
			align-items: center;
			justify-content: center;
			position: relative;
			z-index: 3;
		}
		.city-user-dot svg{ width:14px; height:14px; fill:#222; }

		/* Popup styling */
		.city-popup{ max-width: 220px; }
		.city-popup-inner{ padding: 8px 10px 10px; }
		.city-popup-title{
			font-weight: 700;
			font-size: 14px;
			line-height: 1.2;
			color: #063b2f;
			text-align: center;
			margin: 0 0 4px;
		}
		.city-popup-link{
			display: block;
			text-align: center;
			font-size: 12px;
			color: #00624D;
			text-decoration: underline;
		}

		#city-map .leaflet-popup-content{ margin: 0; }
		#city-map .leaflet-popup-content-wrapper{ padding: 6px 8px; }
	</style>
	<?php
}
add_action( 'wp_head', 'city_map_inline_css' );

// -------------------------------------------------------------------------
// Inline JS (footer)
// -------------------------------------------------------------------------

/**
 * Outputs the map JavaScript in the footer.
 *
 * @since 0.1
 */
function city_map_inline_js() {
	if ( ! is_singular( 'map' ) ) {
		return;
	}

	$post     = get_post();
	$slug     = $post->post_name;
	$rest_url = rest_url( 'city-core/v1/pois' );
	$nonce    = wp_create_nonce( 'wp_rest' );

	// Translatable strings for the JS layer.
	$i18n = array(
		'updatePosition' => __( 'Update position', 'city-core' ),
		'viewMore'       => __( 'View more', 'city-core' ),
	);
	?>
	<script id="city-map-js">
	(function(){
		"use strict";

		var CITY_MAP_DATA = <?php echo wp_json_encode( array(
			'restUrl'  => $rest_url,
			'citySlug' => $slug,
			'nonce'    => $nonce,
			'i18n'     => $i18n,
		) ); ?>;

		async function initCityMap(){
			var data    = CITY_MAP_DATA;
			var url     = data.restUrl + "?city=" + encodeURIComponent( data.citySlug );
			var i18n    = data.i18n;

			/* --- Query param for single-POI highlight --- */
			var params     = new URL( window.location.href ).searchParams;
			var poiSlugRaw = params.get( "poi" );
			var poiSlug    = poiSlugRaw ? poiSlugRaw.trim().toLowerCase() : null;

			/* --- Create map --- */
			var map = L.map( "city-map", { zoomControl: false } );

			L.tileLayer(
				"https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png",
				{ maxZoom: 19, attribution: "&copy; OpenStreetMap contributors &copy; CARTO" }
			).addTo( map );

			/* --- Fetch GeoJSON --- */
			var geojson;
			try {
				var res = await fetch( url, {
					headers: { "X-WP-Nonce": data.nonce }
				});
				geojson = await res.json();
			} catch(e) { return; }

			var feats = geojson.features || [];
			if ( !feats.length ) return;

			/* --- Helpers --- */
			var POPUP_OFFSET = L.point( 0, -14 );

			function escapeHtml( str ) {
				return ( str || "" ).replace( /[&<>"']/g, function(s) {
					return ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" })[s];
				});
			}

			function popupHtml( name, link ) {
				var safeName = escapeHtml( ( name || "" ).trim() );
				var safeLink = escapeHtml( ( link || "" ).trim() );
				var html = '<div class="city-popup"><div class="city-popup-inner">';
				html += '<div class="city-popup-title">' + safeName + '</div>';
				if ( safeLink ) {
					html += '<a class="city-popup-link" href="' + safeLink + '">' + escapeHtml( i18n.viewMore ) + '</a>';
				}
				html += '</div></div>';
				return html;
			}

			/* --- Render markers --- */
			var hasHighlight = !!poiSlug;
			var markers      = [];
			var activeMarker = null;

			for ( var i = 0; i < feats.length; i++ ) {
				var f    = feats[i];
				var lo   = f.geometry.coordinates[0];
				var la   = f.geometry.coordinates[1];
				var slug = ( f.properties.slug || "" ).trim().toLowerCase();
				var name = f.properties.name || "";
				var link = f.properties.url  || "";

				var isActive = hasHighlight && slug === poiSlug;
				var isDimmed = hasHighlight && !isActive;

				var m = L.circleMarker( [la, lo], {
					radius:      isActive ? 12 : 8,
					fillColor:   "#00624D",
					color:       "#FFFFFF",
					weight:      2,
					fillOpacity: isDimmed ? 0.22 : 0.95,
					opacity:     isDimmed ? 0.35 : 1
				}).addTo( map );

				if ( isDimmed ) {
					m.options.interactive = false;
					m.off();
				} else {
					m.bindPopup( popupHtml( name, link ), {
						offset: POPUP_OFFSET,
						closeButton: false
					});
				}

				markers.push( m );

				if ( isActive ) {
					activeMarker = m;
				}
			}

			/* --- Fit bounds --- */
			var group = L.featureGroup( markers );

			function fitAll() {
				map.invalidateSize();
				if ( activeMarker ) {
					map.setView( activeMarker.getLatLng(), 16 );
				} else {
					map.fitBounds( group.getBounds(), { padding: [50, 50] } );
				}
			}

			fitAll();

			if ( activeMarker && activeMarker.getPopup() ) {
				activeMarker.openPopup();
			}

			setTimeout( function(){ map.invalidateSize(); fitAll(); }, 250 );
			window.addEventListener( "load", function(){ map.invalidateSize(); fitAll(); }, { once: true } );

			/* ===== Geolocation ===== */
			var userIcon = L.divIcon({
				className: "",
				html:
					'<div class="city-user-icon">' +
						'<div class="city-user-wave"></div>' +
						'<div class="city-user-wave delay"></div>' +
						'<div class="city-user-dot">' +
							'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" aria-hidden="true">' +
								'<path d="M256 32C167.67 32 96 96.51 96 176c0 128 160 304 160 304s160-176 160-304c0-79.49-71.67-144-160-144m0 224a64 64 0 1 1 64-64 64.07 64.07 0 0 1-64 64"/>' +
							'</svg>' +
						'</div>' +
					'</div>',
				iconSize:   [26, 26],
				iconAnchor: [13, 13]
			});

			var userMarker   = null;
			var warmupWatchId = null;

			function stopWarmup() {
				if ( warmupWatchId !== null ) {
					navigator.geolocation.clearWatch( warmupWatchId );
					warmupWatchId = null;
				}
			}

			function fitWithUser() {
				map.invalidateSize();
				if ( !userMarker ) {
					fitAll();
					return;
				}
				var items = markers.concat( [userMarker] );
				map.fitBounds( L.featureGroup( items ).getBounds(), { padding: [80, 80] } );
			}

			function updateUser( lat, lon, shouldFit ) {
				if ( !userMarker ) {
					userMarker = L.marker( [lat, lon], { icon: userIcon } ).addTo( map );
				} else {
					userMarker.setLatLng( [lat, lon] );
				}
				if ( shouldFit ) fitWithUser();
			}

			function startWarmup( opts ) {
				if ( !( "geolocation" in navigator ) ) return;

				stopWarmup();

				var startTime = Date.now();
				var firstFix  = false;
				var bestAcc   = Number.POSITIVE_INFINITY;

				function isClearlyWorse( acc ) {
					if ( !Number.isFinite( bestAcc ) ) return false;
					return acc > ( bestAcc + 25 ) && acc > ( bestAcc * 1.8 );
				}

				warmupWatchId = navigator.geolocation.watchPosition(
					function( p ) {
						var lat = p.coords.latitude;
						var lon = p.coords.longitude;
						var acc = ( typeof p.coords.accuracy === "number" && isFinite( p.coords.accuracy ) )
							? p.coords.accuracy : null;

						if ( acc !== null && isClearlyWorse( acc ) ) {
							if ( !firstFix ) {
								firstFix = true;
								updateUser( lat, lon, opts.fitOnStart );
							}
							return;
						}

						if ( acc !== null && acc < bestAcc ) bestAcc = acc;

						if ( !firstFix ) {
							firstFix = true;
							updateUser( lat, lon, opts.fitOnStart );
						} else {
							updateUser( lat, lon, false );
						}

						var timeUp        = ( Date.now() - startTime ) > opts.maxTimeMs;
						var accurateEnough = ( acc !== null ) && acc <= opts.targetAccuracyM;

						if ( accurateEnough || timeUp ) {
							stopWarmup();
							if ( opts.fitOnEnd ) fitWithUser();
						}
					},
					function() {
						stopWarmup();
						if ( opts.fitOnEnd ) fitWithUser();
					},
					{ enableHighAccuracy: true, maximumAge: 0, timeout: opts.maxTimeMs }
				);

				window.setTimeout( function() {
					if ( warmupWatchId !== null ) {
						stopWarmup();
						if ( opts.fitOnEnd ) fitWithUser();
					}
				}, opts.maxTimeMs + 250 );
			}

			/* Initial warmup */
			startWarmup({
				maxTimeMs:      25000,
				targetAccuracyM: 20,
				fitOnStart:      true,
				fitOnEnd:        false
			});

			/* Button */
			var centerBtn = document.getElementById( "city-center-user-btn" );
			if ( centerBtn ) {
				centerBtn.addEventListener( "click", function(e) {
					e.preventDefault();
					centerBtn.classList.add( "tap" );
					setTimeout( function(){ centerBtn.classList.remove( "tap" ); }, 120 );

					startWarmup({
						maxTimeMs:       20000,
						targetAccuracyM: 15,
						fitOnStart:      true,
						fitOnEnd:        true
					});
				});
			}
		}

		if ( document.readyState === "loading" ) {
			document.addEventListener( "DOMContentLoaded", initCityMap );
		} else {
			initCityMap();
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'city_map_inline_js', 99 );
