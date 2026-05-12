<?php
/**
 * POI Quiz block — asset dependencies and render callback.
 *
 * @package CityCore
 * @since   0.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the quiz block.
 *
 * @since 0.3
 */
function city_register_quiz_block() {
	register_block_type(
		CITY_CORE_DIR . 'blocks/city-quiz',
		array(
			'render_callback' => 'city_quiz_block_render',
		)
	);
}
add_action( 'init', 'city_register_quiz_block' );

/**
 * Render the quiz block on the frontend.
 *
 * @since 0.3
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Inner content.
 * @param WP_Block $block      Block instance.
 * @return string HTML output.
 */
function city_quiz_block_render( $attributes, $content, $block ) {
	$post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : get_the_ID();

	if ( ! $post_id ) {
		return '';
	}

	// Only render for POI post type.
	if ( get_post_type( $post_id ) !== 'poi' ) {
		return '';
	}

	// Get quiz data from post meta.
	$question = get_post_meta( $post_id, 'city_poi_quiz_question', true );
	$answer1  = get_post_meta( $post_id, 'city_poi_quiz_answer_1', true );
	$answer2  = get_post_meta( $post_id, 'city_poi_quiz_answer_2', true );
	$answer3  = get_post_meta( $post_id, 'city_poi_quiz_answer_3', true );
	$correct  = (int) get_post_meta( $post_id, 'city_poi_quiz_correct', true );
	$hint     = get_post_meta( $post_id, 'city_poi_hint', true );
	$reward_message = get_post_meta( $post_id, 'city_poi_reward_message', true );
	if ( empty( $reward_message ) ) {
		$reward_message = __( 'Correct! POI marked as completed.', 'city-core' );
	} else {
		// Defensive: decode HTML entities in a loop in case of double-encoding
		// (e.g. &amp;quot; → &quot; → ") before passing to JS via esc_js().
		$prev = '';
		while ( $reward_message !== $prev ) {
			$prev = $reward_message;
			$reward_message = html_entity_decode( $reward_message, ENT_QUOTES, 'UTF-8' );
		}
	}
	$poi_slug = get_post_field( 'post_name', $post_id );

	// Get quiz colors from settings.
	$colors = city_get_quiz_colors();

	// If no quiz data, don't render.
	if ( empty( $question ) || empty( $answer1 ) ) {
		return '';
	}

	// Get city slug for progress tracking.
	$city_terms = get_the_terms( $post_id, 'city' );
	$city_slug  = ! empty( $city_terms ) && ! is_wp_error( $city_terms ) ? $city_terms[0]->slug : '';
	$is_favorite = (int) get_post_meta( $post_id, 'city_poi_favorite', true );

	$poi_data = array(
		'poiId'     => $post_id,
		'poiSlug'   => $poi_slug,
		'citySlug'  => $city_slug,
		'nonce'     => wp_create_nonce( 'city_poi_nonce' ),
	);

	// Is this POI already completed (server-side)?
	$is_completed = (int) get_post_meta( $post_id, 'city_poi_completed', true );

	ob_start();
	?>
	<div class="city-quiz" data-poi="<?php echo esc_attr( wp_json_encode( $poi_data ) ); ?>" data-completed="<?php echo (int) $is_completed; ?>">
		<div class="city-quiz-header">
			<p class="city-quiz-question"><?php echo esc_html( $question ); ?></p>
		</div>

				<?php
				// Hint hidden — rendered by a separate modal in the theme/plugin.
				// if ( ! empty( $hint ) ) : ?>
				<!-- <p class="city-quiz-hint"><small><em>Hint: <?php // echo esc_html( $hint ); ?></em></small></p> -->
				<?php // endif; ?>

			<div class="city-quiz-options">
				<button type="button" class="city-quiz-option" data-answer="1">
					<span class="city-quiz-option-letter">A</span>
					<span class="city-quiz-option-text"><?php echo esc_html( $answer1 ); ?></span>
				</button>
				<button type="button" class="city-quiz-option" data-answer="2">
					<span class="city-quiz-option-letter">B</span>
					<span class="city-quiz-option-text"><?php echo esc_html( $answer2 ); ?></span>
				</button>
				<button type="button" class="city-quiz-option" data-answer="3">
					<span class="city-quiz-option-letter">C</span>
					<span class="city-quiz-option-text"><?php echo esc_html( $answer3 ); ?></span>
				</button>
			</div>

			<p class="city-quiz-feedback" style="display: none;"></p>
		</div>
	</div>

	<style>
	.city-quiz {
		background: <?php echo esc_attr( $colors['bg'] ); ?>;
		border: 2px solid <?php echo esc_attr( $colors['border'] ); ?>;
		border-radius: 0 !important;
		padding: 24px;
		margin: 20px 0;
		font-family: inherit;
	}
	.city-quiz-question {
		font-size: 18px;
		font-weight: 700;
		color: <?php echo esc_attr( $colors['question'] ); ?>;
		margin: 0 0 16px;
	}
	.city-quiz-header {
		display: flex;
		align-items: flex-start;
		justify-content: space-between;
		gap: 12px;
		margin: 0 0 16px;
	}

	.city-quiz-options {
		display: flex;
		flex-direction: column;
		gap: 10px;
	}
	.city-quiz-option {
		display: flex;
		align-items: center;
		gap: 12px;
		padding: 12px 16px;
		background: white;
		border: 2px solid #ddd;
		cursor: pointer;
		text-align: left;
		font-size: 15px;
		font-family: inherit;
		color: <?php echo esc_attr( $colors['option_text'] ); ?>;
		transition: all 0.2s ease;
	}
	.city-quiz-option:hover {
		border-color: <?php echo esc_attr( $colors['border'] ); ?>;
		background: #f0f9f6;
	}
	.city-quiz-option.correct {
		background: <?php echo esc_attr( $colors['correct_bg'] ); ?>;
		border-color: <?php echo esc_attr( $colors['correct_border'] ); ?>;
	}
	.city-quiz-option.incorrect {
		background: <?php echo esc_attr( $colors['incorrect_bg'] ); ?>;
		border-color: <?php echo esc_attr( $colors['incorrect_border'] ); ?>;
	}
	.city-quiz-option.disabled {
		opacity: 0.6;
		cursor: not-allowed;
	}
	.city-quiz-option-letter {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 28px;
		height: 28px;
		background: <?php echo esc_attr( $colors['letter_bg'] ); ?>;
		color: white;
		border-radius: 50%;
		font-family: inherit;
		font-weight: 700;
		font-size: 14px;
		flex-shrink: 0;
	}
	.city-quiz-option.correct .city-quiz-option-letter {
		background: <?php echo esc_attr( $colors['correct_border'] ); ?>;
	}
	.city-quiz-option.incorrect .city-quiz-option-letter {
		background: <?php echo esc_attr( $colors['incorrect_border'] ); ?>;
	}
	.city-quiz-option-text {
		flex: 1;
	}
	.city-quiz-feedback {
		margin: 16px 0 0;
		padding: 12px;
		font-family: inherit;
		font-weight: 600;
		text-align: center;
	}
	.city-quiz-feedback.success {
		background: <?php echo esc_attr( $colors['correct_bg'] ); ?>;
		color: <?php echo esc_attr( $colors['feedback_success'] ); ?>;
	}
	.city-quiz-feedback.error {
		background: <?php echo esc_attr( $colors['incorrect_bg'] ); ?>;
		color: <?php echo esc_attr( $colors['feedback_error'] ); ?>;
	}
	</style>

	<script>
	(function() {
		var container = document.querySelector('.city-quiz');
		if (!container) return;

		var poiData = JSON.parse(container.dataset.poi || '{}');
		var poiId = poiData.poiId;
		var poiSlug = poiData.poiSlug || '';
		var citySlug = poiData.citySlug || '';
		var nonce = poiData.nonce || '';

		var options = container.querySelectorAll('.city-quiz-option');
		var feedback = container.querySelector('.city-quiz-feedback');

		// Get correct answer from server-side rendered data.
		var correctAnswer = <?php echo (int) $correct; ?>;

		// Check if already completed from server-side meta.
		var isServerCompleted = parseInt(container.dataset.completed) === 1;

		// Check if already completed from localStorage.
		var completedKey = 'city_poi_completed_' + (citySlug || 'default');
		var completed = JSON.parse(localStorage.getItem(completedKey) || '[]');
		var isLocalCompleted = completed.includes(poiSlug);

		if (isServerCompleted || isLocalCompleted) {
			showCompletedState();
		}

		// Sync cookie city_poi_quiz_status with this POI's completion state.
		// Block Visibility uses this cookie to show/hide template sections.
		if (isServerCompleted || isLocalCompleted) {
			document.cookie = 'city_poi_quiz_status=ok; path=/; max-age=31536000; SameSite=Lax';
		} else {
			// Clear cookie so a previous ok/ko from another POI doesn't leak.
			document.cookie = 'city_poi_quiz_status=; path=/; max-age=0; SameSite=Lax';
		}

		options.forEach(function(btn) {
			btn.addEventListener('click', function() {
				var selected = parseInt(this.dataset.answer);

				// Disable all options.
				options.forEach(function(o) { o.classList.add('disabled'); });

				if (selected === correctAnswer) {
					// Correct!
					this.classList.add('correct');
					feedback.textContent = '<?php echo esc_js( $reward_message ); ?>';
					feedback.className = 'city-quiz-feedback success';
					feedback.style.display = 'block';

					// Save to localStorage.
					if (!completed.includes(poiSlug)) {
						completed.push(poiSlug);
						localStorage.setItem(completedKey, JSON.stringify(completed));
					}

					// Set cookie for Block Visibility (persistent 1 year).
					document.cookie = 'city_poi_quiz_status=ok; path=/; max-age=31536000; SameSite=Lax';

					// Save to server via AJAX, then reload so cookie-controlled
					// sections (Block Visibility with city_poi_quiz_status=ok)
					// render server-side with the reward content.
					var xhr = new XMLHttpRequest();
					xhr.open('POST', '<?php echo admin_url( 'admin-ajax.php' ); ?>', true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

					var reloaded = false;
					function reloadAfterFeedback() {
						if (reloaded) return;
						reloaded = true;
						// Brief pause so the user sees the success message.
						setTimeout(function () {
							window.location.reload();
						}, 1200);
					}

					xhr.onload  = reloadAfterFeedback;
					xhr.onerror = reloadAfterFeedback;

					// Safety net: reload even if onload/onerror never fire.
					setTimeout(reloadAfterFeedback, 3000);

					xhr.send('action=city_complete_poi&nonce=' + encodeURIComponent(nonce) + '&poi_id=' + poiId);

					// Dispatch event for map to update.
					window.dispatchEvent(new CustomEvent('cityPoiCompleted', {
						detail: { poiId: poiId, poiSlug: poiSlug, citySlug: citySlug }
					}));

				} else {
					// Incorrect.
					this.classList.add('incorrect');
					// Highlight correct answer.
					options.forEach(function(o) {
						if (parseInt(o.dataset.answer) === correctAnswer) {
							o.classList.add('correct');
						}
					});
					feedback.textContent = '<?php esc_html_e( 'Incorrect. Try again!', 'city-core' ); ?>';
					feedback.className = 'city-quiz-feedback error';
					feedback.style.display = 'block';

					// Set cookie for Block Visibility (session only).
					document.cookie = 'city_poi_quiz_status=ko; path=/; SameSite=Lax';

					// Re-enable options after a moment.
					setTimeout(function() {
						options.forEach(function(o) { o.classList.remove('disabled', 'incorrect'); });
						feedback.style.display = 'none';
					}, 1500);
				}
			});
		});

		function showCompletedState() {
			options.forEach(function(o) {
				o.classList.add('disabled');
				if (parseInt(o.dataset.answer) === correctAnswer) {
					o.classList.add('correct');
				}
			});
			feedback.textContent = '<?php esc_html_e( 'Completed!', 'city-core' ); ?>';
			feedback.className = 'city-quiz-feedback success';
			feedback.style.display = 'block';
		}

		// ── Favorite button ────────────────────────────────────────────────
		var favBtn = container.querySelector('.add-fav');
		if (favBtn) {
			favBtn.addEventListener('click', function() {
				var btn = this;
				var isFav = btn.classList.contains('added-fav');
				var newState = isFav ? 0 : 1;

				// Toggle cookie (client-side, always works).
				var cookieKey = 'city_poi_favorites_' + (citySlug || 'default');
				var favorites = JSON.parse(localStorage.getItem(cookieKey) || '[]');

				if (newState === 1) {
					if (!favorites.includes(poiSlug)) {
						favorites.push(poiSlug);
					}
					btn.classList.add('added-fav');
				} else {
					favorites = favorites.filter(function(s) { return s !== poiSlug; });
					btn.classList.remove('added-fav');
				}
				localStorage.setItem(cookieKey, JSON.stringify(favorites));

				// Sync to server via AJAX.
				var xhr = new XMLHttpRequest();
				xhr.open('POST', '<?php echo admin_url( 'admin-ajax.php' ); ?>', true);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.send('action=city_toggle_favorite&nonce=' + encodeURIComponent(nonce) + '&poi_id=' + poiId);
			});
		}
	})();
	</script>
	<?php
	return ob_get_clean();
}
