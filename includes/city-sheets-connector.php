<?php
/**
 * Google Sheets Connector for POI Import.
 *
 * Imports POIs from a Google Sheet into the city-core POI CPT.
 *
 * @package CityCore
 * @since   0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handler for POI import.
 *
 * @since 0.3
 */
function city_ajax_import_pois() {
	check_ajax_referer( 'city_import_pois', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	$sheets_url = get_option( 'city_sheets_url', '' );

	if ( empty( $sheets_url ) ) {
		wp_send_json_error( 'Google Sheets URL not configured. Go to Settings > City Core to set it.' );
	}

	// Convert to CSV export URL.
	$csv_url = city_convert_sheets_to_csv( $sheets_url );

	if ( ! $csv_url ) {
		wp_send_json_error( 'Invalid Google Sheets URL' );
	}

	// Fetch CSV data.
	$response = wp_remote_get( $csv_url, array(
		'timeout' => 30,
		'sslverify' => false,
	) );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'Failed to fetch sheet: ' . $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );

	if ( empty( $body ) ) {
		wp_send_json_error( 'Empty response from Google Sheets' );
	}

	// Parse CSV.
	$rows = city_parse_csv( $body );

	if ( empty( $rows ) || count( $rows ) < 2 ) {
		wp_send_json_error( 'No data found in sheet (need header + at least 1 row)' );
	}

	// Parse header row.
	$header = array_shift( $rows );
	$header = array_map( 'trim', $header );
	$header = array_map( 'sanitize_title', $header );

	// Column index mapping (keys are sanitized as slugs).
	$col = array_flip( $header );

	// Validate required columns.
	$required = array( 'nombre', 'ciudad', 'categoria' );
	foreach ( $required as $req ) {
		if ( ! isset( $col[ $req ] ) ) {
			wp_send_json_error( "Missing required column: $req. Found columns: " . implode( ', ', array_keys( $col ) ) );
		}
	}

	// DEBUG: return column mapping so we can see what was parsed.
	$debug_info = array(
		'header' => $header,
		'col_map' => $col,
		'first_row' => ! empty( $rows ) ? $rows[0] : array(),
	);
	set_transient( 'city_import_debug', $debug_info, 300 );

	$results = array(
		'created' => 0,
		'updated' => 0,
		'errors' => 0,
		'log' => array(),
	);

	// DEBUG: log header and first row to see what columns are mapped.
	error_log( 'CITY IMPORT: header=' . print_r( $header, true ) );
	error_log( 'CITY IMPORT: col mapping=' . print_r( $col, true ) );
	if ( ! empty( $rows ) ) {
		error_log( 'CITY IMPORT: first row=' . print_r( $rows[0], true ) );
	}

	foreach ( $rows as $row ) {
		// Ensure row has enough columns.
		$row = array_pad( array_map( 'trim', $row ), count( $header ), '' );

		$result = city_import_single_poi( $header, $row, $col );

		if ( $result['success'] ) {
			if ( $result['created'] ) {
				$results['created']++;
			} else {
				$results['updated']++;
			}
			$results['log'][] = $result['message'];
		} else {
			$results['errors']++;
			$results['log'][] = 'ERROR: ' . $result['message'];
		}
	}

	$summary = sprintf(
		'Import complete: %d created, %d updated, %d errors',
		$results['created'],
		$results['updated'],
		$results['errors']
	);
	$results['log'][] = $summary;

	// Append debug info to response.
	$debug = get_transient( 'city_import_debug' );
	$debug_txt = "\n\nDEBUG INFO:\n" . print_r( $debug, true );

	wp_send_json_success( $summary . "\n\n" . implode( "\n", $results['log'] ) . $debug_txt );
}
add_action( 'wp_ajax_city_import_pois', 'city_ajax_import_pois' );

/**
 * Convert Google Sheets URL to CSV export URL.
 *
 * @since 0.3
 *
 * @param string $url Google Sheets URL.
 * @return string|false CSV URL or false on failure.
 */
function city_convert_sheets_to_csv( $url ) {
	// Pattern: https://docs.google.com/spreadsheets/d/{ID}/edit#gid=0
	if ( preg_match( '/\/spreadsheets\/d\/([a-zA-Z0-9_-]+)/', $url, $matches ) ) {
		$sheet_id = isset( $matches[1] ) ? $matches[1] : '';
		// Export as CSV.
		return "https://docs.google.com/spreadsheets/d/$sheet_id/export?format=csv&gid=0";
	}
	return false;
}

/**
 * Parse CSV string into rows.
 *
 * @since 0.3
 *
 * @param string $csv CSV content.
 * @return array Array of rows (each row is an array of columns).
 */
function city_parse_csv( $csv ) {
	$rows = array();
	$lines = explode( "\n", $csv );

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( empty( $line ) ) {
			continue;
		}

		// Parse CSV line (handles quoted values).
		$row = str_getcsv( $line, ',', '"' );
		if ( ! empty( $row ) ) {
			$rows[] = $row;
		}
	}

	return $rows;
}

