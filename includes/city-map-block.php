<?php
/**
 * City Map Gutenberg block — render callback and asset registration.
 *
 * Leaflet CSS/JS and Google Fonts are loaded inline BEFORE the map markup,
 * matching the pattern used in mancorutas — this ensures styles are applied
 * before the browser paints the map container.
 *
 * All interactive behaviour lives in blocks/city-map/view.js.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the city-core/map block type.
 *
 * @since 0.1
 */
function city_register_map_block() {
	register_block_type(
		CITY_CORE_DIR . 'blocks/city-map',
		array(
			'render_callback' => 'city_map_block_render',
		)
	);
}
add_action( 'init', 'city_register_map_block' );

/**
 * Enqueues Google Fonts (Nunito) for popup typography.
 *
 * Loaded here so the <link> can be placed inline in the render callback,
 * before the map markup — matching mancorutas pattern.
 *
 * @since 0.1
 */

/**
 * Renders the City Map block on the frontend.
 *
 * Follows mancorutas pattern: CSS and JS CDN links are placed inline
 * BEFORE the map markup, so the browser applies them before first paint.
 *
 * @since 0.1
 *
 * @param array    $attributes Block attributes (includes unlockRadius).
 * @param string   $content    Inner content (unused).
 * @param WP_Block $block      Block instance with post context.
 * @return string HTML output.
 */
function city_map_block_render( $attributes, $content, $block ) {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return '';
	}

	$city_slug     = $post->post_name;
	$unlock_radius = isset( $attributes['unlockRadius'] ) ? (int) $attributes['unlockRadius'] : 50;

	// ── Query published POIs for this city ──────────────────────────────────
	$poi_query = new WP_Query( array(
		'post_type'      => 'poi',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'city',
				'field'    => 'slug',
				'terms'    => $city_slug,
			),
		),
	) );

	$pois = array();

	if ( $poi_query->have_posts() ) {
		while ( $poi_query->have_posts() ) {
			$poi_query->the_post();

			$poi_id = get_the_ID();

			// Priority: manual coordinates over extracted ones.
			$manual_lat = get_post_meta( $poi_id, 'city_poi_manual_lat', true );
			$manual_lng = get_post_meta( $poi_id, 'city_poi_manual_lng', true );

			if ( ! empty( $manual_lat ) && ! empty( $manual_lng ) ) {
				$lat = $manual_lat;
				$lng = $manual_lng;
			} else {
				$lat = get_post_meta( $poi_id, 'city_poi_lat', true );
				$lng = get_post_meta( $poi_id, 'city_poi_lng', true );
			}

			if ( '' === $lat || '' === $lng ) {
				continue;
			}

			// Location tolerance (defaults to 100m).
			$tolerance = get_post_meta( $poi_id, 'city_poi_tolerance', true );
			if ( empty( $tolerance ) ) {
				$tolerance = 'normal';
			}

			$tolerance_meters = 50;
			if ( 'strict' === $tolerance ) {
				$tolerance_meters = 5;
			} elseif ( 'amplio' === $tolerance ) {
				$tolerance_meters = 500;
			}

			// ── Category colour, SVG and slug ──────────────────────────────────
			$color          = '';
			$category_svg   = '';
			$category_slug  = '';
			$terms         = get_the_terms( $poi_id, 'poi-category' );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_color = get_term_meta( $term->term_id, 'city_poi_category_color', true );
					$term_svg  = get_term_meta( $term->term_id, 'city_poi_category_svg', true );

					if ( $term_color && ! $color ) {
						$color = $term_color;
					}
					if ( $term_svg && ! $category_svg ) {
						$category_svg = $term_svg;
					}
					if ( $term->slug && ! $category_slug ) {
						$category_slug = $term->slug;
					}

					if ( $color && $category_svg && $category_slug ) {
						break;
					}
				}
			}

			$pois[] = array(
				'slug'          => get_post_field( 'post_name', $poi_id ),
				'name'          => get_the_title(),
				'url'           => get_permalink(),
				'lat'           => (float) $lat,
				'lng'           => (float) $lng,
				'color'         => $color,
				'categorySvg'   => $category_svg,
				'categorySlug'  => $category_slug,
				'tolerance'     => (int) $tolerance_meters,
			);
		}
		wp_reset_postdata();
	}

	// ── Prepare data for the frontend ───────────────────────────────────────
	$map_data = array(
		'citySlug'     => $city_slug,
		'pois'         => $pois,
		'unlockRadius' => $unlock_radius,
		'i18n'         => array(
			'updatePosition' => __( 'Update position', 'city-core' ),
			'viewMore'       => __( 'View more', 'city-core' ),
			'lockedMsg'      => __( 'Get closer to unlock this point of interest.', 'city-core' ),
			'completedMsg'   => __( 'Completed!', 'city-core' ),
		),
	);

	// ── HTML output — mancorutas pattern: CSS/JS before markup ───────────────
	ob_start();
	?>
	<!-- Leaflet CSS and JS — loaded BEFORE markup so tiles render on first paint -->
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

	<!-- Map data -->
	<script type="application/json" id="city-map-data"><?php echo wp_json_encode( $map_data ); ?></script>

	<!-- Map markup -->
	<div id="city-map-wrapper">
		<div id="city-map"></div>

		<!-- Centred button -->
		<div class="city-map-button">
			<div class="wp-block-button with-icon">
				<a id="city-center-user-btn"
				   class="wp-block-button__link wp-element-button"
				   href="#">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
						 width="18" height="18" fill="currentColor" aria-hidden="true">
						<path d="M256 32C167.67 32 96 96.51 96 176c0 128 160 304 160 304s160-176 160-304c0-79.49-71.67-144-160-144m0 224a64 64 0 1 1 64-64 64.07 64.07 0 0 1-64 64"></path>
					</svg>
					<span><?php esc_html_e( 'Update position', 'city-core' ); ?></span>
				</a>
			</div>
		</div>
	</div>

	<!-- Map styles -->
	<style>
	#city-map-wrapper{position:relative;width:100%;height:100vh;min-height:400px;}
	#city-map{width:100%;height:100%;}

