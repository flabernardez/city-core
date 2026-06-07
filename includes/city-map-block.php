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

	$map_colors    = city_get_map_colors();
	$city_slug     = $post->post_name;
	$unlock_radius = isset( $attributes['unlockRadius'] ) ? (int) $attributes['unlockRadius'] : 50;

	// When Polylang is active the translated map post may have a different
	// slug (e.g. "alicante-en") while the shared city taxonomy term is still
	// "alicante". Resolve the city slug from the base (default-language) post
	// so the tax_query always matches the correct term regardless of slug.
	$city_term_id = 0;
	if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
		$default_lang = pll_default_language();
		$base_post_id = pll_get_post( $post_id, $default_lang );
		if ( ! $base_post_id ) {
			$base_post_id = $post_id;
		}

		// Always use the base post's slug as fallback — the translated map
		// slug (e.g. "alicante-en") won't match the city term ("alicante").
		$base_post_obj = get_post( $base_post_id );
		if ( $base_post_obj ) {
			$city_slug = $base_post_obj->post_name;
		}

		$city_terms = get_the_terms( $base_post_id, 'city' );
		if ( $city_terms && ! is_wp_error( $city_terms ) ) {
			$city_term_id = $city_terms[0]->term_id;
			$city_slug    = $city_terms[0]->slug;
		}
	}

	// ── Query published POIs for this city ──────────────────────────────────
	$tax_query_field = $city_term_id ? 'term_id' : 'slug';
	$tax_query_value = $city_term_id ? $city_term_id : $city_slug;

	$poi_query = new WP_Query( array(
		'post_type'      => 'poi',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'city',
				'field'    => $tax_query_field,
				'terms'    => $tax_query_value,
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

			// Resolve base (default-language) POI slug so all translations
			// share the same progress key in localStorage.
			$poi_slug  = get_post_field( 'post_name', $poi_id );
			$base_slug = $poi_slug;
			if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
				$base_poi_id = pll_get_post( $poi_id, pll_default_language() );
				if ( $base_poi_id ) {
					$base_slug = get_post_field( 'post_name', $base_poi_id );
				}
			}

			$pois[] = array(
				'slug'          => $poi_slug,
				'baseSlug'      => $base_slug,
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
		'citySlug'          => $city_slug,
		'pois'              => $pois,
		'unlockRadius'      => $unlock_radius,
		'lockedUseCategory' => (bool) get_option( 'city_map_locked_use_category', false ),
		'i18n'              => array(
			'updatePosition' => __( 'Update position', 'city-core' ),
			'viewMore'       => __( 'View more', 'city-core' ),
			'lockedMsg'      => __( 'Get closer to unlock this point of interest.', 'city-core' ),
			'completedMsg'   => __( 'Completed!', 'city-core' ),
			'noResultsMsg'   => __( 'No hay ningún punto de esa categoría en esta ciudad', 'city-core' ),
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

		<!-- Position button (icon only) -->
		<div class="city-map-button">
			<a id="city-center-user-btn" href="#" aria-label="<?php esc_attr_e( 'Update position', 'city-core' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
					 width="18" height="18" fill="currentColor" aria-hidden="true">
					<path d="M256 32C167.67 32 96 96.51 96 176c0 128 160 304 160 304s160-176 160-304c0-79.49-71.67-144-160-144m0 224a64 64 0 1 1 64-64 64.07 64.07 0 0 1-64 64"></path>
				</svg>
			</a>
		</div>
	</div>

	<!-- Map styles -->
	<style>
	#city-map-wrapper{position:relative;width:100%;height:100vh;min-height:400px;}
	#city-map{width:100%;height:100%;}

/* Voyager tile filter */
#city-map .leaflet-tile{filter:saturate(.65) hue-rotate(12deg) brightness(1.02) contrast(1.06);}

/* Button */
.city-map-button{position:absolute;right:var(--wp--preset--spacing--50,20px);bottom:calc(var(--wp--preset--spacing--50) + 15vh);z-index:3000;}
#city-center-user-btn{display:flex;align-items:center;justify-content:center;width:44px;height:44px;background:<?php echo esc_attr( city_hex_to_rgba( $map_colors['button_bg'], 0.95 ) ); ?>;color:<?php echo esc_attr( $map_colors['button_text'] ); ?>;border-radius:0;border:2px solid <?php echo esc_attr( $map_colors['button'] ); ?>;box-shadow:0 4px 12px rgba(0,0,0,.25);backdrop-filter:blur(8px);padding:0;text-decoration:none;transition:transform .12s ease,box-shadow .12s ease;}
#city-center-user-btn:active,#city-center-user-btn.tap{transform:scale(.96);box-shadow:0 2px 8px rgba(0,0,0,.22);}

/* POI markers */
.city-poi-marker{display:flex;align-items:center;justify-content:center;width:36px;height:44px;font-size:36px;filter:drop-shadow(0 2px 6px rgba(0,0,0,.35));transition:transform .15s ease,opacity .25s ease;cursor:pointer;}
.city-poi-marker svg{width:1em;height:1em;}
.city-poi-marker--locked{color:<?php echo esc_attr( $map_colors['locked'] ); ?>;}
.city-poi-marker--unlocked{color:<?php echo esc_attr( $map_colors['unlocked'] ); ?>;}
.city-poi-marker--completed{color:<?php echo esc_attr( $map_colors['category_active'] ); ?>;}
.city-poi-marker:hover{transform:scale(1.12);}
.city-poi-marker--inline{width:28px;height:28px;font-size:28px;margin:0 auto 6px;filter:none;}

/* Category filter active state */
.city-categories a.city-category-active{color:<?php echo esc_attr( $map_colors['category_active'] ); ?>;font-weight:700;}

/* No results modal — uses popup colors and Figma style */
#city-no-results-modal{pointer-events:none;}
#city-no-results-modal .city-popup-inner{background:<?php echo esc_attr( $map_colors['popup_bg'] ); ?>;border:2px solid <?php echo esc_attr( $map_colors['popup_border'] ); ?>;color:<?php echo esc_attr( $map_colors['popup_text'] ); ?>;box-shadow:none;font-family:var(--wp--preset--font-family--monospace, ui-monospace, "JetBrains Mono", "Courier New", monospace);}

/* User location */
.city-user-icon{position:relative;width:26px;height:26px;}
.city-user-wave{position:absolute;top:50%;left:50%;width:26px;height:26px;border-radius:50%;background:rgba(30,144,255,0.22);transform:translate(-50%,-50%) scale(1);z-index:1;animation:cityWave 2.8s ease-out infinite;}
.city-user-wave.delay{animation-delay:1.4s;}
@keyframes cityWave{0%{transform:translate(-50%,-50%) scale(1);opacity:.55;}70%{opacity:.15;}100%{transform:translate(-50%,-50%) scale(3);opacity:0;}}
.city-user-dot{width:26px;height:26px;border-radius:50%;background:var(--wp--preset--color--core-off-white,#F5F5F5);border:2px solid var(--wp--preset--color--contrast,#1E1E1E);box-shadow:0 4px 10px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;position:relative;z-index:3;}
.city-user-dot svg{width:14px;height:14px;fill:var(--wp--preset--color--contrast,#1E1E1E);}

/* Popups — Figma-style: sharp corners, configurable border/bg/text, monospace */
#city-map .leaflet-popup-content-wrapper{background:<?php echo esc_attr( $map_colors['popup_bg'] ); ?>;border:2px solid <?php echo esc_attr( $map_colors['popup_border'] ); ?>;border-radius:0;box-shadow:none;padding:0;}
#city-map .leaflet-popup-content{margin:0;font-family:var(--wp--preset--font-family--monospace, ui-monospace, "JetBrains Mono", "Courier New", monospace);font-size:var(--wp--preset--font-size--small,14px);line-height:1.4;}
#city-map .leaflet-popup-tip{background:<?php echo esc_attr( $map_colors['popup_bg'] ); ?>;border:2px solid <?php echo esc_attr( $map_colors['popup_border'] ); ?>;box-shadow:none;}
#city-map .leaflet-popup-tip-container{margin-top:-2px;}
#city-map .leaflet-popup-close-button{color:<?php echo esc_attr( $map_colors['popup_text'] ); ?>;}

.city-popup{max-width:240px;}
.city-popup-inner{padding:14px 18px 12px;text-align:center;}
.city-popup-category-icon{text-align:center;font-size:28px;margin:0 0 8px;line-height:1;}
.city-popup-category-icon svg{width:1em;height:1em;fill:currentColor;}
.city-popup-title{font-family:inherit;font-weight:700;font-size:var(--wp--preset--font-size--small,14px);line-height:1.2;color:<?php echo esc_attr( $map_colors['popup_text'] ); ?>;margin:0 0 8px;text-transform:uppercase;letter-spacing:0.02em;}
.city-popup-button{margin-top:10px;}
.city-popup-button .wp-block-button__link{font-family:inherit;font-size:var(--wp--preset--font-size--small,13px);font-style:normal;font-weight:500;letter-spacing:1px;line-height:0.8;padding:8px 18px;background:var(--wp--preset--color--core-light-gray,#f0f0f0);border:none;border-radius:100px;color:<?php echo esc_attr( $map_colors['popup_text'] ); ?>;text-decoration:none;display:inline-block;cursor:pointer;}
.city-popup-locked-msg,
.city-popup-completed-msg{font-family:inherit;font-size:var(--wp--preset--font-size--small,14px);color:var(--wp--preset--color--core-black);margin:0;line-height:1.4;}
.city-popup--completed .city-popup-title{color:<?php echo esc_attr( $map_colors['category_active'] ); ?>;}
	</style>
	<?php
	return ob_get_clean();
}