/**
 * Import a single POI from sheet row.
 *
 * @since 0.3
 *
 * @param array $header Column headers.
 * @param array $row    Row data.
 * @param array $col    Column index mapping.
 * @return array Result with 'success', 'created', 'message'.
 */
function city_import_single_poi( $header, $row, $col ) {
	// Get values using column indices.
	// Normalize the key: remove accents + sanitize_title so it matches the sanitized header in $col.
	$get = function( $key ) use ( $row, $col ) {
		$normalized = sanitize_title( remove_accents( $key ) );
		$idx = isset( $col[ $normalized ] ) ? $col[ $normalized ] : -1;
		return ( $idx >= 0 && $idx < count( $row ) ) ? $row[ $idx ] : '';
	};

	$nombre     = $get( 'nombre' );
	$imagen     = $get( 'imagen' );
	$info       = $get( 'informacion' );
	$ciudad     = $get( 'ciudad' );
	$categoria  = $get( 'categoria' );
	$geo        = $get( 'geo' );
	$pista      = $get( 'pista' );
	$pregunta   = $get( 'pregunta del quiz' );
	$respuesta1 = $get( 'respuesta1' );
	$respuesta2 = $get( 'respuesta2' );
	$respuesta3 = $get( 'respuesta3' );
	$correcta   = $get( 'correcta' );

	// Validate required fields.
	if ( empty( $nombre ) ) {
		return array( 'success' => false, 'created' => false, 'message' => 'Empty Nombre, skipping' );
	}

	// Sanitize name for slug.
	$slug = sanitize_title( $nombre );

	// Check if POI already exists.
	$existing = get_posts( array(
		'post_type'   => 'poi',
		'name'        => $slug,
		'post_status' => 'any',
		'posts_per_page' => 1,
		'fields' => 'ids',
	) );

	$is_update = ! empty( $existing );
	$poi_id = $is_update ? $existing[0] : 0;

	// Prepare post data.
	$post_data = array(
		'post_type'    => 'poi',
		'post_title'   => wp_strip_all_tags( $nombre ),
		'post_name'    => $slug,
		'post_content' => $info,
		'post_status'  => 'publish',
	);

	if ( $is_update ) {
		$post_data['ID'] = $poi_id;
	}

	// Insert or update post.
	if ( $is_update ) {
		wp_update_post( $post_data );
	} else {
		$poi_id = wp_insert_post( $post_data );
		if ( is_wp_error( $poi_id ) || empty( $poi_id ) ) {
			return array( 'success' => false, 'created' => false, 'message' => 'Failed to create POI: ' . $nombre );
		}
	}

	// Debug: log raw values.
	error_log( 'city-core import DEBUG: ciudad="' . $ciudad . '" categoria="' . $categoria . '"' );

	// Set city taxonomy (create if doesn't exist, or get existing).
	if ( ! empty( $ciudad ) ) {
		$city_slug = sanitize_title( remove_accents( $ciudad ) );
		$city_term = wp_insert_term( $ciudad, 'city', array( 'slug' => $city_slug ) );
		if ( is_wp_error( $city_term ) ) {
			// Term already exists - get it by slug.
			$existing = get_term_by( 'slug', $city_slug, 'city' );
			if ( $existing ) {
				wp_set_object_terms( $poi_id, (int) $existing->term_id, 'city' );
			}
		} else {
			wp_set_object_terms( $poi_id, (int) $city_term['term_id'], 'city' );
		}
	}

	// Set poi-category taxonomy (create if doesn't exist, or get existing).
	if ( ! empty( $categoria ) ) {
		$cat_slug = sanitize_title( remove_accents( $categoria ) );
		$cat_term = wp_insert_term( $categoria, 'poi-category', array( 'slug' => $cat_slug ) );
		if ( is_wp_error( $cat_term ) ) {
			// Term already exists - get it by slug.
			$existing = get_term_by( 'slug', $cat_slug, 'poi-category' );
			if ( $existing ) {
				wp_set_object_terms( $poi_id, (int) $existing->term_id, 'poi-category' );
			}
		} else {
			wp_set_object_terms( $poi_id, (int) $cat_term['term_id'], 'poi-category' );
		}
	}

	// Extract and save coordinates from Geo URL.
	$coords = city_extract_coords_from_maps_url( $geo );
	if ( $coords ) {
		update_post_meta( $poi_id, 'city_poi_maps_url', $geo );
		update_post_meta( $poi_id, 'city_poi_lat', $coords['lat'] );
		update_post_meta( $poi_id, 'city_poi_lng', $coords['lng'] );
	}

	// Save quiz meta fields.
	update_post_meta( $poi_id, 'city_poi_hint', $pista );
	update_post_meta( $poi_id, 'city_poi_quiz_question', $pregunta );
	update_post_meta( $poi_id, 'city_poi_quiz_answer_1', $respuesta1 );
	update_post_meta( $poi_id, 'city_poi_quiz_answer_2', $respuesta2 );
	update_post_meta( $poi_id, 'city_poi_quiz_answer_3', $respuesta3 );
	update_post_meta( $poi_id, 'city_poi_quiz_correct', city_parse_quiz_correct( $correcta, $respuesta1, $respuesta2, $respuesta3 ) );

	// Handle featured image.
	if ( ! empty( $imagen ) ) {
		city_set_featured_image( $poi_id, $imagen );
	}

	$action = $is_update ? 'Updated' : 'Created';
	return array(
		'success' => true,
		'created' => ! $is_update,
		'message' => "$action POI: $nombre (ID: $poi_id)",
	);
}

