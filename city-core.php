<?php
/**
 * Plugin Name: City Core
 * Plugin URI:  https://flabernardez.com/city-core
 * Description: Core functions for the City project.
 * Version:     0.8
 * Author:      Flavia Bernardez Rodriguez
 * Author URI:  https://flabernardez.com
 * License:     GPL-2.0-or-later
 * Text Domain: city-core
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------

define( 'CITY_CORE_VERSION', '0.6' );
define( 'CITY_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CITY_CORE_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Includes
// -------------------------------------------------------------------------

require_once CITY_CORE_DIR . 'includes/city-wp-settings.php';
require_once CITY_CORE_DIR . 'includes/city-cpts.php';
require_once CITY_CORE_DIR . 'includes/city-poi-meta.php';
require_once CITY_CORE_DIR . 'includes/city-poi-category-meta.php';
require_once CITY_CORE_DIR . 'includes/city-map-geojson.php';
require_once CITY_CORE_DIR . 'includes/city-map-block.php';
require_once CITY_CORE_DIR . 'includes/city-settings.php';
require_once CITY_CORE_DIR . 'includes/city-sheets-connector.php';
require_once CITY_CORE_DIR . 'includes/city-quiz-block.php';
require_once CITY_CORE_DIR . 'includes/city-ajax.php';
require_once CITY_CORE_DIR . 'includes/city-button-back.php';
require_once CITY_CORE_DIR . 'includes/city-accessibility-block.php';

// Polylang integration — loaded only when Polylang is active.
add_action( 'plugins_loaded', function () {
	if ( defined( 'POLYLANG_VERSION' ) ) {
		require_once CITY_CORE_DIR . 'includes/city-polylang.php';
	}
} );

// -------------------------------------------------------------------------
// Enqueue favorites script
// -------------------------------------------------------------------------

/**
 * Enqueue the favorites toggle script on the frontend.
 *
 * @since 0.4
 */
function city_enqueue_favorites_script() {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_script(
		'city-favorites',
		CITY_CORE_URL . 'assets/js/city-favorites.js',
		array(),
		CITY_CORE_VERSION,
		true
	);

	wp_localize_script(
		'city-favorites',
		'cityCoreAjax',
		array(
			'url'   => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'city_poi_nonce' ),
		)
	);

	// Global category filter — intercepts clicks on .city-categories links and
	// applies the map filter when the map is present on the page.
	// v2 uses capture-phase to bypass any third-party stopPropagation, and the
	// rename forces a cache miss on aggressive CDNs/proxies.
	wp_enqueue_script(
		'city-category-filter',
		CITY_CORE_URL . 'assets/js/city-category-filter-v2.js',
		array(),
		CITY_CORE_VERSION,
		true
	);

	// Global reset-game handler — any element with class .city-reset-game
	// clears player progress (server-side meta + cookies + localStorage)
	// and reloads the page. Depends on city-favorites so window.cityCoreAjax
	// (url + nonce) is already defined when this script runs.
	wp_enqueue_script(
		'city-reset-game',
		CITY_CORE_URL . 'assets/js/city-reset-game.js',
		array( 'city-favorites' ),
		CITY_CORE_VERSION,
		true
	);

	// Auto-close open <details> elements when clicking outside.
	wp_enqueue_script(
		'city-details-autoclose',
		CITY_CORE_URL . 'assets/js/city-details-autoclose.js',
		array(),
		CITY_CORE_VERSION,
		true
	);

	// Smooth-scroll to summary when clicking #city_secret_info details.
	wp_enqueue_script(
		'city-secret-scroll',
		CITY_CORE_URL . 'assets/js/city-secret-scroll.js',
		array(),
		CITY_CORE_VERSION,
		true
	);

	// Inject POI data (slug + citySlug) as a global on single POI pages.
	if ( is_singular( 'poi' ) ) {
		$poi_id     = get_the_ID();
		$poi_slug   = get_post_field( 'post_name', $poi_id );
		$city_terms = get_the_terms( $poi_id, 'city' );
		$city_slug  = ( $city_terms && ! is_wp_error( $city_terms ) ) ? $city_terms[0]->slug : '';

		// Resolve base (default-language) POI slug for cross-language state sharing.
		$base_slug = $poi_slug;
		if ( function_exists( 'pll_get_post' ) && function_exists( 'pll_default_language' ) ) {
			$base_poi_id = pll_get_post( $poi_id, pll_default_language() );
			if ( $base_poi_id ) {
				$base_slug = get_post_field( 'post_name', $base_poi_id );
			}
		}
		?>
		<script>
		window.cityPoiData = <?php echo wp_json_encode( array( 'slug' => $poi_slug, 'baseSlug' => $base_slug, 'citySlug' => $city_slug ) ); ?>;
		// Clear quiz status cookie on POI pages without a quiz block, so
		// Block Visibility conditions don't leak from a previously visited POI.
		document.addEventListener('DOMContentLoaded', function() {
			if (!document.querySelector('.city-quiz')) {
				document.cookie = 'city_poi_quiz_status=; path=/; max-age=0; SameSite=Lax';
			}
		});
		</script>
		<?php
	}
}
add_action( 'wp_enqueue_scripts', 'city_enqueue_favorites_script' );

