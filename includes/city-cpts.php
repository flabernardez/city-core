<?php
/**
 * Custom Post Types and Taxonomies for the City project.
 *
 * @package CityCore
 * @since   0.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// CPT: Points of Interest (poi)
// -------------------------------------------------------------------------

/**
 * Registers the Points of Interest custom post type.
 *
 * @since 0.1
 */
function city_register_poi_post_type() {
	$labels = array(
		'name'                  => _x( 'Points of Interest', 'post type general name', 'city-core' ),
		'singular_name'         => _x( 'Point of Interest', 'post type singular name', 'city-core' ),
		'menu_name'             => _x( 'Points of Interest', 'admin menu', 'city-core' ),
		'name_admin_bar'        => _x( 'Point of Interest', 'add new on admin bar', 'city-core' ),
		'add_new'               => __( 'Add New', 'city-core' ),
		'add_new_item'          => __( 'Add New Point of Interest', 'city-core' ),
		'new_item'              => __( 'New Point of Interest', 'city-core' ),
		'edit_item'             => __( 'Edit Point of Interest', 'city-core' ),
		'view_item'             => __( 'View Point of Interest', 'city-core' ),
		'all_items'             => __( 'All Points of Interest', 'city-core' ),
		'search_items'          => __( 'Search Points of Interest', 'city-core' ),
		'parent_item_colon'     => __( 'Parent Points of Interest:', 'city-core' ),
		'not_found'             => __( 'No points of interest found.', 'city-core' ),
		'not_found_in_trash'    => __( 'No points of interest found in Trash.', 'city-core' ),
		'featured_image'        => __( 'Featured Image', 'city-core' ),
		'set_featured_image'    => __( 'Set featured image', 'city-core' ),
		'remove_featured_image' => __( 'Remove featured image', 'city-core' ),
		'use_featured_image'    => __( 'Use as featured image', 'city-core' ),
		'archives'              => __( 'Points of Interest Archives', 'city-core' ),
		'insert_into_item'      => __( 'Insert into point of interest', 'city-core' ),
		'uploaded_to_this_item' => __( 'Uploaded to this point of interest', 'city-core' ),
		'items_list'            => __( 'Points of interest list', 'city-core' ),
		'items_list_navigation' => __( 'Points of interest list navigation', 'city-core' ),
		'filter_items_list'     => __( 'Filter points of interest list', 'city-core' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'poi' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 5,
		'menu_icon'          => 'dashicons-location',
		'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		'show_in_rest'       => true,
	);

	register_post_type( 'poi', $args );
}
add_action( 'init', 'city_register_poi_post_type' );

// -------------------------------------------------------------------------
// Taxonomy: Cities (city)
// -------------------------------------------------------------------------

/**
 * Registers the Cities taxonomy for Points of Interest.
 *
 * @since 0.1
 */
function city_register_city_taxonomy() {
	$labels = array(
		'name'                       => _x( 'Cities', 'taxonomy general name', 'city-core' ),
		'singular_name'              => _x( 'City', 'taxonomy singular name', 'city-core' ),
		'search_items'               => __( 'Search Cities', 'city-core' ),
		'popular_items'              => __( 'Popular Cities', 'city-core' ),
		'all_items'                  => __( 'All Cities', 'city-core' ),
		'parent_item'                => __( 'Parent City', 'city-core' ),
		'parent_item_colon'          => __( 'Parent City:', 'city-core' ),
		'edit_item'                  => __( 'Edit City', 'city-core' ),
		'update_item'                => __( 'Update City', 'city-core' ),
		'add_new_item'               => __( 'Add New City', 'city-core' ),
		'new_item_name'              => __( 'New City Name', 'city-core' ),
		'separate_items_with_commas' => __( 'Separate cities with commas', 'city-core' ),
		'add_or_remove_items'        => __( 'Add or remove cities', 'city-core' ),
		'choose_from_most_used'      => __( 'Choose from the most used cities', 'city-core' ),
		'not_found'                  => __( 'No cities found.', 'city-core' ),
		'no_terms'                   => __( 'No cities', 'city-core' ),
		'menu_name'                  => __( 'Cities', 'city-core' ),
		'items_list_navigation'      => __( 'Cities list navigation', 'city-core' ),
		'items_list'                 => __( 'Cities list', 'city-core' ),
		'back_to_items'              => __( '&larr; Back to Cities', 'city-core' ),
	);

	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'city' ),
		'show_in_rest'      => true,
	);

	register_taxonomy( 'city', array( 'poi' ), $args );
}
add_action( 'init', 'city_register_city_taxonomy' );

// -------------------------------------------------------------------------
// Taxonomy: POI Categories (poi-category)
// -------------------------------------------------------------------------