/**
 * Extract coordinates from Google Maps URL or plain coordinates.
 *
 * @since 0.3
 *
 * @param string $url Google Maps URL or plain coords like "lat,lng" or "lat lng".
 * @return array|false Array with 'lat' and 'lng' or false.
 */
function city_extract_coords_from_maps_url( $url ) {
	if ( empty( $url ) ) {
		return false;
	}

	// Pattern for plain coordinates: "38.3420187,-0.4931912" or "38.3420187 -0.4931912"
	if ( preg_match( '/^(-?\d+\.\d+)[,\s]+(-?\d+\.\d+)$/', trim( $url ), $matches ) ) {
		return array(
			'lat' => (float) $matches[1],
			'lng' => (float) $matches[2],
		);
	}

	// Patterns for Google Maps URLs.
	$patterns = array(
		'/@(-?\d+\.\d+),(-?\d+\.\d+)/',
		'/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/',
		'/place\/[^@]*@(-?\d+\.\d+),(-?\d+\.\d+)/',
	);

	foreach ( $patterns as $pattern ) {
		if ( preg_match( $pattern, $url, $matches ) ) {
			return array(
				'lat' => (float) $matches[1],
				'lng' => (float) $matches[2],
			);
		}
	}

	return false;
}

/**
 * Parse the "Correcta" field from Google Sheets.
 *
 * Sheets sends "Respuesta1", "Respuesta2", "Respuesta3" but we store
 * the answer number (1, 2 or 3) as an integer in post meta.
 *
 * @since 0.4
 *
 * @param string $correcta  The value from the Correcta column.
 * @param string $answer1    Option 1 text.
 * @param string $answer2   Option 2 text.
 * @param string $answer3   Option 3 text.
 * @return int Answer number (1, 2 or 3). Defaults to 0 on failure.
 */
function city_parse_quiz_correct( $correcta, $answer1, $answer2, $answer3 ) {
	// Normalise: strip whitespace and lowercase.
	$correcta = trim( strtolower( $correcta ) );

	// Direct number: "1", "2", "3".
	if ( is_numeric( $correcta ) ) {
		return (int) $correcta;
	}

	// Text match against each option.
	// "Respuesta1" → 1, "Respuesta2" → 2, "Respuesta3" → 3.
	if ( preg_match( '/respuesta\s*(\d)/i', $correcta, $m ) ) {
		$n = (int) $m[1];
		if ( $n >= 1 && $n <= 3 ) {
			return $n;
		}
	}

	// Match by comparing the correct answer text against each option.
	$options = array(
		1 => trim( strtolower( $answer1 ) ),
		2 => trim( strtolower( $answer2 ) ),
		3 => trim( strtolower( $answer3 ) ),
	);

	foreach ( $options as $num => $text ) {
		if ( ! empty( $text ) && strpos( $correcta, $text ) !== false ) {
			return $num;
		}
	}

	return 0;
}

/**
 * Set featured image from URL.
 *
 * @since 0.3
 *
 * @param int    $post_id Post ID.
 * @param string $image_url Image URL.
 */
function city_set_featured_image( $post_id, $image_url ) {
	if ( empty( $image_url ) ) {
		return;
	}

	// Check if image already exists in media library by URL.
	$image_id = city_get_image_id_by_url( $image_url );

	if ( ! $image_id ) {
		// Download and import image.
		$image_id = media_sideload_image( $image_url, $post_id, null, 'id' );
	}

	if ( ! is_wp_error( $image_id ) && ! empty( $image_id ) ) {
		set_post_thumbnail( $post_id, $image_id );
	}
}

/**
 * Get image ID by URL.
 *
 * @since 0.3
 *
 * @param string $url Image URL.
 * @return int|false Image ID or false.
 */
function city_get_image_id_by_url( $url ) {
	global $wpdb;

	$attachment = $wpdb->get_col( $wpdb->prepare(
		"SELECT ID FROM $wpdb->posts WHERE guid='%s' AND post_type='attachment'",
		esc_url( $url )
	) );

	return ! empty( $attachment ) ? (int) $attachment[0] : false;
}
