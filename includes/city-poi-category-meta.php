<?php
/**
 * POI Category taxonomy meta: color picker and SVG icon.
 *
 * Adds two term meta fields to the "poi-category" taxonomy:
 *  - city_poi_category_color  — hex colour applied to POI marker SVGs on the map.
 *  - city_poi_category_svg    — optional SVG icon pasted by the admin.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Register term meta
// -------------------------------------------------------------------------

/**
 * Registers term meta for poi-category.
 *
 * @since 0.1
 */
function city_register_poi_category_meta() {
	register_term_meta( 'poi-category', 'city_poi_category_color', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => 'sanitize_hex_color',
		'auth_callback'     => function () {
			return current_user_can( 'manage_categories' );
		},
	) );

	register_term_meta( 'poi-category', 'city_poi_category_svg', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		'sanitize_callback' => 'city_sanitize_svg',
		'auth_callback'     => function () {
			return current_user_can( 'manage_categories' );
		},
	) );
}
add_action( 'init', 'city_register_poi_category_meta' );

/**
 * Basic SVG sanitization: removes <script> tags and inline event handlers.
 *
 * Note: this field is admin-only. A full SVG allow-list is not applied
 * to avoid stripping valid SVG attributes; trust the admin user.
 *
 * @since 0.1
 *
 * @param string $svg Raw SVG string.
 * @return string Sanitized SVG string.
 */
function city_sanitize_svg( $svg ) {
	// Remove script blocks.
	$svg = preg_replace( '/<script[\s\S]*?>[\s\S]*?<\/script>/i', '', $svg );
	// Remove inline event handlers (onclick, onload, etc.).
	$svg = preg_replace( '/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\')/i', '', $svg );
	return trim( $svg );
}

// -------------------------------------------------------------------------
// Enqueue colour picker in admin
// -------------------------------------------------------------------------

/**
 * Enqueues the WordPress colour picker on the poi-category screens.
 *
 * @since 0.1
 *
 * @param string $hook Current admin page hook.
 */
function city_enqueue_poi_category_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
	if ( 'poi-category' !== $taxonomy ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script(
		'city-poi-category-admin',
		CITY_CORE_URL . 'assets/js/poi-category-admin.js',
		array( 'wp-color-picker' ),
		CITY_CORE_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'city_enqueue_poi_category_admin_assets' );

// -------------------------------------------------------------------------
// Add form fields — "Add new term" screen
// -------------------------------------------------------------------------

/**
 * Renders term meta fields on the "Add new POI Category" form.
 *
 * @since 0.1
 */
function city_poi_category_add_form_fields() {
	?>
	<div class="form-field">
		<label for="city_poi_category_color"><?php esc_html_e( 'Marker colour', 'city-core' ); ?></label>
		<input type="text" id="city_poi_category_color" name="city_poi_category_color"
			   value="" class="city-color-picker" data-default-color="#00624D">
		<p class="description">
			<?php esc_html_e( 'Colour applied to the POI marker SVGs on the map.', 'city-core' ); ?>
		</p>
	</div>

	<div class="form-field">
		<label for="city_poi_category_svg"><?php esc_html_e( 'Category icon (SVG)', 'city-core' ); ?></label>
		<textarea id="city_poi_category_svg" name="city_poi_category_svg"
				  rows="6" cols="50"
				  placeholder="<?php esc_attr_e( 'Paste your SVG code here. Use fill=&quot;currentColor&quot; so the marker colour applies.', 'city-core' ); ?>"></textarea>
		<p class="description">
			<?php esc_html_e( 'Optional. Replaces the default location icon on unlocked POI markers for this category. Use fill="currentColor".', 'city-core' ); ?>
		</p>
	</div>
	<?php
}
add_action( 'poi-category_add_form_fields', 'city_poi_category_add_form_fields' );

// -------------------------------------------------------------------------
// Edit form fields — "Edit term" screen
// -------------------------------------------------------------------------

/**
 * Renders term meta fields on the "Edit POI Category" form.
 *
 * @since 0.1
 *
 * @param WP_Term $term Current term object.
 */
function city_poi_category_edit_form_fields( $term ) {
	$color = get_term_meta( $term->term_id, 'city_poi_category_color', true );
	$svg   = get_term_meta( $term->term_id, 'city_poi_category_svg', true );
	?>
	<tr class="form-field">
		<th scope="row">
			<label for="city_poi_category_color"><?php esc_html_e( 'Marker colour', 'city-core' ); ?></label>
		</th>
		<td>
			<input type="text" id="city_poi_category_color" name="city_poi_category_color"
				   value="<?php echo esc_attr( $color ); ?>"
				   class="city-color-picker"
				   data-default-color="#00624D">
			<p class="description">
				<?php esc_html_e( 'Colour applied to the POI marker SVGs on the map.', 'city-core' ); ?>
			</p>
		</td>
	</tr>

	<tr class="form-field">
		<th scope="row">
			<label for="city_poi_category_svg"><?php esc_html_e( 'Category icon (SVG)', 'city-core' ); ?></label>
		</th>
		<td>
			<?php if ( $svg ) : ?>
				<div class="city-svg-preview" style="margin-bottom:8px; font-size:32px; color:<?php echo esc_attr( $color ?: '#00624D' ); ?>;">
					<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitized on save ?>
				</div>
			<?php endif; ?>
			<textarea id="city_poi_category_svg" name="city_poi_category_svg"
					  rows="8" cols="60"
					  placeholder="<?php esc_attr_e( 'Paste your SVG code here. Use fill=&quot;currentColor&quot; so the marker colour applies.', 'city-core' ); ?>"><?php echo esc_textarea( $svg ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Optional. Replaces the default location icon on unlocked POI markers for this category. Use fill="currentColor".', 'city-core' ); ?>
			</p>
		</td>
	</tr>
	<?php
}
add_action( 'poi-category_edit_form_fields', 'city_poi_category_edit_form_fields' );

// -------------------------------------------------------------------------
// Save term meta
// -------------------------------------------------------------------------

/**
 * Saves term meta when a POI category is created or updated.
 *
 * @since 0.1
 *
 * @param int $term_id Term ID.
 */
function city_save_poi_category_meta( $term_id ) {
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	if ( isset( $_POST['city_poi_category_color'] ) ) {
		$color = sanitize_hex_color( wp_unslash( $_POST['city_poi_category_color'] ) );
		if ( $color ) {
			update_term_meta( $term_id, 'city_poi_category_color', $color );
		} else {
			delete_term_meta( $term_id, 'city_poi_category_color' );
		}
	}

	if ( isset( $_POST['city_poi_category_svg'] ) ) {
		$svg = city_sanitize_svg( wp_unslash( $_POST['city_poi_category_svg'] ) );
		if ( $svg ) {
			update_term_meta( $term_id, 'city_poi_category_svg', $svg );
		} else {
			delete_term_meta( $term_id, 'city_poi_category_svg' );
		}
	}
}
add_action( 'created_poi-category', 'city_save_poi_category_meta' );
add_action( 'edited_poi-category',  'city_save_poi_category_meta' );
