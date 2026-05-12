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

	// Pre-pass consistency validation: abort if Ciudad/Categoria values have
	// duplicate variants or likely typos. This prevents accidental duplicate
	// terms from being auto-created by wp_insert_term().
	$validation = city_validate_sheet_consistency( $rows, $col );
	if ( ! empty( $validation['errors'] ) ) {
		$msg = __( 'Import aborted. Please fix these inconsistencies in your Google Sheet and try again:', 'city-core' ) . "\n\n"
			 . implode( "\n", $validation['errors'] );
		wp_send_json_error( $msg );
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
 * AJAX handler for POI import PREVIEW (dry-run).
 *
 * Downloads and parses the Google Sheet CSV, runs consistency validation,
 * and returns a JSON payload with header, sample rows, totals, and the list
 * of new cities/categories that would be created. Does NOT touch the DB.
 *
 * @since 0.8
 */
function city_ajax_preview_pois() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	if ( ! check_ajax_referer( 'city_import_pois', 'nonce', false ) ) {
		wp_send_json_error( 'Invalid nonce' );
	}

	$sheets_url = get_option( 'city_sheets_url', '' );
	if ( empty( $sheets_url ) ) {
		wp_send_json_error( 'No Google Sheets URL configured. Save the URL first.' );
	}

	$csv_url = city_convert_sheets_to_csv( $sheets_url );
	if ( ! $csv_url ) {
		wp_send_json_error( 'Could not derive CSV export URL from the Google Sheets URL.' );
	}

	$response = wp_remote_get( $csv_url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) {
		wp_send_json_error( 'Failed to fetch sheet: ' . $response->get_error_message() );
	}

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) {
		wp_send_json_error( 'Empty response from Google Sheets.' );
	}

	$rows = city_parse_csv( $body );
	if ( empty( $rows ) || count( $rows ) < 2 ) {
		wp_send_json_error( 'No data found in sheet (need header + at least 1 row).' );
	}

	// Header parsing (same as import).
	$header_raw = array_shift( $rows );
	$header     = array_map( 'sanitize_title', array_map( 'trim', $header_raw ) );
	$col        = array_flip( $header );

	// Required columns check.
	$required = array( 'nombre', 'ciudad', 'categoria' );
	$missing  = array();
	foreach ( $required as $req ) {
		if ( ! isset( $col[ $req ] ) ) {
			$missing[] = $req;
		}
	}
	if ( ! empty( $missing ) ) {
		wp_send_json_error( 'Missing required columns: ' . implode( ', ', $missing ) . '. Found: ' . implode( ', ', $header ) );
	}

	// Consistency validation (variants and typos).
	$validation = city_validate_sheet_consistency( $rows, $col );

	// Build a sample of the first 10 rows mapped column → value (use raw header for display).
	$sample      = array();
	$sample_size = min( 10, count( $rows ) );
	for ( $i = 0; $i < $sample_size; $i++ ) {
		$row    = array_pad( array_map( 'trim', $rows[ $i ] ), count( $header ), '' );
		$mapped = array();
		foreach ( $header_raw as $idx => $col_name ) {
			$mapped[ trim( $col_name ) ] = isset( $row[ $idx ] ) ? $row[ $idx ] : '';
		}
		$sample[] = $mapped;
	}

	// Collect unique cities & categories that would be created.
	$cities_seen     = array();
	$categories_seen = array();
	$ciudad_idx      = isset( $col['ciudad'] ) ? (int) $col['ciudad'] : -1;
	$categoria_idx   = isset( $col['categoria'] ) ? (int) $col['categoria'] : -1;
	foreach ( $rows as $row ) {
		if ( $ciudad_idx >= 0 && isset( $row[ $ciudad_idx ] ) ) {
			$v = trim( $row[ $ciudad_idx ] );
			if ( '' !== $v ) {
				$cities_seen[ $v ] = true;
			}
		}
		if ( $categoria_idx >= 0 && isset( $row[ $categoria_idx ] ) ) {
			$v = trim( $row[ $categoria_idx ] );
			if ( '' !== $v ) {
				$categories_seen[ $v ] = true;
			}
		}
	}

	$cities_list     = array_keys( $cities_seen );
	$categories_list = array_keys( $categories_seen );

	// Which of those cities/categories don't exist yet?
	$new_cities = array();
	foreach ( $cities_list as $name ) {
		$slug = sanitize_title( remove_accents( $name ) );
		if ( ! get_term_by( 'slug', $slug, 'city' ) ) {
			$new_cities[] = $name;
		}
	}
	$new_categories = array();
	foreach ( $categories_list as $name ) {
		$slug = sanitize_title( remove_accents( $name ) );
		if ( ! get_term_by( 'slug', $slug, 'poi-category' ) ) {
			$new_categories[] = $name;
		}
	}

	wp_send_json_success( array(
		'header'                 => array_values( array_map( 'trim', $header_raw ) ),
		'total_rows'             => count( $rows ),
		'sample'                 => $sample,
		'errors'                 => $validation['errors'],
		'cities_in_sheet'        => $cities_list,
		'categories_in_sheet'    => $categories_list,
		'new_cities'             => $new_cities,
		'new_categories'         => $new_categories,
	) );
}
add_action( 'wp_ajax_city_preview_pois', 'city_ajax_preview_pois' );

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

	// Use fgetcsv over a temp stream so multi-line cells (quoted text with
	// embedded newlines) are preserved as a single field. Splitting on "\n"
	// would corrupt rows whenever a cell contains a line break.
	$handle = fopen( 'php://temp', 'r+' );
	if ( ! $handle ) {
		return $rows;
	}

	fwrite( $handle, $csv );
	rewind( $handle );

	while ( ( $row = fgetcsv( $handle, 0, ',', '"', '\\' ) ) !== false ) {
		// fgetcsv returns array( null ) for empty lines — skip them.
		if ( count( $row ) === 1 && ( null === $row[0] || '' === trim( (string) $row[0] ) ) ) {
			continue;
		}
		$rows[] = $row;
	}

	fclose( $handle );

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
	$correcta           = $get( 'correcta' );
	$reward_message     = $get( 'recompensa del quizz' );
	$imagen_recompensa  = $get( 'imagen recompensa' );

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

		// Ensure a `map` CPT post exists for this city so the /map/{slug}/
		// page works out of the box when a new city appears in the sheet.
		city_ensure_map_for_city( $city_slug, $ciudad );
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

	if ( ! empty( $reward_message ) ) {
		update_post_meta( $poi_id, 'city_poi_reward_message', sanitize_text_field( $reward_message ) );
	}

	// Handle featured image.
	if ( ! empty( $imagen ) ) {
		city_set_featured_image( $poi_id, $imagen );
	}

	// Handle reward image (Imagen recompensa) — saved as attachment ID in city_poi_quiz_correct_img meta.
	if ( ! empty( $imagen_recompensa ) ) {
		$reward_image_id = city_get_image_id_by_url( $imagen_recompensa );
		if ( ! $reward_image_id ) {
			$reward_image_id = media_sideload_image( $imagen_recompensa, $poi_id, null, 'id' );
		}
		if ( ! is_wp_error( $reward_image_id ) && ! empty( $reward_image_id ) ) {
			update_post_meta( $poi_id, 'city_poi_quiz_correct_img', (int) $reward_image_id );
		}
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

/**
 * Pre-pass validation: detect inconsistent or near-duplicate values in the
 * Ciudad / Categoria columns before importing.
 *
 * Catches two scenarios:
 *   1. "Exact variants" — same normalized slug but different raw values
 *      (e.g. "Cádiz" vs "cadiz"). Likely intended to be the same term.
 *   2. "Typos" — different normalized slugs with Levenshtein distance <= 2
 *      and both at least 4 characters long (e.g. "Restaurante" vs "Restaurnte").
 *
 * Returns an array with an `errors` key listing human-readable messages.
 * Empty array means the data is consistent.
 *
 * @since 0.8
 *
 * @param array $rows Parsed CSV rows (first row is the header).
 * @param array $col  Column index map (slug → index).
 * @return array { 'errors' => string[] }
 */
function city_validate_sheet_consistency( $rows, $col ) {
	$errors = array();

	$ciudad_idx    = isset( $col['ciudad'] ) ? (int) $col['ciudad'] : -1;
	$categoria_idx = isset( $col['categoria'] ) ? (int) $col['categoria'] : -1;

	$city_groups     = array();
	$category_groups = array();

	// $rows comes from the caller without the header row (already shifted).
	foreach ( $rows as $row ) {
		if ( $ciudad_idx >= 0 && isset( $row[ $ciudad_idx ] ) ) {
			$raw = trim( $row[ $ciudad_idx ] );
			if ( '' !== $raw ) {
				$slug = sanitize_title( remove_accents( $raw ) );
				if ( $slug ) {
					$city_groups[ $slug ][] = $raw;
				}
			}
		}

		if ( $categoria_idx >= 0 && isset( $row[ $categoria_idx ] ) ) {
			$raw = trim( $row[ $categoria_idx ] );
			if ( '' !== $raw ) {
				$slug = sanitize_title( remove_accents( $raw ) );
				if ( $slug ) {
					$category_groups[ $slug ][] = $raw;
				}
			}
		}
	}

	$check_groups = function ( $groups, $label ) use ( &$errors ) {
		// 1. Exact variants: same slug, different raw values.
		foreach ( $groups as $slug => $raws ) {
			$unique = array_values( array_unique( $raws ) );
			if ( count( $unique ) > 1 ) {
				$errors[] = sprintf(
					/* translators: 1: column label (Ciudad/Categoria), 2: comma-separated list of variants */
					__( '%1$s: "%2$s" se escriben de forma distinta pero apuntan al mismo termino. Unifica la escritura (mayusculas, acentos) en todas las filas.', 'city-core' ),
					$label,
					implode( '", "', $unique )
				);
			}
		}

		// 2. Typos: different slugs with small Levenshtein distance.
		$slugs = array_keys( $groups );
		$count = count( $slugs );
		for ( $i = 0; $i < $count; $i++ ) {
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$a = $slugs[ $i ];
				$b = $slugs[ $j ];
				$shorter = min( strlen( $a ), strlen( $b ) );
				if ( $shorter < 4 ) {
					continue;
				}
				$dist = levenshtein( $a, $b );
				if ( $dist > 0 && $dist <= 2 ) {
					// Pick one representative raw value per slug for the message.
					$a_raw = $groups[ $a ][0];
					$b_raw = $groups[ $b ][0];
					$errors[] = sprintf(
						/* translators: 1: column label, 2: value A, 3: value B */
						__( '%1$s: "%2$s" y "%3$s" parecen variantes con un posible error tipografico. Revisa la escritura.', 'city-core' ),
						$label,
						$a_raw,
						$b_raw
					);
				}
			}
		}
	};

	$check_groups( $city_groups, __( 'Ciudad', 'city-core' ) );
	$check_groups( $category_groups, __( 'Categoria', 'city-core' ) );

	// 3. Validate Geo column: check that non-empty values can be parsed as coordinates.
	$geo_idx    = isset( $col['geo'] ) ? (int) $col['geo'] : -1;
	$nombre_idx = isset( $col['nombre'] ) ? (int) $col['nombre'] : -1;

	if ( $geo_idx >= 0 ) {
		foreach ( $rows as $i => $row ) {
			$geo_raw = isset( $row[ $geo_idx ] ) ? trim( $row[ $geo_idx ] ) : '';
			if ( '' === $geo_raw ) {
				continue; // Empty Geo is OK (optional, can be set manually).
			}
			$coords = city_extract_coords_from_maps_url( $geo_raw );
			if ( false === $coords ) {
				$poi_name = ( $nombre_idx >= 0 && isset( $row[ $nombre_idx ] ) )
					? trim( $row[ $nombre_idx ] )
					: sprintf( 'row %d', $i + 2 );
				$errors[] = sprintf(
					/* translators: 1: raw Geo value, 2: POI name or row number */
					__( 'Geo: "%1$s" (fila "%2$s") no tiene un formato de coordenadas reconocible. Usa "lat,lng", "lat lng" o una URL de Google Maps.', 'city-core' ),
					$geo_raw,
					$poi_name
				);
			}
		}
	}

	return array( 'errors' => $errors );
}

/**
 * Ensure there is a `map` CPT post for the given city slug. Creates one with
 * the city-core/map block as initial content if it doesn't exist yet.
 *
 * @since 0.8
 *
 * @param string $slug  City slug (post_name for the map).
 * @param string $title Raw city name (post_title).
 * @return int|false Map post ID, or false on failure / if it already existed.
 */
function city_ensure_map_for_city( $slug, $title ) {
	$slug = sanitize_title( $slug );
	if ( '' === $slug ) {
		return false;
	}

	$existing = get_posts( array(
		'post_type'   => 'map',
		'name'        => $slug,
		'numberposts' => 1,
		'post_status' => 'any',
		'fields'      => 'ids',
	) );

	if ( ! empty( $existing ) ) {
		return false; // Already exists, nothing to do.
	}

	$post_id = wp_insert_post( array(
		'post_type'    => 'map',
		'post_status'  => 'publish',
		'post_name'    => $slug,
		'post_title'   => $title ? $title : $slug,
		'post_content' => '<!-- wp:city-core/map /-->',
	) );

	return is_wp_error( $post_id ) ? false : (int) $post_id;
}
