<?php
/**
 * POI Meta Fields: Latitude, Longitude and Geolocation Settings.
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
 * Registers all meta fields for the POI post type.
 *
 * @since 0.1
 */
function city_register_poi_meta() {
	// Extracted coordinates (from Google Maps URL).
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

	// Manual coordinates (PRIORITY over extracted).
	register_post_meta( 'poi', 'city_poi_manual_lat', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_manual_lng', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Google Maps URL for coordinate extraction.
	register_post_meta( 'poi', 'city_poi_maps_url', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Location tolerance.
	register_post_meta( 'poi', 'city_poi_tolerance', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Quiz meta fields (added in 0.3).
	register_post_meta( 'poi', 'city_poi_hint', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_quiz_question', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_quiz_answer_1', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_quiz_answer_2', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_quiz_answer_3', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_text_field',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	register_post_meta( 'poi', 'city_poi_quiz_correct', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Reward message shown when quiz is answered correctly (supports safe HTML).
	register_post_meta( 'poi', 'city_poi_reward_message', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'wp_kses_post',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Completed image (attachment ID shown as quiz reward).
	register_post_meta( 'poi', 'city_poi_quiz_correct_img', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Auxiliary: URL of the attachment above, kept in sync automatically.
	// Useful for dynamic-field blocks (e.g. Stackable Image) that expect a URL
	// rather than an attachment ID.
	register_post_meta( 'poi', 'city_poi_quiz_correct_img_url', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'esc_url_raw',
		'auth_callback'     => function () {
			return current_user_can( 'edit_posts' );
		},
	) );

	// Completion and favorite state (saved server-side when quiz answered or favorite toggled).
	register_post_meta( 'poi', 'city_poi_completed', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
	) );

	register_post_meta( 'poi', 'city_poi_favorite', array(
		'type'              => 'integer',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'absint',
	) );
}
add_action( 'init', 'city_register_poi_meta', 20 );

/**
 * Add meta box for POI coordinates and geolocation settings.
 *
 * @since 0.1
 */
function city_add_poi_coordinates_metabox() {
	add_meta_box(
		'city_poi_coordinates',
		__( 'Coordinates & Geolocation', 'city-core' ),
		'city_poi_coordinates_metabox_callback',
		'poi',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'city_add_poi_coordinates_metabox' );

/**
 * Add meta box for Quiz settings.
 *
 * @since 0.3
 */
function city_add_poi_quiz_metabox() {
	add_meta_box(
		'city_poi_quiz',
		__( 'Quiz Settings', 'city-core' ),
		'city_poi_quiz_metabox_callback',
		'poi',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'city_add_poi_quiz_metabox' );

/**
 * Render the coordinates meta box.
 *
 * @since 0.1
 *
 * @param WP_Post $post Post object.
 */
function city_poi_coordinates_metabox_callback( $post ) {
	wp_nonce_field( 'city_poi_save_coordinates', 'city_poi_coordinates_nonce' );

	$maps_url    = get_post_meta( $post->ID, 'city_poi_maps_url', true );
	$lat         = get_post_meta( $post->ID, 'city_poi_lat', true );
	$lng         = get_post_meta( $post->ID, 'city_poi_lng', true );
	$manual_lat  = get_post_meta( $post->ID, 'city_poi_manual_lat', true );
	$manual_lng  = get_post_meta( $post->ID, 'city_poi_manual_lng', true );
	$tolerance   = get_post_meta( $post->ID, 'city_poi_tolerance', true );

	if ( empty( $tolerance ) ) {
		$tolerance = 'normal';
	}

	echo '<table class="form-table">';

	// Manual coordinates (PRIORITY).
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_manual_lat">' . esc_html__( 'Manual Coordinates', 'city-core' ) . ' (' . esc_html__( 'PRIORITY', 'city-core' ) . ')</label></th>';
	echo '<td>';
	echo '<strong>' . esc_html__( 'Latitude:', 'city-core' ) . '</strong><br>';
	echo '<input type="number" step="0.000001" id="city_poi_manual_lat" name="city_poi_manual_lat" value="' . esc_attr( $manual_lat ) . '" placeholder="41.4036299" style="width: 100%; max-width: 300px;" /><br><br>';
	echo '<strong>' . esc_html__( 'Longitude:', 'city-core' ) . '</strong><br>';
	echo '<input type="number" step="0.000001" id="city_poi_manual_lng" name="city_poi_manual_lng" value="' . esc_attr( $manual_lng ) . '" placeholder="2.1743558" style="width: 100%; max-width: 300px;" />';
	echo '<p class="description">' . esc_html__( 'If you enter manual coordinates, they will take priority over extracted ones.', 'city-core' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	// Google Maps URL.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_maps_url">' . esc_html__( 'Google Maps URL', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="url" id="city_poi_maps_url" name="city_poi_maps_url" value="' . esc_attr( $maps_url ) . '" size="60" placeholder="https://www.google.com/maps/@..." style="width: 100%;" />';
	echo '<p class="description">' . esc_html__( 'Search the location on Google Maps and copy the full URL.', 'city-core' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	// Extracted coordinates (read-only).
	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Extracted Coordinates', 'city-core' ) . '</th>';
	echo '<td>';
	echo '<strong>' . esc_html__( 'Latitude:', 'city-core' ) . '</strong> <span id="city_extracted_lat">' . esc_html( $lat ) . '</span><br>';
	echo '<strong>' . esc_html__( 'Longitude:', 'city-core' ) . '</strong> <span id="city_extracted_lng">' . esc_html( $lng ) . '</span>';
	echo '<input type="hidden" id="city_poi_lat" name="city_poi_lat" value="' . esc_attr( $lat ) . '" />';
	echo '<input type="hidden" id="city_poi_lng" name="city_poi_lng" value="' . esc_attr( $lng ) . '" />';
	echo '</td>';
	echo '</tr>';

	// Location tolerance.
	echo '<tr>';
	echo '<th scope="row">' . esc_html__( 'Location Tolerance', 'city-core' ) . '</th>';
	echo '<td>';
	echo '<label for="city_poi_tolerance_strict"><input type="radio" id="city_poi_tolerance_strict" name="city_poi_tolerance" value="strict" ' . checked( $tolerance, 'strict', false ) . ' /> ' . esc_html__( 'Strict (5m)', 'city-core' ) . '</label><br>';
	echo '<label for="city_poi_tolerance_normal"><input type="radio" id="city_poi_tolerance_normal" name="city_poi_tolerance" value="normal" ' . checked( $tolerance, 'normal', false ) . ' /> ' . esc_html__( 'Normal (50m)', 'city-core' ) . '</label><br>';
	echo '<label for="city_poi_tolerance_amplio"><input type="radio" id="city_poi_tolerance_amplio" name="city_poi_tolerance" value="amplio" ' . checked( $tolerance, 'amplio', false ) . ' /> ' . esc_html__( 'Wide (500m)', 'city-core' ) . '</label><br>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
	echo '<p><em>' . esc_html__( 'Leave the URL empty if you only want to use manual coordinates.', 'city-core' ) . '</em></p>';

	// JavaScript for extracting coordinates from Google Maps URL.
	echo '<script>
		document.getElementById("city_poi_maps_url").addEventListener("input", function() {
			const url = this.value;
			const coords = cityExtractCoordinatesFromGoogleMapsURL(url);
			
			if (coords) {
				document.getElementById("city_extracted_lat").textContent = coords.lat;
				document.getElementById("city_extracted_lng").textContent = coords.lng;
				document.getElementById("city_poi_lat").value = coords.lat;
				document.getElementById("city_poi_lng").value = coords.lng;
			} else {
				document.getElementById("city_extracted_lat").textContent = "";
				document.getElementById("city_extracted_lng").textContent = "";
				document.getElementById("city_poi_lat").value = "";
				document.getElementById("city_poi_lng").value = "";
			}
		});
		
		function cityExtractCoordinatesFromGoogleMapsURL(url) {
			if (!url) return null;
			
			const patterns = [
				/@(-?\d+\.\d+),(-?\d+\.\d+)/,
				/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,
				/place\/[^@]*@(-?\d+\.\d+),(-?\d+\.\d+)/
			];
			
			for (const pattern of patterns) {
				const match = url.match(pattern);
				if (match) {
					return {
						lat: parseFloat(match[1]),
						lng: parseFloat(match[2])
					};
				}
			}
			
			return null;
		}
	</script>';
}

/**
 * Render the quiz meta box.
 *
 * @since 0.3
 *
 * @param WP_Post $post Post object.
 */
function city_poi_quiz_metabox_callback( $post ) {
	wp_nonce_field( 'city_poi_save_quiz', 'city_poi_quiz_nonce' );

	$hint           = get_post_meta( $post->ID, 'city_poi_hint', true );
	$question       = get_post_meta( $post->ID, 'city_poi_quiz_question', true );
	$answer1        = get_post_meta( $post->ID, 'city_poi_quiz_answer_1', true );
	$answer2        = get_post_meta( $post->ID, 'city_poi_quiz_answer_2', true );
	$answer3        = get_post_meta( $post->ID, 'city_poi_quiz_answer_3', true );
	$correct        = (int) get_post_meta( $post->ID, 'city_poi_quiz_correct', true );
	$reward_message = get_post_meta( $post->ID, 'city_poi_reward_message', true );

	echo '<table class="form-table">';

	// Hint field.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_hint">' . esc_html__( 'Hint (Pista)', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="city_poi_hint" name="city_poi_hint" value="' . esc_attr( $hint ) . '" style="width: 100%;" />';
	echo '<p class="description">' . esc_html__( 'A hint shown to help the user answer the quiz.', 'city-core' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	// Quiz question.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_quiz_question">' . esc_html__( 'Quiz Question', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="city_poi_quiz_question" name="city_poi_quiz_question" value="' . esc_attr( $question ) . '" style="width: 100%;" />';
	echo '</td>';
	echo '</tr>';

	// Answer 1.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_quiz_answer_1">' . esc_html__( 'Answer 1', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="city_poi_quiz_answer_1" name="city_poi_quiz_answer_1" value="' . esc_attr( $answer1 ) . '" style="width: 100%;" />';
	echo ' <label><input type="radio" name="city_poi_quiz_correct" value="1" ' . checked( $correct, 1, false ) . ' /> ' . esc_html__( 'Correct', 'city-core' ) . '</label>';
	echo '</td>';
	echo '</tr>';

	// Answer 2.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_quiz_answer_2">' . esc_html__( 'Answer 2', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="city_poi_quiz_answer_2" name="city_poi_quiz_answer_2" value="' . esc_attr( $answer2 ) . '" style="width: 100%;" />';
	echo ' <label><input type="radio" name="city_poi_quiz_correct" value="2" ' . checked( $correct, 2, false ) . ' /> ' . esc_html__( 'Correct', 'city-core' ) . '</label>';
	echo '</td>';
	echo '</tr>';

	// Answer 3.
	echo '<tr>';
	echo '<th scope="row"><label for="city_poi_quiz_answer_3">' . esc_html__( 'Answer 3', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<input type="text" id="city_poi_quiz_answer_3" name="city_poi_quiz_answer_3" value="' . esc_attr( $answer3 ) . '" style="width: 100%;" />';
	echo ' <label><input type="radio" name="city_poi_quiz_correct" value="3" ' . checked( $correct, 3, false ) . ' /> ' . esc_html__( 'Correct', 'city-core' ) . '</label>';
	echo '</td>';
	echo '</tr>';

	// Reward message (WYSIWYG — supports bold, italic, links).
	echo '<tr>';
	echo '<th scope="row"><label>' . esc_html__( 'Reward Message', 'city-core' ) . '</label></th>';
	echo '<td>';
	wp_editor( $reward_message, 'city_poi_reward_message', array(
		'textarea_name' => 'city_poi_reward_message',
		'media_buttons' => false,
		'textarea_rows' => 4,
		'teeny'         => true,
		'quicktags'     => array( 'buttons' => 'strong,em,link' ),
	) );
	echo '<p class="description">' . esc_html__( 'Custom message shown when the quiz is answered correctly. Supports bold, italic and links. Leave empty for default.', 'city-core' ) . '</p>';
	echo '</td>';
	echo '</tr>';

	// Reward image (attachment ID shown when quiz answered correctly).
	$reward_image_id  = (int) get_post_meta( $post->ID, 'city_poi_quiz_correct_img', true );
	$reward_image_url = $reward_image_id ? wp_get_attachment_image_url( $reward_image_id, 'medium' ) : '';

	echo '<tr>';
	echo '<th scope="row"><label>' . esc_html__( 'Reward Image', 'city-core' ) . '</label></th>';
	echo '<td>';
	echo '<div class="city-poi-image-picker" data-target="city_poi_quiz_correct_img">';
	echo '<div class="city-poi-image-preview" style="margin-bottom:8px;">';
	if ( $reward_image_url ) {
		echo '<img src="' . esc_url( $reward_image_url ) . '" style="max-width:200px;height:auto;display:block;" />';
	}
	echo '</div>';
	echo '<input type="hidden" name="city_poi_quiz_correct_img" id="city_poi_quiz_correct_img" value="' . esc_attr( $reward_image_id ) . '" />';
	echo '<button type="button" class="button city-poi-image-select">' . esc_html( $reward_image_id ? __( 'Change image', 'city-core' ) : __( 'Select image', 'city-core' ) ) . '</button> ';
	echo '<button type="button" class="button-link city-poi-image-remove" style="color:#a00;' . ( $reward_image_id ? '' : 'display:none;' ) . '">' . esc_html__( 'Remove', 'city-core' ) . '</button>';
	echo '<p class="description">' . esc_html__( 'Optional. Image shown when the quiz is answered correctly. Maps to the Google Sheet column "Imagen recompensa".', 'city-core' ) . '</p>';
	echo '</div>';
	echo '</td>';
	echo '</tr>';

	echo '</table>';
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

	$maps_url   = isset( $_POST['city_poi_maps_url'] ) ? esc_url_raw( $_POST['city_poi_maps_url'] ) : '';
	$lat        = isset( $_POST['city_poi_lat'] ) ? sanitize_text_field( $_POST['city_poi_lat'] ) : '';
	$lng        = isset( $_POST['city_poi_lng'] ) ? sanitize_text_field( $_POST['city_poi_lng'] ) : '';
	$manual_lat = isset( $_POST['city_poi_manual_lat'] ) ? sanitize_text_field( $_POST['city_poi_manual_lat'] ) : '';
	$manual_lng = isset( $_POST['city_poi_manual_lng'] ) ? sanitize_text_field( $_POST['city_poi_manual_lng'] ) : '';
	$tolerance  = isset( $_POST['city_poi_tolerance'] ) ? sanitize_text_field( $_POST['city_poi_tolerance'] ) : 'normal';

	// Validate coordinates are reasonable values.
	if ( ! empty( $lat ) && ( ! is_numeric( $lat ) || $lat < -90 || $lat > 90 ) ) {
		$lat = '';
	}
	if ( ! empty( $lng ) && ( ! is_numeric( $lng ) || $lng < -180 || $lng > 180 ) ) {
		$lng = '';
	}
	if ( ! empty( $manual_lat ) && ( ! is_numeric( $manual_lat ) || $manual_lat < -90 || $manual_lat > 90 ) ) {
		$manual_lat = '';
	}
	if ( ! empty( $manual_lng ) && ( ! is_numeric( $manual_lng ) || $manual_lng < -180 || $manual_lng > 180 ) ) {
		$manual_lng = '';
	}

	// Validate tolerance value.
	if ( ! in_array( $tolerance, array( 'strict', 'normal', 'amplio' ), true ) ) {
		$tolerance = 'normal';
	}

	update_post_meta( $post_id, 'city_poi_maps_url', $maps_url );
	update_post_meta( $post_id, 'city_poi_lat', $lat );
	update_post_meta( $post_id, 'city_poi_lng', $lng );
	update_post_meta( $post_id, 'city_poi_manual_lat', $manual_lat );
	update_post_meta( $post_id, 'city_poi_manual_lng', $manual_lng );
	update_post_meta( $post_id, 'city_poi_tolerance', $tolerance );
}
add_action( 'save_post_poi', 'city_save_poi_coordinates' );

/**
 * Save POI quiz meta.
 *
 * @since 0.3
 *
 * @param int $post_id Post ID.
 */
function city_save_poi_quiz( $post_id ) {
	if ( ! isset( $_POST['city_poi_quiz_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['city_poi_quiz_nonce'], 'city_poi_save_quiz' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$hint     = isset( $_POST['city_poi_hint'] ) ? sanitize_text_field( $_POST['city_poi_hint'] ) : '';
	$question = isset( $_POST['city_poi_quiz_question'] ) ? sanitize_text_field( $_POST['city_poi_quiz_question'] ) : '';
	$answer1  = isset( $_POST['city_poi_quiz_answer_1'] ) ? sanitize_text_field( $_POST['city_poi_quiz_answer_1'] ) : '';
	$answer2  = isset( $_POST['city_poi_quiz_answer_2'] ) ? sanitize_text_field( $_POST['city_poi_quiz_answer_2'] ) : '';
	$answer3  = isset( $_POST['city_poi_quiz_answer_3'] ) ? sanitize_text_field( $_POST['city_poi_quiz_answer_3'] ) : '';
	$correct  = isset( $_POST['city_poi_quiz_correct'] ) ? absint( $_POST['city_poi_quiz_correct'] ) : 0;

	// Validate correct answer (must be 1, 2, or 3).
	if ( ! in_array( $correct, array( 1, 2, 3 ), true ) ) {
		$correct = 0;
	}

	update_post_meta( $post_id, 'city_poi_hint', $hint );
	update_post_meta( $post_id, 'city_poi_quiz_question', $question );
	update_post_meta( $post_id, 'city_poi_quiz_answer_1', $answer1 );
	update_post_meta( $post_id, 'city_poi_quiz_answer_2', $answer2 );
	update_post_meta( $post_id, 'city_poi_quiz_answer_3', $answer3 );
	update_post_meta( $post_id, 'city_poi_quiz_correct', $correct );

	$reward_message = isset( $_POST['city_poi_reward_message'] ) ? wp_kses_post( $_POST['city_poi_reward_message'] ) : '';
	update_post_meta( $post_id, 'city_poi_reward_message', $reward_message );

	$reward_image_id = isset( $_POST['city_poi_quiz_correct_img'] ) ? absint( $_POST['city_poi_quiz_correct_img'] ) : 0;
	update_post_meta( $post_id, 'city_poi_quiz_correct_img', $reward_image_id );
}
add_action( 'save_post_poi', 'city_save_poi_quiz' );

/**
 * Enqueue WP Media + a small inline script on the POI edit screen so the
 * classic Quiz metabox can open the Media Library and pick an image.
 *
 * @since 0.8
 *
 * @param string $hook Current admin page hook.
 */
function city_enqueue_poi_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'poi' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();

	$script = <<<JS
( function ( \$ ) {
	\$( document ).on( 'click', '.city-poi-image-select', function ( e ) {
		e.preventDefault();
		var wrapper = \$( this ).closest( '.city-poi-image-picker' );
		var input   = wrapper.find( 'input[type="hidden"]' );
		var preview = wrapper.find( '.city-poi-image-preview' );
		var remove  = wrapper.find( '.city-poi-image-remove' );
		var frame = wp.media( {
			title: 'Select image',
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var att = frame.state().get( 'selection' ).first().toJSON();
			input.val( att.id );
			preview.html( '<img src="' + att.url + '" style="max-width:200px;height:auto;display:block;" />' );
			remove.show();
			wrapper.find( '.city-poi-image-select' ).text( 'Change image' );
		} );
		frame.open();
	} );
	\$( document ).on( 'click', '.city-poi-image-remove', function ( e ) {
		e.preventDefault();
		var wrapper = \$( this ).closest( '.city-poi-image-picker' );
		wrapper.find( 'input[type="hidden"]' ).val( '' );
		wrapper.find( '.city-poi-image-preview' ).empty();
		\$( this ).hide();
		wrapper.find( '.city-poi-image-select' ).text( 'Select image' );
	} );
} )( jQuery );
JS;

	wp_add_inline_script( 'media-editor', $script );
}
add_action( 'admin_enqueue_scripts', 'city_enqueue_poi_admin_assets' );

/**
 * Keep `city_poi_quiz_correct_img_url` in sync with `city_poi_quiz_correct_img`.
 *
 * Whenever the attachment ID is added or updated (from any vector: classic
 * metabox, Gutenberg sidebar, sheets importer, REST API, WP-CLI...), derive
 * the URL via wp_get_attachment_url() and store it in the auxiliary meta so
 * blocks/dynamic fields that expect a URL (e.g. Stackable Image) work out
 * of the box.
 *
 * @since 0.8
 *
 * @param int    $meta_id    Meta row ID (unused).
 * @param int    $post_id    Post ID.
 * @param string $meta_key   Meta key being updated.
 * @param mixed  $meta_value New meta value (attachment ID).
 */
function city_sync_quiz_correct_img_url( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( 'city_poi_quiz_correct_img' !== $meta_key ) {
		return;
	}
	$attachment_id = (int) $meta_value;
	$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
	update_post_meta( $post_id, 'city_poi_quiz_correct_img_url', $url ? esc_url_raw( $url ) : '' );
}
add_action( 'updated_post_meta', 'city_sync_quiz_correct_img_url', 10, 4 );
add_action( 'added_post_meta',   'city_sync_quiz_correct_img_url', 10, 4 );

/**
 * Clean up the auxiliary URL meta when the ID meta is deleted.
 *
 * @since 0.8
 *
 * @param array  $meta_ids Meta IDs being deleted (unused).
 * @param int    $post_id  Post ID.
 * @param string $meta_key Meta key being deleted.
 */
function city_sync_quiz_correct_img_url_on_delete( $meta_ids, $post_id, $meta_key ) {
	if ( 'city_poi_quiz_correct_img' !== $meta_key ) {
		return;
	}
	delete_post_meta( $post_id, 'city_poi_quiz_correct_img_url' );
}
add_action( 'deleted_post_meta', 'city_sync_quiz_correct_img_url_on_delete', 10, 3 );

/**
 * One-shot migration: backfill `city_poi_quiz_correct_img_url` for POIs that
 * already had an attachment ID before the auxiliary meta existed.
 *
 * Runs once on the first admin pageview after deploy. Subsequent visits are
 * a no-op thanks to the `city_quiz_correct_img_url_migrated` option flag.
 *
 * @since 0.8
 */
function city_migrate_quiz_correct_img_url() {
	if ( ! is_admin() ) {
		return;
	}
	if ( get_option( 'city_quiz_correct_img_url_migrated' ) ) {
		return;
	}

	$pois = get_posts( array(
		'post_type'   => 'poi',
		'numberposts' => -1,
		'meta_key'    => 'city_poi_quiz_correct_img',
		'fields'      => 'ids',
	) );

	foreach ( $pois as $poi_id ) {
		$attachment_id = (int) get_post_meta( $poi_id, 'city_poi_quiz_correct_img', true );
		if ( ! $attachment_id ) {
			continue;
		}
		$url = wp_get_attachment_url( $attachment_id );
		if ( $url ) {
			update_post_meta( $poi_id, 'city_poi_quiz_correct_img_url', esc_url_raw( $url ) );
		}
	}

	update_option( 'city_quiz_correct_img_url_migrated', 1 );
}
add_action( 'admin_init', 'city_migrate_quiz_correct_img_url' );
