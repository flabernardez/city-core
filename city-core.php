<?php
/**
 * Plugin Name: City Core
 * Plugin URI:  https://flabernardez.com/city-core
 * Description: Core functions for the City project.
 * Version:     0.1
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

define( 'CITY_CORE_VERSION', '0.1' );
define( 'CITY_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CITY_CORE_URL', plugin_dir_url( __FILE__ ) );

// -------------------------------------------------------------------------
// Includes
// -------------------------------------------------------------------------

require_once CITY_CORE_DIR . 'includes/city-wp-settings.php';
require_once CITY_CORE_DIR . 'includes/city-cpts.php';
require_once CITY_CORE_DIR . 'includes/city-poi-meta.php';
require_once CITY_CORE_DIR . 'includes/city-map-geojson.php';
require_once CITY_CORE_DIR . 'includes/city-map-frontend.php';

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
