<?php
/**
 * City Core Settings.
 *
 * Settings page for Google Sheets integration and POI import.
 *
 * @package CityCore
 * @since   0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add settings page under Settings menu.
 *
 * @since 0.3
 */
function city_add_settings_page() {
	add_options_page(
		__( 'City Core Settings', 'city-core' ),
		__( 'City Core', 'city-core' ),
		'manage_options',
		'city-core-settings',
		'city_render_settings_page'
	);
}
add_action( 'admin_menu', 'city_add_settings_page' );

/**
 * Register settings.
 *
 * @since 0.3
 */
function city_register_settings() {
	register_setting( 'city_settings_group', 'city_sheets_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url',
	) );

	// Quiz color options.
	$color_options = array(
		'city_quiz_color_bg',
		'city_quiz_color_border',
		'city_quiz_color_question',
		'city_quiz_color_letter_bg',
		'city_quiz_color_correct_bg',
		'city_quiz_color_correct_border',
		'city_quiz_color_incorrect_bg',
		'city_quiz_color_incorrect_border',
		'city_quiz_color_option_text',
		'city_quiz_color_feedback_success',
		'city_quiz_color_feedback_error',
		// Map color options.
		'city_map_color_button',
		'city_map_color_button_text',
		'city_map_color_category_active',
		'city_map_color_locked',
		'city_map_color_button_bg',
		'city_map_color_indicator_bg',
		'city_map_color_popup_border',
		'city_map_color_popup_bg',
		'city_map_color_popup_text',
	);
	foreach ( $color_options as $opt ) {
		register_setting( 'city_settings_group', $opt, array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
		) );
	}

	// Boolean toggle: use category color for locked POIs.
	register_setting( 'city_settings_group', 'city_map_locked_use_category', array(
		'type'              => 'boolean',
		'sanitize_callback' => 'rest_sanitize_boolean',
	) );
}
add_action( 'admin_init', 'city_register_settings' );

/**
 * Get quiz color options with defaults.
 *
 * @since 0.8
 *
 * @return array Associative array of quiz colors.
 */
function city_get_quiz_colors() {
	return array(
		'bg'                => get_option( 'city_quiz_color_bg', '#f8f5ea' ),
		'border'            => get_option( 'city_quiz_color_border', '#00624d' ),
		'question'          => get_option( 'city_quiz_color_question', '#18302b' ),
		'letter_bg'         => get_option( 'city_quiz_color_letter_bg', '#00624d' ),
		'correct_bg'        => get_option( 'city_quiz_color_correct_bg', '#d4edda' ),
		'correct_border'    => get_option( 'city_quiz_color_correct_border', '#28a745' ),
		'incorrect_bg'      => get_option( 'city_quiz_color_incorrect_bg', '#f8d7da' ),
		'incorrect_border'  => get_option( 'city_quiz_color_incorrect_border', '#dc3545' ),
		'option_text'       => get_option( 'city_quiz_color_option_text', '#18302b' ),
		'feedback_success'  => get_option( 'city_quiz_color_feedback_success', '#155724' ),
		'feedback_error'    => get_option( 'city_quiz_color_feedback_error', '#721c24' ),
	);
}

/**
 * Get map color options with defaults.
 *
 * @since 0.8
 *
 * @return array Associative array of map colors.
 */
function city_get_map_colors() {
	return array(
		'button'          => get_option( 'city_map_color_button', '#00624D' ),
		'button_text'     => get_option( 'city_map_color_button_text', '#063b2f' ),
		'category_active' => get_option( 'city_map_color_category_active', '#e07722' ),
		'locked'          => get_option( 'city_map_color_locked', '#888888' ),
		'button_bg'       => get_option( 'city_map_color_button_bg', '#ffffff' ),
		'indicator_bg'    => get_option( 'city_map_color_indicator_bg', '#ffffff' ),
		'popup_border'    => get_option( 'city_map_color_popup_border', '#000000' ),
		'popup_bg'        => get_option( 'city_map_color_popup_bg', '#ffffff' ),
		'popup_text'      => get_option( 'city_map_color_popup_text', '#000000' ),
	);
}

