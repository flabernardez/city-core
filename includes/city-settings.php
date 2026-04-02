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
	register_setting( 'city_settings_group', 'city_sheets_url', 'esc_url' );
	register_setting( 'city_settings_group', 'city_sheets_url', array(
		'type'              => 'string',
		'sanitize_callback' => 'esc_url',
	) );
}
add_action( 'admin_init', 'city_register_settings' );

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
			<p><?php esc_html_e( 'The sheet must have these columns: Nombre, Imagen, Información, Ciudad, Categoría, Geo, Pista, Pregunta del Quiz, Respuesta1, Respuesta2, Respuesta3, Correcta', 'city-core' ); ?></p>
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

			<?php submit_button(); ?>
		</form>

		<hr>

		<h2><?php esc_html_e( 'Import POIs', 'city-core' ); ?></h2>
		<p><?php esc_html_e( 'Click the button below to import or update Points of Interest from the Google Sheet.', 'city-core' ); ?></p>

		<button type="button" id="city-import-pois-btn" class="button button-primary">
			<?php esc_html_e( 'Import POIs from Sheet', 'city-core' ); ?>
		</button>
		<span id="city-import-status" style="margin-left: 10px;"></span>

		<div id="city-import-results" style="margin-top: 20px; display: none;">
			<pre id="city-import-output" style="background: #f0f0f0; padding: 15px; max-height: 400px; overflow: auto;"></pre>
		</div>

		<script>
		(function() {
			var btn = document.getElementById('city-import-pois-btn');
			var status = document.getElementById('city-import-status');
			var results = document.getElementById('city-import-results');
			var output = document.getElementById('city-import-output');

			btn.addEventListener('click', function() {
				btn.disabled = true;
				status.textContent = '<?php esc_html_e( 'Importing...', 'city-core' ); ?>';
				results.style.display = 'none';

				fetch(ajaxurl, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
					body: 'action=city_import_pois&nonce=<?php echo wp_create_nonce( 'city_import_pois' ); ?>'
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					btn.disabled = false;
					status.textContent = data.success ? '✅ Done!' : '❌ Error';
					results.style.display = 'block';
					output.textContent = data.data || JSON.stringify(data, null, 2);
				})
				.catch(function(err) {
					btn.disabled = false;
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