/* Voyager tile filter */
#city-map .leaflet-tile{filter:saturate(.65) hue-rotate(12deg) brightness(1.02) contrast(1.06);}

/* Button */
.city-map-button{position:absolute;left:50%;bottom:20vh;transform:translateX(-50%);z-index:3000;display:flex;flex-direction:column;align-items:center;}
#city-center-user-btn{display:inline-flex;align-items:center;gap:8px;white-space:nowrap;background:rgba(255,255,255,.95);color:#063b2f;border-radius:999px;border:2px solid #00624D;box-shadow:0 12px 24px rgba(0,0,0,.25);backdrop-filter:blur(8px);padding:12px 18px;font-weight:400;font-family:var(--wp--preset--font-family--system-font);font-size:inherit;text-decoration:none;transition:transform .12s ease,box-shadow .12s ease;}
#city-center-user-btn:active,#city-center-user-btn.tap{transform:scale(.96);box-shadow:0 6px 14px rgba(0,0,0,.22);}

/* POI markers */
.city-poi-marker{display:flex;align-items:center;justify-content:center;width:36px;height:44px;font-size:36px;filter:drop-shadow(0 2px 6px rgba(0,0,0,.35));transition:transform .15s ease,opacity .25s ease;cursor:pointer;}
.city-poi-marker svg{width:1em;height:1em;}
.city-poi-marker--locked{color:#888;}
.city-poi-marker--unlocked{color:#00624D;}
.city-poi-marker--completed{color:#e07722;}
.city-poi-marker:hover{transform:scale(1.12);}
.city-poi-marker--inline{width:28px;height:28px;font-size:28px;margin:0 auto 6px;filter:none;}

/* Category filter active state */
.city-categories a.city-category-active{color:#e07722;font-weight:700;}

/* No results modal */
#city-no-results-modal{pointer-events:none;}
#city-no-results-modal .city-popup-inner{background:#fff;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.18);}

/* User location */
.city-user-icon{position:relative;width:26px;height:26px;}
.city-user-wave{position:absolute;top:50%;left:50%;width:26px;height:26px;border-radius:50%;background:rgba(0,98,77,.22);transform:translate(-50%,-50%) scale(1);z-index:1;animation:cityWave 2.8s ease-out infinite;}
.city-user-wave.delay{animation-delay:1.4s;}
@keyframes cityWave{0%{transform:translate(-50%,-50%) scale(1);opacity:.55;}70%{opacity:.15;}100%{transform:translate(-50%,-50%) scale(3);opacity:0;}}
.city-user-dot{width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.95);border:2px solid #00624D;box-shadow:0 4px 10px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;position:relative;z-index:3;}
.city-user-dot svg{width:14px;height:14px;fill:#222;}

/* Popups */
.city-popup{max-width:220px;}
.city-popup-inner{padding:8px 10px 10px;text-align:center;}
.city-popup-title{font-family:var(--wp--preset--font-family--system-font);font-weight:700;font-size:14px;line-height:1.2;color:#063b2f;margin:0 0 4px;}
.city-popup-link{display:block;font-size:12px;color:#00624D;text-decoration:underline;}
.city-popup--locked .city-popup-title{color:#555;}
.city-popup-locked-msg{font-size:11px;color:#888;margin:4px 0 0;}
.city-popup--completed .city-popup-title{color:#e07722;}
.city-popup-completed-msg{font-size:11px;color:#e07722;margin:4px 0 0;}
#city-map .leaflet-popup-content{margin:0;}
#city-map .leaflet-popup-content-wrapper{padding:6px 8px;}
	</style>
	<?php
	return ob_get_clean();
}