/**
 * Convert hex color to rgba string.
 *
 * @since 0.8
 *
 * @param string $hex   Hex color (e.g. '#00624D').
 * @param float  $alpha Alpha value 0-1.
 * @return string rgba() CSS value.
 */
function city_hex_to_rgba( $hex, $alpha = 1 ) {
	$hex = ltrim( $hex, '#' );
	$r   = hexdec( substr( $hex, 0, 2 ) );
	$g   = hexdec( substr( $hex, 2, 2 ) );
	$b   = hexdec( substr( $hex, 4, 2 ) );
	return "rgba($r,$g,$b,$alpha)";
}

/**
 * Get the theme color palette as a flat list of hex values.
 *
 * Handles both the origin-based structure (block themes, WP 5.9+) and the
 * flat legacy format. Prefers theme + custom colors, skips core defaults.
 *
 * @since 0.8
 *
 * @return array List of unique hex color strings.
 */
function city_get_theme_palette() {
	$theme_palette = wp_get_global_settings( array( 'color', 'palette' ) );
	$theme_colors  = array();

	if ( is_array( $theme_palette ) ) {
		if ( isset( $theme_palette['theme'] ) || isset( $theme_palette['custom'] ) || isset( $theme_palette['default'] ) ) {
			$sources = array();
			if ( ! empty( $theme_palette['theme'] ) && is_array( $theme_palette['theme'] ) ) {
				$sources[] = $theme_palette['theme'];
			}
			if ( ! empty( $theme_palette['custom'] ) && is_array( $theme_palette['custom'] ) ) {
				$sources[] = $theme_palette['custom'];
			}
			foreach ( $sources as $source ) {
				foreach ( $source as $entry ) {
					if ( is_array( $entry ) && ! empty( $entry['color'] ) ) {
						$theme_colors[] = $entry['color'];
					}
				}
			}
		} else {
			foreach ( $theme_palette as $entry ) {
				if ( is_array( $entry ) && ! empty( $entry['color'] ) ) {
					$theme_colors[] = $entry['color'];
				}
			}
		}
	}

	return array_values( array_unique( $theme_colors ) );
}

/**
 * Enqueue color picker on settings page.
 *
 * @since 0.8
 *
 * @param string $hook_suffix Admin page hook suffix.
 */
