<?php
/**
 * Plugin Name: City Core
 * Plugin URI:  https://flabernardez.com/city-core
 * Description: Core functions for the City project.
 * Version:     0.4
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

define( 'CITY_CORE_VERSION', '0.4' );
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

	// Inject POI data (slug + citySlug) as a global on single POI pages.
	if ( is_singular( 'poi' ) ) {
		$poi_id     = get_the_ID();
		$poi_slug   = get_post_field( 'post_name', $poi_id );
		$city_terms = get_the_terms( $poi_id, 'city' );
		$city_slug  = ( $city_terms && ! is_wp_error( $city_terms ) ) ? $city_terms[0]->slug : '';
		?>
		<script>
		window.cityPoiData = <?php echo wp_json_encode( array( 'slug' => $poi_slug, 'citySlug' => $city_slug ) ); ?>;
		</script>
		<?php
	}
}
add_action( 'wp_enqueue_scripts', 'city_enqueue_favorites_script' );

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