/**
 * Registers the POI Categories taxonomy for Points of Interest.
 *
 * Uses the slug 'poi-category' to avoid conflicts with the built-in
 * 'category' taxonomy used by posts.
 *
 * @since 0.1
 */
function city_register_poi_category_taxonomy() {
	$labels = array(
		'name'                       => _x( 'POI Categories', 'taxonomy general name', 'city-core' ),
		'singular_name'              => _x( 'POI Category', 'taxonomy singular name', 'city-core' ),
		'search_items'               => __( 'Search POI Categories', 'city-core' ),
		'popular_items'              => __( 'Popular POI Categories', 'city-core' ),
		'all_items'                  => __( 'All POI Categories', 'city-core' ),
		'parent_item'                => __( 'Parent POI Category', 'city-core' ),
		'parent_item_colon'          => __( 'Parent POI Category:', 'city-core' ),
		'edit_item'                  => __( 'Edit POI Category', 'city-core' ),
		'update_item'                => __( 'Update POI Category', 'city-core' ),
		'add_new_item'               => __( 'Add New POI Category', 'city-core' ),
		'new_item_name'              => __( 'New POI Category Name', 'city-core' ),
		'separate_items_with_commas' => __( 'Separate categories with commas', 'city-core' ),
		'add_or_remove_items'        => __( 'Add or remove categories', 'city-core' ),
		'choose_from_most_used'      => __( 'Choose from the most used categories', 'city-core' ),
		'not_found'                  => __( 'No POI categories found.', 'city-core' ),
		'no_terms'                   => __( 'No POI categories', 'city-core' ),
		'menu_name'                  => __( 'POI Categories', 'city-core' ),
		'items_list_navigation'      => __( 'POI categories list navigation', 'city-core' ),
		'items_list'                 => __( 'POI categories list', 'city-core' ),
		'back_to_items'              => __( '&larr; Back to POI Categories', 'city-core' ),
	);

	$args = array(
		'labels'            => $labels,
		'hierarchical'      => true,
		'public'            => true,
		'show_ui'           => true,
		'show_admin_column' => true,
		'query_var'         => true,
		'rewrite'           => array( 'slug' => 'poi-category' ),
		'show_in_rest'      => true,
	);

	register_taxonomy( 'poi-category', array( 'poi' ), $args );
}
add_action( 'init', 'city_register_poi_category_taxonomy' );

// -------------------------------------------------------------------------
// CPT: Maps (map)
// -------------------------------------------------------------------------

/**
 * Registers the Maps custom post type.
 *
 * Each Map post represents a city. Its slug must match a term slug
 * in the "city" taxonomy so the frontend can load the related POIs.
 *
 * @since 0.1
 */
function city_register_map_post_type() {
	$labels = array(
		'name'                  => _x( 'Maps', 'post type general name', 'city-core' ),
		'singular_name'         => _x( 'Map', 'post type singular name', 'city-core' ),
		'menu_name'             => _x( 'Maps', 'admin menu', 'city-core' ),
		'name_admin_bar'        => _x( 'Map', 'add new on admin bar', 'city-core' ),
		'add_new'               => __( 'Add New', 'city-core' ),
		'add_new_item'          => __( 'Add New Map', 'city-core' ),
		'new_item'              => __( 'New Map', 'city-core' ),
		'edit_item'             => __( 'Edit Map', 'city-core' ),
		'view_item'             => __( 'View Map', 'city-core' ),
		'all_items'             => __( 'All Maps', 'city-core' ),
		'search_items'          => __( 'Search Maps', 'city-core' ),
		'parent_item_colon'     => __( 'Parent Map:', 'city-core' ),
		'not_found'             => __( 'No maps found.', 'city-core' ),
		'not_found_in_trash'    => __( 'No maps found in Trash.', 'city-core' ),
		'featured_image'        => __( 'Featured Image', 'city-core' ),
		'set_featured_image'    => __( 'Set featured image', 'city-core' ),
		'remove_featured_image' => __( 'Remove featured image', 'city-core' ),
		'use_featured_image'    => __( 'Use as featured image', 'city-core' ),
		'archives'              => __( 'Maps Archives', 'city-core' ),
		'insert_into_item'      => __( 'Insert into map', 'city-core' ),
		'uploaded_to_this_item' => __( 'Uploaded to this map', 'city-core' ),
		'items_list'            => __( 'Maps list', 'city-core' ),
		'items_list_navigation' => __( 'Maps list navigation', 'city-core' ),
		'filter_items_list'     => __( 'Filter maps list', 'city-core' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'map' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => 6,
		'menu_icon'          => 'dashicons-admin-site-alt3',
		'supports'           => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest'       => true,
	);

	register_post_type( 'map', $args );
}
add_action( 'init', 'city_register_map_post_type' );
