<?php
/**
 * POI Meta Fields: Latitude and Longitude.
 *
 * Uses classic meta boxes for compatibility with both classic and block editors.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers latitude and longitude meta for the POI post type.
 *
 * @since 0.1
 */
function city_register_poi_meta() {
	register_post_meta( 'poi', 'city_poi_lat', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_lng', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );
}
add_action( 'init', 'city_register_poi_meta', 20 );

/**
 * Add meta box for POI coordinates.
 *
 * @since 0.1
 */
function city_add_poi_coordinates_metabox() {
	add_meta_box(
		'city_poi_coordinates',
		__( 'Coordinates', 'city-core' ),
		'city_poi_coordinates_metabox_callback',
		'poi',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'city_add_poi_coordinates_metabox' );

/**
 * Render the coordinates meta box.
 *
 * @since 0.1
 *
 * @param WP_Post $post Post object.
 */
function city_poi_coordinates_metabox_callback( $post ) {
	wp_nonce_field( 'city_poi_save_coordinates', 'city_poi_coordinates_nonce' );

	$lat = get_post_meta( $post->ID, 'city_poi_lat', true );
	$lng = get_post_meta( $post->ID, 'city_poi_lng', true );
	?>
	<p>
		<label for="city_poi_lat"><?php esc_html_e( 'Latitude', 'city-core' ); ?></label><br>
		<input type="number" step="any" id="city_poi_lat" name="city_poi_lat" 
			   value="<?php echo esc_attr( $lat ); ?>" 
			   placeholder="41.4036299" style="width: 100%;">
	</p>
	<p>
		<label for="city_poi_lng"><?php esc_html_e( 'Longitude', 'city-core' ); ?></label><br>
		<input type="number" step="any" id="city_poi_lng" name="city_poi_lng" 
			   value="<?php echo esc_attr( $lng ); ?>" 
			   placeholder="2.1743558" style="width: 100%;">
	</p>
	<p class="description">
		<?php esc_html_e( 'Decimal coordinates for this point of interest.', 'city-core' ); ?>
	</p>
	<?php
}

/**
 * Save POI coordinates meta.
 *
 * @since 0.1
 *
 * @param int $post_id Post ID.
 */
function city_save_poi_coordinates( $post_id ) {
	if ( ! isset( $_POST['city_poi_coordinates_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['city_poi_coordinates_nonce'], 'city_poi_save_coordinates' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$lat = isset( $_POST['city_poi_lat'] ) ? sanitize_text_field( $_POST['city_poi_lat'] ) : '';
	$lng = isset( $_POST['city_poi_lng'] ) ? sanitize_text_field( $_POST['city_poi_lng'] ) : '';

	// Validate coordinates are reasonable values.
	if ( ! empty( $lat ) && ( ! is_numeric( $lat ) || $lat < -90 || $lat > 90 ) ) {
		$lat = '';
	}
	if ( ! empty( $lng ) && ( ! is_numeric( $lng ) || $lng < -180 || $lng > 180 ) ) {
		$lng = '';
	}

	update_post_meta( $post_id, 'city_poi_lat', $lat );
	update_post_meta( $post_id, 'city_poi_lng', $lng );
}
add_action( 'save_post_poi', 'city_save_poi_coordinates' );
