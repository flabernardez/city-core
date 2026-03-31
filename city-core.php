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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disables comments sitewide.
 *
 * Closes comments on all post types, removes comment support,
 * hides existing comments and the admin menu entry.
 *
 * @since 0.1
 */
function city_disable_comments() {
	// Close comments on all existing posts.
	add_filter( 'comments_open', '__return_false', 20, 2 );
	add_filter( 'pings_open', '__return_false', 20, 2 );

	// Hide existing comments.
	add_filter( 'comments_array', '__return_empty_array', 10, 2 );

	// Remove comment support from all post types.
	add_action( 'init', function () {
		foreach ( get_post_types() as $post_type ) {
			if ( post_type_supports( $post_type, 'comments' ) ) {
				remove_post_type_support( $post_type, 'comments' );
				remove_post_type_support( $post_type, 'trackbacks' );
			}
		}
	} );

	// Remove comments from the admin menu.
	add_action( 'admin_menu', function () {
		remove_menu_page( 'edit-comments.php' );
	} );

	// Remove comments from the admin bar.
	add_action( 'init', function () {
		if ( is_admin_bar_showing() ) {
			remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
		}
	} );
}
add_action( 'plugins_loaded', 'city_disable_comments' );
