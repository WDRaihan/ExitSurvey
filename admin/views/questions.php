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
						<input type="hidden" name="q_id[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $q['id'] ); ?>">

						<div class="es-question-header">
							<label class="es-toggle">
								<input type="checkbox" name="q_active[<?php echo (int) $row_index; ?>]" value="1" <?php checked( $q['is_active'] ); ?>>
								<span class="es-toggle__slider"></span>
							</label>
							<span class="es-question-id">Q<?php echo esc_html( $q['id'] ); ?></span>
							<select name="q_trigger[<?php echo (int) $row_index; ?>]" class="es-select-trigger">
								<?php foreach ( $trigger_labels as $t => $l ) : ?>
									<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $q['trigger_type'], $t ); ?>><?php echo esc_html( $l ); ?></option>
								<?php endforeach; ?>
							</select>
							<select name="q_type[<?php echo (int) $row_index; ?>]">
								<option value="multiple_choice" <?php selected( $q['question_type'], 'multiple_choice' ); ?>><?php echo esc_html__( 'Multiple Choice', 'exitsurvey' ); ?></option>
								<option value="text" <?php selected( $q['question_type'], 'text' ); ?>><?php echo esc_html__( 'Open Text', 'exitsurvey' ); ?></option>
							</select>
							<input type="number" name="q_order[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $q['sort_order'] ); ?>" class="es-input-order" title="Sort order">
						</div>

						<div class="es-question-body">
							<label><?php echo esc_html__( 'Question Text', 'exitsurvey' ); ?></label>
							<textarea name="q_text[<?php echo (int) $row_index; ?>]" class="es-textarea-question" rows="2"><?php echo esc_textarea( $q['question_text'] ); ?></textarea>

							<label><?php echo esc_html__( 'Answer Options (one per line — leave empty for open text)', 'exitsurvey' ); ?></label>
							<textarea name="q_options[<?php echo (int) $row_index; ?>]" class="es-textarea-options" rows="5"><?php
								$options = $q['options'] ? json_decode( $q['options'], true ) : [];
								echo esc_textarea( implode( "\n", $options ) );
							?></textarea>

							<div class="es-extra-field-settings" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
								<label style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600;">
									<input type="checkbox" name="q_extra_enabled[<?php echo (int) $row_index; ?>]" value="1" <?php checked( $q['extra_field_enabled'] ?? 0, 1 ); ?>>
									<?php echo esc_html__( 'Enable additional text input for this question', 'exitsurvey' ); ?>
								</label>
								<div class="es-extra-field-label-wrap" style="margin-top: 10px; <?php echo ( $q['extra_field_enabled'] ?? 0 ) ? '' : 'display:none;'; ?>">
									<label style="display: block; margin-bottom: 5px; font-size: 13px;"><?php echo esc_html__( 'Extra Field Label:', 'exitsurvey' ); ?></label>
									<input type="text" name="q_extra_label[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $q['extra_field_label'] ?? 'Share your email for a discount code' ); ?>" class="regular-text" style="width: 100%;">
								</div>
								<?php do_action( 'exitsurvey_question_extra_fields', $q, $row_index ); ?>
							</div>

							<?php
							$seg = json_decode( $q['segment_rules'] ?? '{}', true ) ?: [];
							$seg = wp_parse_args( $seg, [
								'user_type'      => 'all',
								'min_orders'     => 0,
								'max_orders'     => 0,
								'min_cart_value'  => 0,
								'max_cart_value'  => 0,
							] );
							$has_rules = ( $seg['user_type'] !== 'all' || $seg['min_orders'] > 0 || $seg['max_orders'] > 0 || $seg['min_cart_value'] > 0 || $seg['max_cart_value'] > 0 );
							?>
							<div class="es-segment-settings" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
								<a href="#" class="es-segment-toggle">
									<span class="dashicons dashicons-filter" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom;"></span>
									<?php echo esc_html__( 'Targeting Rules', 'exitsurvey' ); ?>
									<?php if ( $has_rules ) : ?>
										<span class="es-segment-badge"><?php echo esc_html__( 'Active', 'exitsurvey' ); ?></span>
									<?php endif; ?>
									<span class="es-segment-arrow">▼</span>
								</a>
								<div class="es-segment-body" style="<?php echo $has_rules ? '' : 'display: none;'; ?> margin-top: 12px;">
									<div class="es-segment-grid">
										<div class="es-segment-field">
											<label><?php echo esc_html__( 'User Type', 'exitsurvey' ); ?></label>
											<select name="q_seg_user_type[<?php echo (int) $row_index; ?>]">
												<option value="all" <?php selected( $seg['user_type'], 'all' ); ?>><?php echo esc_html__( 'All Visitors', 'exitsurvey' ); ?></option>
												<option value="guest" <?php selected( $seg['user_type'], 'guest' ); ?>><?php echo esc_html__( 'Guest Only', 'exitsurvey' ); ?></option>
												<option value="logged_in" <?php selected( $seg['user_type'], 'logged_in' ); ?>><?php echo esc_html__( 'Logged-in Only', 'exitsurvey' ); ?></option>
											</select>
										</div>
										<div class="es-segment-field">
											<label><?php echo esc_html__( 'Min Orders', 'exitsurvey' ); ?></label>
											<input type="number" name="q_seg_min_orders[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $seg['min_orders'] ); ?>" min="0" class="small-text">
										</div>
										<div class="es-segment-field">
											<label><?php echo esc_html__( 'Max Orders', 'exitsurvey' ); ?></label>
											<input type="number" name="q_seg_max_orders[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $seg['max_orders'] ); ?>" min="0" class="small-text">
										</div>
										<div class="es-segment-field">
											<label><?php echo esc_html__( 'Min Cart Value', 'exitsurvey' ); ?></label>
											<input type="number" name="q_seg_min_cart[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $seg['min_cart_value'] ); ?>" min="0" step="0.01" class="small-text">
										</div>
										<div class="es-segment-field">
											<label><?php echo esc_html__( 'Max Cart Value', 'exitsurvey' ); ?></label>
											<input type="number" name="q_seg_max_cart[<?php echo (int) $row_index; ?>]" value="<?php echo esc_attr( $seg['max_cart_value'] ); ?>" min="0" step="0.01" class="small-text">
										</div>
									</div>
									<p class="description" style="margin-top: 8px; font-size: 12px; color: #94a3b8;"><?php echo esc_html__( 'Set 0 for no limit. Only matching visitors will see this question.', 'exitsurvey' ); ?></p>
								</div>
							</div>
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