/**
 * Inject a translated-slug → base-slug map for POI listing blocks.
 *
 * On translated pages the Query Loop shows POIs with translated slugs
 * (e.g. "museo-en") but localStorage stores base slugs ("museo").
 * This filter detects `core/query` blocks with `city-` classes (city-completed,
 * city-favorite), extracts the POI slugs from the rendered HTML, resolves each
 * to its base slug via Polylang, and injects a small `<script>` before the
 * block so the JS filter can match correctly.
 *
 * @since 0.8
 */
function city_inject_base_slug_map_for_listing( $content, $block ) {
	// Only act on blocks with city- classes.
	$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	if ( strpos( $class, 'city-' ) === false ) {
		return $content;
	}

	// Nothing to map without Polylang.
	if ( ! function_exists( 'pll_get_post' ) || ! function_exists( 'pll_default_language' ) ) {
		return $content;
	}

	$default_lang = pll_default_language();

	// If we're already in the default language, slugs are already base slugs.
	if ( function_exists( 'pll_current_language' ) && pll_current_language() === $default_lang ) {
		return $content;
	}

	// Extract POI slugs from rendered HTML links (/poi/{slug}/).
	if ( ! preg_match_all( '/\/poi\/([^\/\?"]+)/', $content, $matches ) ) {
		return $content;
	}

	$slugs = array_unique( $matches[1] );
	$map   = array();

	foreach ( $slugs as $slug ) {
		$posts = get_posts( array(
			'post_type'      => 'poi',
			'name'           => $slug,
			'numberposts'    => 1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		if ( empty( $posts ) ) {
			continue;
		}
		$base_id = pll_get_post( $posts[0], $default_lang );
		if ( $base_id ) {
			$base_slug = get_post_field( 'post_name', $base_id );
			if ( $base_slug !== $slug ) {
				$map[ $slug ] = $base_slug;
			}
		}
	}

	if ( ! empty( $map ) ) {
		$script = '<script>'
			. 'window.cityBaseSlugMap=window.cityBaseSlugMap||{};'
			. 'Object.assign(window.cityBaseSlugMap,' . wp_json_encode( $map ) . ');'
			. '</script>';
		$content = $script . $content;
	}

	return $content;
}
add_filter( 'render_block_core/query', 'city_inject_base_slug_map_for_listing', 10, 2 );

// -------------------------------------------------------------------------
// Text domain
// -------------------------------------------------------------------------

/**
 * Loads the plugin text domain for translations.
 *
 * @since 0.1
 */
function city_core_load_textdomain() {
	load_plugin_textdomain( 'city-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'city_core_load_textdomain' );