function city_enqueue_settings_scripts( $hook_suffix ) {
	if ( 'settings_page_city-core-settings' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wp-color-picker' );
	wp_enqueue_style(
		'city-color-picker-style',
		CITY_CORE_URL . 'assets/css/city-color-picker.css',
		array( 'wp-color-picker' ),
		CITY_CORE_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'city_enqueue_settings_scripts' );

/**
 * Render settings page.
 *
 * @since 0.3
 */
function city_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$sheets_url = get_option( 'city_sheets_url', '' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Google Sheets Integration', 'city-core' ); ?></strong></p>
			<p><?php esc_html_e( 'Enter the public URL of your Google Sheets spreadsheet to import Points of Interest.', 'city-core' ); ?></p>
			<p><?php esc_html_e( 'The sheet must have these columns: Nombre, Imagen, Información, Ciudad, Categoría, Geo, Pista, Pregunta del Quiz, Respuesta1, Respuesta2, Respuesta3, Correcta, Recompensa del quizz, Imagen recompensa', 'city-core' ); ?></p>
		</div>

		<form method="post" action="options.php">
			<?php
			settings_fields( 'city_settings_group' );
			do_settings_sections( 'city_settings_group' );
			?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="city_sheets_url"><?php esc_html_e( 'Google Sheets URL', 'city-core' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							id="city_sheets_url"
							name="city_sheets_url"
							value="<?php echo esc_attr( $sheets_url ); ?>"
							class="regular-text"
							placeholder="https://docs.google.com/spreadsheets/d/..."
							style="width: 100%; max-width: 600px;"
						/>
						<p class="description">
							<?php esc_html_e( 'Make the sheet public (Anyone with the link can view) before pasting the URL.', 'city-core' ); ?>
						</p>
					</td>
				</tr>
			</table>

		<hr>

		<h2><?php esc_html_e( 'Import POIs', 'city-core' ); ?></h2>
		<p><?php esc_html_e( 'Two-step import. First click "Preview Sheet Data" to inspect what would be imported (no DB changes). If the data looks correct, click "Confirm Import" to apply.', 'city-core' ); ?></p>

		<button type="button" id="city-preview-pois-btn" class="button button-primary">
			<?php esc_html_e( 'Preview Sheet Data', 'city-core' ); ?>
		</button>
		<button type="button" id="city-import-pois-btn" class="button button-secondary" disabled style="margin-left: 8px;">
			<?php esc_html_e( 'Confirm Import', 'city-core' ); ?>
		</button>
		<span id="city-import-status" style="margin-left: 10px;"></span>

		<div id="city-import-preview" style="margin-top: 20px; display: none;"></div>

		<div id="city-import-results" style="margin-top: 20px; display: none;">
			<pre id="city-import-output" style="background: #f0f0f0; padding: 15px; max-height: 400px; overflow: auto;"></pre>
		</div>

		<hr>

		<?php $theme_colors = city_get_theme_palette(); ?>

		<h2><?php esc_html_e( 'Quiz Colors', 'city-core' ); ?></h2>
		<p><?php esc_html_e( 'Customize the colors used by the quiz block. Leave blank to use defaults.', 'city-core' ); ?></p>

		<?php
		$quiz_colors = array(
			'city_quiz_color_bg'                => array( __( 'Background', 'city-core' ), '#f8f5ea' ),
			'city_quiz_color_border'            => array( __( 'Border', 'city-core' ), '#00624d' ),
			'city_quiz_color_question'          => array( __( 'Question text', 'city-core' ), '#18302b' ),
			'city_quiz_color_letter_bg'         => array( __( 'Option letter background', 'city-core' ), '#00624d' ),
			'city_quiz_color_correct_bg'        => array( __( 'Correct answer background', 'city-core' ), '#d4edda' ),
			'city_quiz_color_correct_border'    => array( __( 'Correct answer border', 'city-core' ), '#28a745' ),
			'city_quiz_color_incorrect_bg'      => array( __( 'Incorrect answer background', 'city-core' ), '#f8d7da' ),
			'city_quiz_color_incorrect_border'  => array( __( 'Incorrect answer border', 'city-core' ), '#dc3545' ),
			'city_quiz_color_option_text'       => array( __( 'Answer text color', 'city-core' ), '#18302b' ),
			'city_quiz_color_feedback_success'  => array( __( 'Feedback success text', 'city-core' ), '#155724' ),
			'city_quiz_color_feedback_error'    => array( __( 'Feedback error text', 'city-core' ), '#721c24' ),
		);
		?>

		<table class="form-table">
			<?php foreach ( $quiz_colors as $option_name => $data ) :
				$label   = $data[0];
				$default = $data[1];
				$value   = get_option( $option_name, $default );
				if ( empty( $value ) ) {
					$value = $default;
				}
			?>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( $option_name ); ?>"><?php echo esc_html( $label ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="<?php echo esc_attr( $option_name ); ?>"
						name="<?php echo esc_attr( $option_name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						class="city-color-picker"
						data-default-color="<?php echo esc_attr( $default ); ?>"
					/>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<h3><?php esc_html_e( 'Map Colors', 'city-core' ); ?></h3>

		<?php
		$map_colors = array(
			'city_map_color_button'      => array( __( 'Button & indicator border', 'city-core' ), '#00624D' ),
			'city_map_color_button_text'     => array( __( 'Button text', 'city-core' ), '#063b2f' ),
			'city_map_color_category_active' => array( __( 'Active category filter', 'city-core' ), '#e07722' ),
			'city_map_color_locked'          => array( __( 'Locked POI color', 'city-core' ), '#888888' ),
			'city_map_color_button_bg'       => array( __( 'Button background', 'city-core' ), '#ffffff' ),
			'city_map_color_indicator_bg'    => array( __( 'Indicator background', 'city-core' ), '#ffffff' ),
			'city_map_color_popup_border'    => array( __( 'Popup border', 'city-core' ), '#000000' ),
			'city_map_color_popup_bg'        => array( __( 'Popup background', 'city-core' ), '#ffffff' ),
			'city_map_color_popup_text'      => array( __( 'Popup text', 'city-core' ), '#000000' ),
		);
		$locked_use_category = (bool) get_option( 'city_map_locked_use_category', false );
		?>

		<table class="form-table">
			<?php foreach ( $map_colors as $option_name => $data ) :
				$label   = $data[0];
				$default = $data[1];
				$value   = get_option( $option_name, $default );
				if ( empty( $value ) ) {
					$value = $default;
				}
			?>
			<tr>
				<th scope="row">
					<label for="<?php echo esc_attr( $option_name ); ?>"><?php echo esc_html( $label ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="<?php echo esc_attr( $option_name ); ?>"
						name="<?php echo esc_attr( $option_name ); ?>"
						value="<?php echo esc_attr( $value ); ?>"
						class="city-color-picker"
						data-default-color="<?php echo esc_attr( $default ); ?>"
					/>
				</td>
			</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row">
					<label for="city_map_locked_use_category"><?php esc_html_e( 'Use category color for locked POIs', 'city-core' ); ?></label>
				</th>
				<td>
					<label>
						<input
							type="checkbox"
							id="city_map_locked_use_category"
							name="city_map_locked_use_category"
							value="1"
							<?php checked( $locked_use_category ); ?>
						/>
						<?php esc_html_e( 'When enabled, locked POIs use their assigned category color. When disabled, all locked POIs use the "Locked POI color" above.', 'city-core' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'city-core' ) ); ?>

		</form>

		<script>
		(function($) {
			var cityThemePalette = <?php echo wp_json_encode( $theme_colors ); ?>;
			$(document).ready(function() {
				$('.city-color-picker').wpColorPicker({
					palettes: cityThemePalette.length ? cityThemePalette : true
				});
			});
		})(jQuery);
		</script>

		<script>
		(function() {
			var previewBtn = document.getElementById('city-preview-pois-btn');
			var importBtn  = document.getElementById('city-import-pois-btn');
			var status     = document.getElementById('city-import-status');
			var preview    = document.getElementById('city-import-preview');
			var results    = document.getElementById('city-import-results');
			var output     = document.getElementById('city-import-output');
			var nonce      = '<?php echo wp_create_nonce( 'city_import_pois' ); ?>';

			function esc(s) {
				return String(s == null ? '' : s)
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#39;');
			}

			function truncate(s, n) {
				s = String(s == null ? '' : s);
				return s.length > n ? s.substring(0, n) + '…' : s;
			}

			function renderPreview(data) {
				var html = '';

				html += '<h3>📋 <?php esc_html_e( 'Sheet preview', 'city-core' ); ?></h3>';
				html += '<p><strong><?php esc_html_e( 'Detected rows:', 'city-core' ); ?></strong> ' + data.total_rows + '</p>';

				if (data.errors && data.errors.length) {
					html += '<div style="background:#fff8e1;border-left:4px solid #ffb900;padding:12px 16px;margin:12px 0;">';
					html += '<strong>⚠️ <?php esc_html_e( 'Consistency warnings:', 'city-core' ); ?></strong><ul style="margin:8px 0 0 16px;">';
					for (var i = 0; i < data.errors.length; i++) {
						html += '<li>' + esc(data.errors[i]) + '</li>';
					}
					html += '</ul><p style="margin:8px 0 0;"><em><?php esc_html_e( 'Fix these in your Sheet before importing or the result will contain duplicates.', 'city-core' ); ?></em></p></div>';
				}

				// Cities & categories summary.
				html += '<div style="display:flex;gap:20px;flex-wrap:wrap;margin:12px 0;">';
				html += '<div style="flex:1;min-width:240px;"><strong>🏙️ <?php esc_html_e( 'Cities in sheet', 'city-core' ); ?> (' + data.cities_in_sheet.length + '):</strong><br>';
				html += data.cities_in_sheet.map(function(c) { return esc(c); }).join(', ') || '<em>—</em>';
				if (data.new_cities.length) {
					html += '<br><span style="color:#0073aa;"><strong><?php esc_html_e( 'NEW (will be created):', 'city-core' ); ?></strong> ' + data.new_cities.map(esc).join(', ') + '</span>';
				}
				html += '</div>';

				html += '<div style="flex:1;min-width:240px;"><strong>📂 <?php esc_html_e( 'Categories in sheet', 'city-core' ); ?> (' + data.categories_in_sheet.length + '):</strong><br>';
				html += data.categories_in_sheet.map(function(c) { return esc(c); }).join(', ') || '<em>—</em>';
				if (data.new_categories.length) {
					html += '<br><span style="color:#0073aa;"><strong><?php esc_html_e( 'NEW (will be created):', 'city-core' ); ?></strong> ' + data.new_categories.map(esc).join(', ') + '</span>';
				}
				html += '</div>';
				html += '</div>';

				// Sample table.
				html += '<h4><?php esc_html_e( 'First rows preview (up to 10):', 'city-core' ); ?></h4>';
				html += '<div style="overflow-x:auto;max-width:100%;"><table class="widefat striped" style="font-size:12px;"><thead><tr>';
				for (var h = 0; h < data.header.length; h++) {
					html += '<th>' + esc(data.header[h]) + '</th>';
				}
				html += '</tr></thead><tbody>';
				for (var r = 0; r < data.sample.length; r++) {
					html += '<tr>';
					for (var h2 = 0; h2 < data.header.length; h2++) {
						var key = data.header[h2];
						var val = data.sample[r][key] !== undefined ? data.sample[r][key] : '';
						html += '<td style="max-width:240px;vertical-align:top;">' + esc(truncate(val, 120)) + '</td>';
					}
					html += '</tr>';
				}
				html += '</tbody></table></div>';

				if (data.errors && data.errors.length) {
					html += '<p style="margin-top:12px;color:#a00;"><strong><?php esc_html_e( 'Import is blocked until you resolve the warnings above.', 'city-core' ); ?></strong></p>';
				} else {
					html += '<p style="margin-top:12px;color:#0a7c2f;"><strong>✅ <?php esc_html_e( 'Data looks consistent. Click "Confirm Import" to apply.', 'city-core' ); ?></strong></p>';
				}

				preview.innerHTML = html;
				preview.style.display = 'block';
			}

			previewBtn.addEventListener('click', function() {
				previewBtn.disabled = true;
				importBtn.disabled = true;
				status.textContent = '<?php esc_html_e( 'Loading preview…', 'city-core' ); ?>';
				preview.style.display = 'none';
				results.style.display = 'none';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=city_preview_pois&nonce=' + encodeURIComponent(nonce),
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					previewBtn.disabled = false;
					if (!data.success) {
						status.textContent = '❌ Error';
						preview.innerHTML = '<p style="color:#a00;"><strong>Error:</strong> ' + esc(data.data || JSON.stringify(data)) + '</p>';
						preview.style.display = 'block';
						return;
					}
					renderPreview(data.data);
					if (data.data.errors && data.data.errors.length) {
						status.textContent = '⚠️ <?php esc_html_e( 'Fix sheet warnings before importing', 'city-core' ); ?>';
						importBtn.disabled = true;
					} else {
						status.textContent = '✅ <?php esc_html_e( 'Preview OK', 'city-core' ); ?>';
						importBtn.disabled = false;
					}
				})
				.catch(function(err) {
					previewBtn.disabled = false;
					status.textContent = '❌ Error';
					preview.innerHTML = '<p style="color:#a00;">' + esc(err.message) + '</p>';
					preview.style.display = 'block';
				});
			});

			importBtn.addEventListener('click', function() {
				importBtn.disabled = true;
				previewBtn.disabled = true;
				status.textContent = '<?php esc_html_e( 'Importing…', 'city-core' ); ?>';
				results.style.display = 'none';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=city_import_pois&nonce=' + encodeURIComponent(nonce),
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					previewBtn.disabled = false;
					status.textContent = data.success ? '✅ Done!' : '❌ Error';
					results.style.display = 'block';
					output.textContent = data.data || JSON.stringify(data, null, 2);
				})
				.catch(function(err) {
					previewBtn.disabled = false;
					importBtn.disabled = false;
					status.textContent = '❌ Error';
					results.style.display = 'block';
					output.textContent = err.message;
				});
			});
		})();
		</script>
	</div>
	<?php
}
