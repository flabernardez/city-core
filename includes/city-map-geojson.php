<?php
/**
 * REST API endpoint that returns POIs as GeoJSON.
 *
 * Endpoint: GET /wp-json/city-core/v1/pois?city={slug}
 *
 * Returns a GeoJSON FeatureCollection with Point features for every
 * published POI that belongs to the given city taxonomy term and has
 * valid latitude/longitude meta.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the POIs REST route.
 *
 * @since 0.1
 */
function city_register_pois_rest_route() {
	register_rest_route( 'city-core/v1', '/pois', array(
		'methods'             => 'GET',
		'callback'            => 'city_get_pois_geojson',
		'permission_callback' => '__return_true',
		'args'                => array(
			'city' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_title',
				'description'       => __( 'City taxonomy term slug.', 'city-core' ),
			),
		),
	) );
}
add_action( 'rest_api_init', 'city_register_pois_rest_route' );

/**
 * Returns a GeoJSON FeatureCollection for POIs in the given city.
 *
 * @since 0.1
 *
 * @param WP_REST_Request $request REST request object.
 * @return WP_REST_Response GeoJSON response.
 */
function city_get_pois_geojson( $request ) {
	$city_slug = $request->get_param( 'city' );

	$query = new WP_Query( array(
		'post_type'      => 'poi',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => 'city',
				'field'    => 'slug',
				'terms'    => $city_slug,
			),
		),
	) );

	$features = array();

	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();

			$lat = get_post_meta( get_the_ID(), 'city_poi_lat', true );
			$lng = get_post_meta( get_the_ID(), 'city_poi_lng', true );

			if ( '' === $lat || '' === $lng ) {
				continue;
			}

			$features[] = array(
				'type'       => 'Feature',
				'geometry'   => array(
					'type'        => 'Point',
					'coordinates' => array( (float) $lng, (float) $lat ),
				),
				'properties' => array(
					'name' => get_the_title(),
					'slug' => get_post_field( 'post_name', get_the_ID() ),
					'url'  => get_permalink(),
				),
			);
		}
		wp_reset_postdata();
	}

	$geojson = array(
		'type'     => 'FeatureCollection',
		'features' => $features,
	);

	return new WP_REST_Response( $geojson, 200 );
}
