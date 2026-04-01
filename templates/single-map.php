<?php
/**
 * Template: Single Map.
 *
 * Full-screen Leaflet map with POI markers for the associated city.
 * The Leaflet library, inline CSS, and JS are enqueued/output by
 * city-map-frontend.php via wp_head / wp_footer hooks.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="city-map-wrapper">
	<div id="city-map"></div>

	<div class="city-map-button">
		<div class="wp-block-button with-icon">
			<a id="city-center-user-btn"
			   class="wp-block-button__link wp-element-button"
			   href="#">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
					 width="18" height="18" fill="currentColor" aria-hidden="true">
					<path d="M256 32C167.67 32 96 96.51 96 176c0 128 160 304 160 304s160-176 160-304c0-79.49-71.67-144-160-144m0 224a64 64 0 1 1 64-64 64.07 64.07 0 0 1-64 64"></path>
				</svg>
				<span><?php esc_html_e( 'Update position', 'city-core' ); ?></span>
			</a>
		</div>
	</div>
</div>

<?php
get_footer();
