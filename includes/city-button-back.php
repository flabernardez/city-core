<?php
/**
 * Back Button functionality for City Core
 * Allow pages to set custom back button destination
 *
 * @since 0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add meta box to pages for back button settings.
 */
function city_back_add_meta_box() {
	foreach ( array( 'page', 'poi', 'map' ) as $post_type ) {
		add_meta_box(
			'city_back_button_settings',
			__( 'Botón Atrás', 'city-core' ),
			'city_back_meta_box_callback',
			$post_type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'city_back_add_meta_box' );

/**
 * Meta box callback function.
 */
function city_back_meta_box_callback( $post ) {
	wp_nonce_field( 'city_back_save_meta_box_data', 'city_back_meta_box_nonce' );

	$selected_page = get_post_meta( $post->ID, '_city_back_page_id', true );

	$pages = get_posts( array(
		'post_type'      => array( 'page', 'map' ),
		'posts_per_page' => -1,
		'orderby'       => 'title',
		'order'         => 'ASC',
		'post_status'   => 'publish',
	) );

	echo '<label for="city_back_page_id">' . esc_html__( 'Destino del botón "Atrás":', 'city-core' ) . '</label><br>';
	echo '<select id="city_back_page_id" name="city_back_page_id" style="width: 100%; margin-top: 10px;">';
	echo '<option value="">' . esc_html__( '-- Comportamiento por defecto --', 'city-core' ) . '</option>';
	echo '<option value="history_back" ' . selected( $selected_page, 'history_back', false ) . '>' . esc_html__( '← Página anterior (Historial)', 'city-core' ) . '</option>';

	foreach ( $pages as $page ) {
		echo '<option value="' . esc_attr( $page->ID ) . '" ' . selected( $selected_page, $page->ID, false ) . '>' . esc_html( $page->post_title ) . '</option>';
	}

	echo '</select>';
	echo '<p class="description" style="margin-top: 10px;">' . esc_html__( 'Selecciona la página a la que debe ir el botón "Atrás" del header.', 'city-core' ) . '</p>';
}

/**
 * Save meta box data.
 */
function city_back_save_meta_box_data( $post_id ) {
	if ( ! isset( $_POST['city_back_meta_box_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['city_back_meta_box_nonce'], 'city_back_save_meta_box_data' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}

	$back_page_id = isset( $_POST['city_back_page_id'] ) ? sanitize_text_field( $_POST['city_back_page_id'] ) : '';

	update_post_meta( $post_id, '_city_back_page_id', $back_page_id );
}
add_action( 'save_post', 'city_back_save_meta_box_data' );

/**
 * Start output buffering to capture entire page HTML.
 */
function city_back_start_buffer() {
	if ( is_admin() ) {
		return;
	}
	ob_start( 'city_back_process_buffer' );
}
add_action( 'template_redirect', 'city_back_start_buffer', 1 );

/**
 * Process the buffered HTML to inject href into links inside .city-back.
 */
function city_back_process_buffer( $html ) {
	if ( empty( $html ) || false === strpos( $html, 'city-back' ) ) {
		return $html;
	}

	$post_id = get_queried_object_id();

	if ( ! $post_id ) {
		return $html;
	}

	$back_page_id = get_post_meta( $post_id, '_city_back_page_id', true );

	if ( empty( $back_page_id ) ) {
		return $html;
	}

	$aria_label = __( 'Volver atrás', 'city-core' );
	$sr_text    = __( 'Volver a la página anterior', 'city-core' );

	if ( 'history_back' === $back_page_id ) {
		$back_url = 'javascript:history.back()';
	} else {
		$back_url  = get_permalink( $back_page_id );
		$back_title = get_the_title( $back_page_id );

		if ( ! $back_url ) {
			return $html;
		}

		$sr_text = __( 'Volver a: ', 'city-core' ) . $back_title;
	}

	// Pattern: <div class="...city-back..."> ... <a href="..."> ... </a> ... </div>
	$pattern = '/(<div[^>]*class="[^"]*\bcity-back\b[^"]*"[^>]*>)(.*?)(<a\s+[^>]*)(href=")([^"]*)("[^>]*>)(.*?)(<\/a>)(.*?)(<\/div>)/si';

	$html = preg_replace_callback(
		$pattern,
		function( $matches ) use ( $back_url, $aria_label, $sr_text ) {
			$div_open   = $matches[1];
			$before_a   = $matches[2];
			$a_start    = $matches[3];
			$old_href   = $matches[5];
			$a_rest     = $matches[6];
			$a_content  = $matches[7];
			$a_close    = $matches[8];
			$after_a    = $matches[9];
			$div_close  = $matches[10];

			// Remove existing aria-label.
			$a_start = preg_replace( '/\s+aria-label="[^"]*"/', '', $a_start );
			$a_rest  = preg_replace( '/\s+aria-label="[^"]*"/', '', $a_rest );

			// Build new link with attributes.
			$new_a    = $a_start . 'href="' . esc_attr( $back_url ) . '" aria-label="' . esc_attr( $aria_label ) . '"' . $a_rest;
			$sr_span  = '<span class="screen-reader-text">' . esc_html( $sr_text ) . '</span>';

			return $div_open . $before_a . $new_a . $a_content . $sr_span . $a_close . $after_a . $div_close;
		},
		$html
	);

	return $html;
}

/**
 * Add JavaScript for keyboard support when using history back.
 */
function city_back_add_functionality() {
	if ( ! is_page() ) {
		return;
	}

	$post_id       = get_the_ID();
	$back_page_id  = get_post_meta( $post_id, '_city_back_page_id', true );

	if ( empty( $back_page_id ) || 'history_back' !== $back_page_id ) {
		return;
	}

	?>
	<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var backContainers = document.querySelectorAll( '.city-back' );

			backContainers.forEach( function( container ) {
				var link = container.querySelector( 'a' );
				if ( link ) {
					link.addEventListener( 'keydown', function( e ) {
						if ( e.key === 'Enter' || e.key === ' ' ) {
							e.preventDefault();
							window.history.back();
						}
					} );
				}
			} );
		} );
	</script>
	<?php
}
add_action( 'wp_footer', 'city_back_add_functionality' );

/**
 * Add CSS for screen reader text and focus styles.
 */
function city_back_add_accessibility_css() {
	?>
	<style>
		.screen-reader-text {
			position: absolute !important;
			width: 1px !important;
			height: 1px !important;
			padding: 0 !important;
			margin: -1px !important;
			overflow: hidden !important;
			clip: rect(0, 0, 0, 0) !important;
			white-space: nowrap !important;
			border: 0 !important;
		}

		.city-back a:focus {
			outline: 2px solid var( --wp--preset--color--accent, #00ff88 );
			outline-offset: 2px;
		}

		.city-back a:focus:not( :focus-visible ) {
			outline: none;
		}

		.city-back a:focus-visible {
			outline: 2px solid var( --wp--preset--color--accent, #00ff88 );
			outline-offset: 2px;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'city_back_add_accessibility_css' );
