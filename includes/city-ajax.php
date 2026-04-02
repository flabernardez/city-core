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
 * Mark a POI as completed (called when quiz answered correctly).
 *
 * @since 0.4
 */
function city_ajax_complete_poi() {
	check_ajax_referer( 'city_poi_nonce', 'nonce' );

	$poi_id = isset( $_POST['poi_id'] ) ? absint( $_POST['poi_id'] ) : 0;

	if ( ! $poi_id || get_post_type( $poi_id ) !== 'poi' ) {
		wp_send_json_error( 'Invalid POI ID' );
	}

	update_post_meta( $poi_id, 'city_poi_completed', 1 );

	wp_send_json_success( array( 'poi_id' => $poi_id ) );
}
add_action( 'wp_ajax_city_complete_poi', 'city_ajax_complete_poi' );
add_action( 'wp_ajax_nopriv_city_complete_poi', 'city_ajax_complete_poi' );

/**
 * Toggle favorite state for a POI.
 *
 * @since 0.4
 */
function city_ajax_toggle_favorite() {
	check_ajax_referer( 'city_poi_nonce', 'nonce' );

	$poi_id = isset( $_POST['poi_id'] ) ? absint( $_POST['poi_id'] ) : 0;

	if ( ! $poi_id || get_post_type( $poi_id ) !== 'poi' ) {
		wp_send_json_error( 'Invalid POI ID' );
	}

	$current = (int) get_post_meta( $poi_id, 'city_poi_favorite', true );
	$new     = $current ? 0 : 1;

	update_post_meta( $poi_id, 'city_poi_favorite', $new );

	wp_send_json_success( array(
		'poi_id'  => $poi_id,
		'favorite' => $new,
	) );
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
