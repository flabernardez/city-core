<?php
/**
 * POI Meta Fields: Latitude and Longitude.
 *
 * Registers post meta for the POI custom post type and renders
 * a classic meta box with two numeric inputs in the editor.
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
	$meta_args = array(
		'type'              => 'number',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'city_sanitize_coordinate',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	);

	register_post_meta( 'poi', 'city_poi_lat', $meta_args );
	register_post_meta( 'poi', 'city_poi_lng', $meta_args );
}
add_action( 'init', 'city_register_poi_meta' );

/**
 * Sanitizes a coordinate value.
 *
 * @since 0.1
 *
 * @param mixed $value Raw coordinate value.
 * @return float|string Sanitized float or empty string.
 */
function city_sanitize_coordinate( $value ) {
	if ( '' === $value || null === $value ) {
		return '';
	}
	return (float) $value;
}

/**
 * Adds the coordinates meta box to the POI editor.
 *
 * @since 0.1
 */
function city_add_poi_coordinates_meta_box() {
	add_meta_box(
		'city_poi_coordinates',
		__( 'Coordinates', 'city-core' ),
		'city_poi_coordinates_meta_box_callback',
		'poi',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'city_add_poi_coordinates_meta_box' );

/**
 * Renders the coordinates meta box.
 *
 * @since 0.1
 *
 * @param WP_Post $post Current post object.
 */
function city_poi_coordinates_meta_box_callback( $post ) {
	wp_nonce_field( 'city_poi_coordinates_save', 'city_poi_coordinates_nonce' );

	$lat = get_post_meta( $post->ID, 'city_poi_lat', true );
	$lng = get_post_meta( $post->ID, 'city_poi_lng', true );
	?>
	<p>
		<label for="city_poi_lat">
			<strong><?php esc_html_e( 'Latitude', 'city-core' ); ?></strong>
		</label><br>
		<input type="number" step="any" id="city_poi_lat" name="city_poi_lat"
			   value="<?php echo esc_attr( $lat ); ?>"
			   style="width:100%;" placeholder="41.4036299">
	</p>
	<p>
		<label for="city_poi_lng">
			<strong><?php esc_html_e( 'Longitude', 'city-core' ); ?></strong>
		</label><br>
		<input type="number" step="any" id="city_poi_lng" name="city_poi_lng"
			   value="<?php echo esc_attr( $lng ); ?>"
			   style="width:100%;" placeholder="2.1743558">
	</p>
	<p class="description">
		<?php esc_html_e( 'Enter the decimal coordinates for this point of interest.', 'city-core' ); ?>
	</p>
	<?php
}

/**
 * Saves the coordinates meta box data.
 *
 * @since 0.1
 *
 * @param int $post_id Post ID.
 */
function city_save_poi_coordinates( $post_id ) {
	if ( ! isset( $_POST['city_poi_coordinates_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['city_poi_coordinates_nonce'], 'city_poi_coordinates_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$fields = array( 'city_poi_lat', 'city_poi_lng' );

	foreach ( $fields as $field ) {
		if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
			update_post_meta( $post_id, $field, city_sanitize_coordinate( $_POST[ $field ] ) );
		} else {
			delete_post_meta( $post_id, $field );
		}
	}
}
add_action( 'save_post_poi', 'city_save_poi_coordinates' );
