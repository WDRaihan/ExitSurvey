<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap es-admin">
	<h1 class="es-page-title">
		<span class="dashicons dashicons-editor-help"></span>
		<?php echo esc_html__( 'Survey Questions', 'exitsurvey' ); ?>
	</h1>

	<?php if ( isset( $_GET['saved'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html__( 'Questions saved successfully!', 'exitsurvey' ); ?></p></div>
	<?php endif; ?>

	<p class="es-description">
		<?php echo esc_html__( 'Configure the questions shown in each exit survey trigger. One question is shown per session based on the visitor\'s browsing behavior.', 'exitsurvey' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'exitsurvey_questions' ); ?>
		<input type="hidden" name="action" value="exitsurvey_save_questions">

		<?php
		$trigger_labels = [
			'cart'     => __( '🛒 Cart Abandonment', 'exitsurvey' ),
			'checkout' => __( '💳 Checkout Abandonment', 'exitsurvey' ),
			'product'  => __( '📦 Product Page Visit', 'exitsurvey' ),
			'shop'     => __( '🏪 Shop / Category Browse', 'exitsurvey' ),
			'general'  => __( '💬 General (Fallback)', 'exitsurvey' ),
		];
		$grouped = [];
		foreach ( $questions as $q ) {
			$grouped[ $q['trigger_type'] ][] = $q;
		}
		$row_index = 0;
		foreach ( $trigger_labels as $trigger => $label ) :
			$trigger_questions = $grouped[ $trigger ] ?? [];
		?>
		<div class="es-question-group">
			<h2 class="es-group-title"><?php echo esc_html( $label ); ?></h2>

			<?php if ( empty( $trigger_questions ) ) : ?>
				<p class="es-empty"><?php echo esc_html__( 'No questions for this trigger.', 'exitsurvey' ); ?></p>
			<?php else : ?>
				<?php foreach ( $trigger_questions as $q ) : ?>
					<div class="es-question-card">
						<input type="hidden" name="q_id[<?php echo $row_index; ?>]" value="<?php echo esc_attr( $q['id'] ); ?>">

						<div class="es-question-header">
							<label class="es-toggle">
								<input type="checkbox" name="q_active[<?php echo $row_index; ?>]" value="1" <?php checked( $q['is_active'] ); ?>>
								<span class="es-toggle__slider"></span>
							</label>
							<span class="es-question-id">Q<?php echo esc_html( $q['id'] ); ?></span>
							<select name="q_trigger[<?php echo $row_index; ?>]" class="es-select-trigger">
								<?php foreach ( $trigger_labels as $t => $l ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $q['trigger_type'], $t ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<select name="q_type[<?php echo $row_index; ?>]">
								<option value="multiple_choice" <?php selected( $q['question_type'], 'multiple_choice' ); ?>><?php echo esc_html__( 'Multiple Choice', 'exitsurvey' ); ?></option>
								<option value="text" <?php selected( $q['question_type'], 'text' ); ?>><?php echo esc_html__( 'Open Text', 'exitsurvey' ); ?></option>
							</select>
							<input type="number" name="q_order[<?php echo $row_index; ?>]" value="<?php echo esc_attr( $q['sort_order'] ); ?>" class="es-input-order" title="Sort order">
						</div>

						<div class="es-question-body">
							<label><?php echo esc_html__( 'Question Text', 'exitsurvey' ); ?></label>
							<textarea name="q_text[<?php echo $row_index; ?>]" class="es-textarea-question" rows="2"><?php echo esc_textarea( $q['question_text'] ); ?></textarea>

							<label><?php echo esc_html__( 'Answer Options (one per line — leave empty for open text)', 'exitsurvey' ); ?></label>
							<textarea name="q_options[<?php echo $row_index; ?>]" class="es-textarea-options" rows="5"><?php
								$options = $q['options'] ? json_decode( $q['options'], true ) : [];
								echo esc_textarea( implode( "\n", $options ) );
							?></textarea>
						</div>
					</div>
					<?php $row_index++; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>

		<p class="submit">
			<button type="submit" class="button button-primary button-hero"><?php echo esc_html__( 'Save All Questions', 'exitsurvey' ); ?></button>
		</p>
	</form>
</div>
