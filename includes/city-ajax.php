<?php
/**
 * AJAX handlers for POI state: completion and favorites.
 *
 * @package CityCore
 * @since   0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mark a POI as completed — no-op.
 *
 * Completion state is now stored client-side only (localStorage + cookie)
 * so each device maintains its own progress. The endpoint is kept registered
 * to avoid 400 errors from any legacy scripts that still call it.
 *
 * @since 0.4
 */
function city_ajax_complete_poi() {
	wp_send_json_success();
}
add_action( 'wp_ajax_city_complete_poi', 'city_ajax_complete_poi' );
add_action( 'wp_ajax_nopriv_city_complete_poi', 'city_ajax_complete_poi' );

/**
 * Toggle favorite state for a POI — no-op.
 *
 * Favorite state is now stored client-side only (localStorage) so each
 * device maintains its own list. The endpoint is kept registered to avoid
 * 400 errors from any legacy scripts that still call it.
 *
 * @since 0.4
 */
function city_ajax_toggle_favorite() {
	wp_send_json_success();
}
add_action( 'wp_ajax_city_toggle_favorite', 'city_ajax_toggle_favorite' );
add_action( 'wp_ajax_nopriv_city_toggle_favorite', 'city_ajax_toggle_favorite' );

/**
 * Get POI state (completed + favorite) for a list of POI IDs.
 * Used by the visibility condition for the Visibility Block plugin.
 *
 * @since 0.4
 */
function city_ajax_get_poi_states() {
	check_ajax_referer( 'city_poi_nonce', 'nonce' );

	$poi_ids = isset( $_POST['poi_ids'] ) ? array_map( 'absint', (array) $_POST['poi_ids'] ) : array();

	if ( empty( $poi_ids ) ) {
		wp_send_json_success( array() );
	}

	$states = array();
	foreach ( $poi_ids as $poi_id ) {
		if ( get_post_type( $poi_id ) !== 'poi' ) {
			continue;
		}
		$states[ $poi_id ] = array(
			'completed' => (int) get_post_meta( $poi_id, 'city_poi_completed', true ),
			'favorite'  => (int) get_post_meta( $poi_id, 'city_poi_favorite', true ),
		);
	}

	wp_send_json_success( $states );
}
add_action( 'wp_ajax_city_get_poi_states', 'city_ajax_get_poi_states' );
add_action( 'wp_ajax_nopriv_city_get_poi_states', 'city_ajax_get_poi_states' );

/**
 * Reset all player progress — no-op on server.
 *
 * Progress is now client-side only. The real reset happens in the browser
 * (city-reset-game.js clears localStorage, sessionStorage and cookies).
 * This endpoint is kept registered to avoid errors from the JS caller.
 *
 * @since 0.8
 */
function city_ajax_reset_all_progress() {
	wp_send_json_success();
}
add_action( 'wp_ajax_city_reset_all_progress',        'city_ajax_reset_all_progress' );
add_action( 'wp_ajax_nopriv_city_reset_all_progress', 'city_ajax_reset_all_progress' );
