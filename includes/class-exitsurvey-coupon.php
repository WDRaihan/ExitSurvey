<?php
/**
 * Conversion Recovery — Auto Coupon, Smart Discount Tiers & Cart Recovery Email.
 *
 * Self-contained module: all hooks, coupon creation, and email sending live here.
 * Wired into the core plugin via action/filter hooks — zero changes needed to core.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Coupon {

	/* ------------------------------------------------------------------
	 * BOOTSTRAP
	 * --------------------------------------------------------------- */

	public static function init() {
		// Database migration.
		add_action( 'exitsurvey_database_update', [ __CLASS__, 'migrate_database' ] );

		// Admin: inject UI into Questions page.
		add_action( 'exitsurvey_question_extra_fields', [ __CLASS__, 'render_question_coupon_field' ], 20, 2 );

		// Admin: inject UI into Settings page.
		add_action( 'exitsurvey_settings_sections', [ __CLASS__, 'render_settings_section' ] );

		// Admin: save data.
		add_action( 'exitsurvey_save_question', [ __CLASS__, 'save_question_coupon_field' ], 10, 2 );
		add_action( 'exitsurvey_save_settings', [ __CLASS__, 'save_settings_fields' ] );

		// Frontend: enrich question data sent to JS (expose trigger answers list).
		add_filter( 'exitsurvey_question_row_data', [ __CLASS__, 'enrich_question_data' ] );

		// Core: respond after a survey response is saved.
		add_action( 'exitsurvey_after_response_saved', [ __CLASS__, 'handle_response' ], 20, 3 );

		// Core: allow coupon data to be appended to the AJAX success response.
		add_filter( 'exitsurvey_submit_response_data', [ __CLASS__, 'append_coupon_to_response' ], 10, 3 );
	}

	/* ------------------------------------------------------------------
	 * DATABASE MIGRATION
	 * --------------------------------------------------------------- */

	/**
	 * Add coupon_trigger_answers column to questions table if missing.
	 */
	public static function migrate_database() {
		global $wpdb;
		$q_table = $wpdb->prefix . 'exitsurvey_questions';

		$col = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'coupon_trigger_answers' AND TABLE_SCHEMA = %s",
				$q_table,
				DB_NAME
			)
		);

		if ( empty( $col ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN coupon_trigger_answers TEXT DEFAULT NULL AFTER segment_rules" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/* ------------------------------------------------------------------
	 * ADMIN: QUESTIONS PAGE — PER-QUESTION TRIGGER FIELD
	 * --------------------------------------------------------------- */

	/**
	 * Render the "Trigger coupon on answers" textarea inside each question card.
	 *
	 * @param array $q         Question data row.
	 * @param int   $row_index Current row index.
	 */
	public static function render_question_coupon_field( $q, $row_index ) {
		if ( get_option( 'exitsurvey_coupon_enabled', 'no' ) !== 'yes' ) {
			return;
		}
		$value = $q['coupon_trigger_answers'] ?? '';
		?>
		<div class="es-coupon-trigger-wrap" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
			<label style="display: block; font-weight: 600; font-size: 13px; color: #92400e; margin-bottom: 6px;">
				🎟️ <?php echo esc_html__( 'Trigger coupon on these answers (one per line)', 'exitsurvey' ); ?>
			</label>
			<textarea
				name="q_coupon_trigger_answers[<?php echo (int) $row_index; ?>]"
				rows="4"
				style="width:100%; border:1px solid #e5e7eb; border-radius:6px; padding:8px 10px; font-size:13px; font-family:monospace; resize:vertical;"
				placeholder="<?php esc_attr_e( 'Leave blank to show coupon for any answer. Add specific answers (one per line) to restrict when the coupon appears.', 'exitsurvey' ); ?>"
			><?php echo esc_textarea( $value ); ?></textarea>
			<p class="description" style="margin: 5px 0 0; font-size: 12px; color: #94a3b8;">
				<?php echo esc_html__( 'Example: "Too expensive" or "Found a better deal elsewhere". Case-insensitive. Leave empty to always show the coupon.', 'exitsurvey' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Save per-question coupon trigger answers.
	 *
	 * @param int $question_id DB ID of the question.
	 * @param int $row_index   Row index from the form.
	 */
	public static function save_question_coupon_field( $question_id, $row_index ) {
		global $wpdb;

		$raw_map = wp_unslash( $_POST['q_coupon_trigger_answers'] ?? [] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw     = sanitize_textarea_field( $raw_map[ $row_index ] ?? '' );

		$wpdb->update(
			$wpdb->prefix . 'exitsurvey_questions',
			[ 'coupon_trigger_answers' => $raw ],
			[ 'id' => $question_id ]
		);
	}

	/* ------------------------------------------------------------------
	 * ADMIN: SETTINGS PAGE — CONVERSION RECOVERY SECTION
	 * --------------------------------------------------------------- */

	/**
	 * Render the Conversion Recovery settings section.
	 */
	public static function render_settings_section() {
		$enabled         = get_option( 'exitsurvey_coupon_enabled', 'no' );
		$prefix          = get_option( 'exitsurvey_coupon_prefix', 'EXIT' );
		$expiry_hours    = (int) get_option( 'exitsurvey_coupon_expiry_hours', 24 );
		$countdown_min   = (int) get_option( 'exitsurvey_coupon_countdown_minutes', 10 );
		$tiers           = json_decode( get_option( 'exitsurvey_coupon_tiers', '[]' ), true );
		if ( empty( $tiers ) ) {
			$tiers = [
				[ 'min_cart' => 0,  'max_cart' => 49.99, 'discount_type' => 'percent', 'amount' => 5 ],
				[ 'min_cart' => 50, 'max_cart' => 0,     'discount_type' => 'percent', 'amount' => 10 ],
			];
		}

		$recovery_enabled = get_option( 'exitsurvey_cart_recovery_email_enabled', 'no' );
		$recovery_subject = get_option( 'exitsurvey_cart_recovery_email_subject', "You left something behind — here's a discount!" );
		$from_name        = get_option( 'exitsurvey_cart_recovery_from_name', get_bloginfo( 'name' ) );
		$from_email       = get_option( 'exitsurvey_cart_recovery_from_email', get_option( 'admin_email' ) );
		?>
		<div class="es-settings-section" style="grid-column: 1 / -1;">
			<h2><?php echo esc_html__( '🎟️ Conversion Recovery', 'exitsurvey' ); ?></h2>
			<p class="description" style="margin-top: -8px; margin-bottom: 16px; color: #64748b; font-size: 13px;">
				<?php echo esc_html__( 'Automatically offer discount coupons and send cart recovery emails to win back abandoning visitors.', 'exitsurvey' ); ?>
			</p>

			<!-- Master Toggle -->
			<table class="form-table">
				<tr>
					<th><?php echo esc_html__( 'Enable Auto Coupon', 'exitsurvey' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="exitsurvey_coupon_enabled" value="yes" <?php checked( $enabled, 'yes' ); ?>>
							<?php echo esc_html__( 'Generate and display a coupon code when a visitor submits the survey', 'exitsurvey' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Coupon Code Prefix', 'exitsurvey' ); ?></th>
					<td>
						<input type="text" name="exitsurvey_coupon_prefix" value="<?php echo esc_attr( $prefix ); ?>" class="regular-text" maxlength="20" placeholder="EXIT">
						<p class="description"><?php echo esc_html__( 'Generated codes will look like: EXIT-A3F7K2. Max 20 characters.', 'exitsurvey' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Coupon Expiry (hours)', 'exitsurvey' ); ?></th>
					<td>
						<input type="number" name="exitsurvey_coupon_expiry_hours" value="<?php echo esc_attr( $expiry_hours ); ?>" min="0" max="8760" class="small-text"> hours
						<p class="description"><?php echo esc_html__( 'Set to 0 for no expiry. Each coupon is single-use.', 'exitsurvey' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Countdown Timer (minutes)', 'exitsurvey' ); ?></th>
					<td>
						<input type="number" name="exitsurvey_coupon_countdown_minutes" value="<?php echo esc_attr( $countdown_min ); ?>" min="1" max="60" class="small-text"> minutes
						<p class="description"><?php echo esc_html__( 'How long the offer countdown runs in the popup after the coupon is shown.', 'exitsurvey' ); ?></p>
					</td>
				</tr>
			</table>

			<!-- Smart Discount Tiers -->
			<h3 style="margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 16px;"><?php echo esc_html__( '📊 Smart Discount Tiers (up to 3)', 'exitsurvey' ); ?></h3>
			<p class="description" style="color: #64748b; font-size: 13px; margin-bottom: 12px;">
				<?php echo esc_html__( 'Set discount percentages or fixed amounts based on cart value. Leave "Max Cart" at 0 for "no upper limit".', 'exitsurvey' ); ?>
			</p>

			<table class="form-table" id="es-coupon-tiers-table">
				<thead>
					<tr>
						<th style="font-size:12px; color:#64748b; font-weight:600; padding: 4px 10px;"><?php echo esc_html__( 'Tier', 'exitsurvey' ); ?></th>
						<th style="font-size:12px; color:#64748b; font-weight:600; padding: 4px 10px;"><?php echo esc_html__( 'Min Cart ($)', 'exitsurvey' ); ?></th>
						<th style="font-size:12px; color:#64748b; font-weight:600; padding: 4px 10px;"><?php echo esc_html__( 'Max Cart ($, 0 = unlimited)', 'exitsurvey' ); ?></th>
						<th style="font-size:12px; color:#64748b; font-weight:600; padding: 4px 10px;"><?php echo esc_html__( 'Discount Type', 'exitsurvey' ); ?></th>
						<th style="font-size:12px; color:#64748b; font-weight:600; padding: 4px 10px;"><?php echo esc_html__( 'Amount', 'exitsurvey' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$max_tiers = 3;
				for ( $i = 0; $i < $max_tiers; $i++ ) :
					$tier = $tiers[ $i ] ?? [ 'min_cart' => '', 'max_cart' => '', 'discount_type' => 'percent', 'amount' => '' ];
				?>
					<tr>
						<td style="color:#64748b; font-weight:700; padding: 6px 10px;">#<?php echo $i + 1; ?></td>
						<td style="padding: 6px 10px;">
							<input type="number" name="exitsurvey_coupon_tier_min[<?php echo $i; ?>]" value="<?php echo esc_attr( $tier['min_cart'] ); ?>" step="0.01" min="0" class="small-text" placeholder="0">
						</td>
						<td style="padding: 6px 10px;">
							<input type="number" name="exitsurvey_coupon_tier_max[<?php echo $i; ?>]" value="<?php echo esc_attr( $tier['max_cart'] ); ?>" step="0.01" min="0" class="small-text" placeholder="0">
						</td>
						<td style="padding: 6px 10px;">
							<select name="exitsurvey_coupon_tier_type[<?php echo $i; ?>]">
								<option value="percent" <?php selected( $tier['discount_type'], 'percent' ); ?>><?php echo esc_html__( 'Percentage (%)', 'exitsurvey' ); ?></option>
								<option value="fixed_cart" <?php selected( $tier['discount_type'], 'fixed_cart' ); ?>><?php echo esc_html__( 'Fixed Amount ($)', 'exitsurvey' ); ?></option>
							</select>
						</td>
						<td style="padding: 6px 10px;">
							<input type="number" name="exitsurvey_coupon_tier_amount[<?php echo $i; ?>]" value="<?php echo esc_attr( $tier['amount'] ); ?>" step="0.01" min="0" class="small-text" placeholder="10">
						</td>
					</tr>
				<?php endfor; ?>
				</tbody>
			</table>

			<!-- Cart Recovery Email -->
			<h3 style="margin-top: 20px; border-top: 1px solid #e5e7eb; padding-top: 16px;"><?php echo esc_html__( '📧 Cart Recovery Email', 'exitsurvey' ); ?></h3>
			<p class="description" style="color: #64748b; font-size: 13px; margin-bottom: 12px;">
				<?php echo esc_html__( 'When a visitor provides their email via an extra field, automatically send them a cart recovery email with their coupon and abandoned items.', 'exitsurvey' ); ?>
			</p>

			<table class="form-table">
				<tr>
					<th><?php echo esc_html__( 'Enable Cart Recovery Email', 'exitsurvey' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="exitsurvey_cart_recovery_email_enabled" value="yes" <?php checked( $recovery_enabled, 'yes' ); ?>>
							<?php echo esc_html__( 'Send cart recovery email when email is collected via an extra field', 'exitsurvey' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'From Name', 'exitsurvey' ); ?></th>
					<td>
						<input type="text" name="exitsurvey_cart_recovery_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'From Email', 'exitsurvey' ); ?></th>
					<td>
						<input type="email" name="exitsurvey_cart_recovery_from_email" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
					</td>
				</tr>
				<tr>
					<th><?php echo esc_html__( 'Email Subject', 'exitsurvey' ); ?></th>
					<td>
						<input type="text" name="exitsurvey_cart_recovery_email_subject" value="<?php echo esc_attr( $recovery_subject ); ?>" class="regular-text">
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Save conversion recovery settings from the Settings form.
	 */
	public static function save_settings_fields() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified by the caller.

		// Simple fields.
		$simple = [
			'exitsurvey_coupon_enabled'                => 'yes_no',
			'exitsurvey_coupon_prefix'                 => 'text',
			'exitsurvey_coupon_expiry_hours'           => 'int',
			'exitsurvey_coupon_countdown_minutes'      => 'int',
			'exitsurvey_cart_recovery_email_enabled'   => 'yes_no',
			'exitsurvey_cart_recovery_from_name'       => 'text',
			'exitsurvey_cart_recovery_from_email'      => 'email',
			'exitsurvey_cart_recovery_email_subject'   => 'text',
		];

		foreach ( $simple as $key => $type ) {
			$raw = wp_unslash( $_POST[ $key ] ?? '' );
			switch ( $type ) {
				case 'yes_no':
					update_option( $key, ! empty( $raw ) ? 'yes' : 'no' );
					break;
				case 'int':
					update_option( $key, absint( $raw ) );
					break;
				case 'email':
					update_option( $key, sanitize_email( $raw ) );
					break;
				default:
					update_option( $key, sanitize_text_field( $raw ) );
			}
		}

		// Discount tiers.
		$tier_mins    = array_map( 'floatval', wp_unslash( $_POST['exitsurvey_coupon_tier_min']    ?? [] ) );
		$tier_maxs    = array_map( 'floatval', wp_unslash( $_POST['exitsurvey_coupon_tier_max']    ?? [] ) );
		$tier_types   = array_map( 'sanitize_text_field', wp_unslash( $_POST['exitsurvey_coupon_tier_type']   ?? [] ) );
		$tier_amounts = array_map( 'floatval', wp_unslash( $_POST['exitsurvey_coupon_tier_amount'] ?? [] ) );

		$tiers = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$amount = $tier_amounts[ $i ] ?? 0;
			if ( $amount <= 0 ) {
				continue; // Skip empty tiers.
			}
			$tiers[] = [
				'min_cart'      => $tier_mins[ $i ] ?? 0,
				'max_cart'      => $tier_maxs[ $i ] ?? 0,
				'discount_type' => in_array( $tier_types[ $i ] ?? 'percent', [ 'percent', 'fixed_cart' ], true ) ? $tier_types[ $i ] : 'percent',
				'amount'        => $amount,
			];
		}
		update_option( 'exitsurvey_coupon_tiers', wp_json_encode( $tiers ) );
		// phpcs:enable
	}

	/* ------------------------------------------------------------------
	 * FRONTEND: ENRICH QUESTION DATA
	 * --------------------------------------------------------------- */

	/**
	 * Expose coupon_trigger_answers to the frontend JS payload.
	 *
	 * @param array $row Question data.
	 * @return array
	 */
	public static function enrich_question_data( $row ) {
		$row['coupon_trigger_answers'] = $row['coupon_trigger_answers'] ?? '';
		return $row;
	}

	/* ------------------------------------------------------------------
	 * CORE: HANDLE SAVED RESPONSE
	 * --------------------------------------------------------------- */

	/**
	 * After a survey response is saved:
	 *  1. Determine whether to trigger a coupon (per-answer rules).
	 *  2. Select the correct discount tier from cart value.
	 *  3. Generate a WooCommerce coupon (single-use, with expiry).
	 *  4. Optionally send a cart recovery email.
	 *
	 * @param string $question_id The question key.
	 * @param string $answer      Full answer text (may contain "| Note: email").
	 * @param int    $response_id DB insert ID.
	 */
	public static function handle_response( $question_id, $answer, $response_id ) {
		if ( get_option( 'exitsurvey_coupon_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		// ------------------------------------------------------------------
		// 1. Fetch the question's per-answer trigger list.
		// ------------------------------------------------------------------
		global $wpdb;
		$q_table  = $wpdb->prefix . 'exitsurvey_questions';
		$question = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT coupon_trigger_answers FROM ' . sanitize_key( $q_table ) . ' WHERE question_key = %s', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$question_id
			),
			ARRAY_A
		);

		if ( ! self::should_trigger_coupon( $question, $answer ) ) {
			return;
		}

		// ------------------------------------------------------------------
		// 2. Fetch the stored response row for cart_value + cart_items.
		// ------------------------------------------------------------------
		$r_table  = $wpdb->prefix . 'exitsurvey_responses';
		$response = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT cart_value, cart_items FROM ' . sanitize_key( $r_table ) . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$response_id
			),
			ARRAY_A
		);

		$cart_value = $response ? (float) $response['cart_value'] : 0;
		$cart_items = $response ? $response['cart_items'] : '';

		// ------------------------------------------------------------------
		// 3. Generate a coupon.
		// ------------------------------------------------------------------
		$coupon_data = self::generate_coupon( $cart_value );
		if ( ! $coupon_data ) {
			return;
		}

		$coupon_code = $coupon_data['code'];

		// Store coupon data on the response row for later retrieval by the filter.
		$wpdb->update(
			$r_table,
			[ 'extra_info' => $coupon_data['extra_info_json'] ],
			[ 'id' => $response_id ]
		);

		// Auto-apply to cart if WooCommerce cart is available.
		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->apply_coupon( $coupon_code );
			// Calculate totals to ensure discount is reflected.
			WC()->cart->calculate_totals();
		}

		// ------------------------------------------------------------------
		// 4. Optionally send cart recovery email.
		// ------------------------------------------------------------------
		if ( get_option( 'exitsurvey_cart_recovery_email_enabled', 'no' ) === 'yes' ) {
			$email = self::extract_email_from_answer( $answer );
			if ( ! empty( $email ) && is_email( $email ) ) {
				self::send_cart_recovery_email( sanitize_email( $email ), $coupon_code, $cart_items );
			}
		}
	}

	/**
	 * Filter hook: append coupon_code to the AJAX success JSON.
	 *
	 * @param array  $data        Current response data array.
	 * @param string $question_id Question key.
	 * @param int    $response_id DB response ID.
	 * @return array
	 */
	public static function append_coupon_to_response( $data, $question_id, $response_id ) {
		global $wpdb;
		$r_table  = $wpdb->prefix . 'exitsurvey_responses';
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT extra_info FROM ' . sanitize_key( $r_table ) . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$response_id
			),
			ARRAY_A
		);

		if ( empty( $row['extra_info'] ) ) {
			return $data;
		}

		$info = json_decode( $row['extra_info'], true );
		if ( empty( $info['coupon_code'] ) ) {
			return $data;
		}

		$code = $info['coupon_code'];
		$coupon = new WC_Coupon( $code );

		$data['coupon_code']     = $code;
		$data['coupon_discount'] = $coupon->get_discount_type() === 'percent'
			? $coupon->get_amount() . '%'
			: wc_price( $coupon->get_amount() );
		$data['coupon_expiry']   = $info['coupon_expiry_timestamp'] ?? 0;

		return $data;
	}

	/* ------------------------------------------------------------------
	 * COUPON LOGIC
	 * --------------------------------------------------------------- */

	/**
	 * Determine whether a coupon should be triggered for this answer.
	 *
	 * @param array|null $question Question row (may be null if question not found).
	 * @param string     $answer   Full answer string.
	 * @return bool
	 */
	public static function should_trigger_coupon( $question, $answer ) {
		if ( ! is_array( $question ) ) {
			// Question not found — allow coupon by default (global setting is already checked).
			return true;
		}

		$trigger_list = trim( $question['coupon_trigger_answers'] ?? '' );

		if ( empty( $trigger_list ) ) {
			// No restrictions — always trigger.
			return true;
		}

		// Extract the primary answer (before "| Note:").
		$primary_answer = $answer;
		if ( strpos( $answer, '| Note:' ) !== false ) {
			$parts          = explode( '| Note:', $answer, 2 );
			$primary_answer = trim( $parts[0] );
		}

		$allowed = array_filter( array_map( 'trim', explode( "\n", $trigger_list ) ) );
		foreach ( $allowed as $trigger ) {
			if ( strcasecmp( $primary_answer, $trigger ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a unique WC coupon for the given cart value.
	 *
	 * @param float $cart_value Cart subtotal (0 if unknown).
	 * @return string|false Coupon code on success, false if no tier matches.
	 */
	public static function generate_coupon( $cart_value ) {
		$tiers = json_decode( get_option( 'exitsurvey_coupon_tiers', '[]' ), true );

		if ( empty( $tiers ) ) {
			return false;
		}

		// Find the matching tier.
		$matched_tier = null;
		foreach ( $tiers as $tier ) {
			$min = (float) $tier['min_cart'];
			$max = (float) $tier['max_cart'];

			$above_min = ( $cart_value >= $min );
			$below_max = ( $max <= 0 || $cart_value <= $max );

			if ( $above_min && $below_max ) {
				$matched_tier = $tier;
				break;
			}
		}

		if ( ! $matched_tier ) {
			// Fall back to first tier when cart value is 0 (guest before cart data loads).
			$matched_tier = $tiers[0];
		}

		$prefix = strtoupper( sanitize_text_field( get_option( 'exitsurvey_coupon_prefix', 'EXIT' ) ) );
		$suffix = strtoupper( wp_generate_password( 6, false, false ) );
		$code   = $prefix . '-' . $suffix;

		// Build expiry date.
		$expiry_hours = (int) get_option( 'exitsurvey_coupon_expiry_hours', 24 );
		$expiry_date  = '';
		if ( $expiry_hours > 0 ) {
			$expiry_date = gmdate( 'Y-m-d', strtotime( "+{$expiry_hours} hours" ) );
		}

		// Create the WC coupon.
		$coupon = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( $matched_tier['discount_type'] );
		$coupon->set_amount( (float) $matched_tier['amount'] );
		$coupon->set_usage_limit( 1 );               // Single-use.
		$coupon->set_usage_limit_per_user( 1 );      // Once per user.
		$coupon->set_individual_use( false );
		if ( $expiry_date ) {
			$coupon->set_date_expires( $expiry_date );
		}
		$coupon->set_description( __( 'Auto-generated by ExitSurvey — Conversion Recovery.', 'exitsurvey' ) );

		$result = $coupon->save();

		if ( ! $result ) {
			error_log( '[ExitSurvey] Failed to save WC coupon: ' . $code );
			return false;
		}

		// Save additional info into DB for the AJAX response payload.
		$expiry_timestamp = $expiry_hours > 0 ? time() + ( $expiry_hours * 3600 ) : 0;
		$extra_info = wp_json_encode( [
			'coupon_code'             => $code,
			'coupon_expiry_timestamp' => $expiry_timestamp,
		] );

		return [
			'code'             => $code,
			'expiry_timestamp' => $expiry_timestamp,
			'extra_info_json'  => $extra_info,
		];
	}

	/* ------------------------------------------------------------------
	 * CART RECOVERY EMAIL
	 * --------------------------------------------------------------- */

	/**
	 * Send the cart recovery HTML email to the visitor.
	 *
	 * @param string $email       Valid email address.
	 * @param string $coupon_code Generated coupon code.
	 * @param string $cart_items  JSON-encoded cart items array.
	 */
	public static function send_cart_recovery_email( $email, $coupon_code, $cart_items ) {
		$subject   = get_option( 'exitsurvey_cart_recovery_email_subject', "You left something behind — here's a discount!" );
		$from_name = get_option( 'exitsurvey_cart_recovery_from_name', get_bloginfo( 'name' ) );
		$from_addr = get_option( 'exitsurvey_cart_recovery_from_email', get_option( 'admin_email' ) );

		$items = json_decode( $cart_items, true );
		if ( ! is_array( $items ) ) {
			$items = [];
		}

		// Build cart rows HTML.
		$items_html = '';
		foreach ( $items as $item ) {
			$name  = esc_html( $item['name'] ?? '' );
			$qty   = esc_html( $item['qty'] ?? 1 );
			$price = wp_kses_post( $item['price'] ?? '' );
			$img   = esc_url( $item['image'] ?? '' );
			$url   = esc_url( $item['url'] ?? '' );

			$items_html .= '<tr style="border-bottom:1px solid #f0f0f0;">';
			if ( $img ) {
				$items_html .= '<td style="padding:12px 8px; width:64px;"><img src="' . $img . '" width="56" height="56" style="border-radius:6px; object-fit:cover;" alt="' . $name . '"></td>';
			}
			$items_html .= '<td style="padding:12px 8px;">';
			$items_html .= '<a href="' . $url . '" style="font-size:14px;font-weight:600;color:#1e1b4b;text-decoration:none;">' . $name . '</a>';
			$items_html .= '<br><span style="color:#6b7280;font-size:13px;">Qty: ' . $qty . ' &middot; ' . $price . '</span>';
			$items_html .= '</td></tr>';
		}

		$cart_section = '';
		if ( $items_html ) {
			$cart_section = '
			<h3 style="font-size:16px;font-weight:700;color:#374151;margin:24px 0 12px;">🛒 Items still in your cart</h3>
			<table width="100%" cellspacing="0" cellpadding="0" style="background:#f8f7ff;border-radius:10px;overflow:hidden;">'
				. $items_html .
			'</table>';
		}

		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : home_url( '/checkout/' );
		$store_name   = esc_html( get_bloginfo( 'name' ) );

		$expiry_hours = (int) get_option( 'exitsurvey_coupon_expiry_hours', 24 );
		$expiry_note  = $expiry_hours > 0
			? sprintf( esc_html__( 'This code expires in %d hours.', 'exitsurvey' ), $expiry_hours )
			: esc_html__( 'This code does not expire.', 'exitsurvey' );

		$body = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
		<body style="margin:0;padding:0;background:#f4f6fb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;">
		<table width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6fb;padding:32px 16px;">
		  <tr><td align="center">
		    <table width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
		      <!-- Header -->
		      <tr><td style="background:linear-gradient(135deg,#2563eb 0%,#3b82f6 100%);padding:32px 32px 24px;text-align:center;">
		        <p style="margin:0 0 6px;font-size:36px;">🎟️</p>
		        <h1 style="margin:0 0 8px;font-size:24px;font-weight:800;color:#fff;">You left something behind!</h1>
		        <p style="margin:0;font-size:15px;color:rgba(255,255,255,.85);">Here\'s a special discount just for you from ' . $store_name . '</p>
		      </td></tr>
		      <!-- Coupon Box -->
		      <tr><td style="padding:28px 32px 0;">
		        <div style="background:#fefce8;border:2px dashed #f59e0b;border-radius:12px;padding:20px;text-align:center;margin-bottom:8px;">
		          <p style="margin:0 0 8px;font-size:13px;color:#92400e;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">Your Exclusive Discount Code</p>
		          <p style="margin:0 0 8px;font-size:32px;font-weight:900;color:#1e1b4b;letter-spacing:.1em;font-family:monospace;">' . esc_html( $coupon_code ) . '</p>
		          <p style="margin:0;font-size:12px;color:#b45309;">' . $expiry_note . ' Single use only.</p>
		        </div>
		      </td></tr>
		      <!-- Cart Items -->
		      <tr><td style="padding:0 32px;">' . $cart_section . '</td></tr>
		      <!-- CTA -->
		      <tr><td style="padding:28px 32px;text-align:center;">
		        <a href="' . esc_url( $checkout_url ) . '" style="display:inline-block;background:linear-gradient(135deg,#2563eb,#3b82f6);color:#fff;text-decoration:none;font-size:16px;font-weight:700;padding:16px 36px;border-radius:10px;">
		          ✅ Complete My Purchase →
		        </a>
		      </td></tr>
		      <!-- Footer -->
		      <tr><td style="padding:0 32px 28px;text-align:center;color:#9ca3af;font-size:12px;">
		        <p style="margin:0;">' . $store_name . '</p>
		      </td></tr>
		    </table>
		  </td></tr>
		</table>
		</body></html>';

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_addr ) . '>',
		];

		wp_mail( $email, $subject, $body, $headers );
	}

	/* ------------------------------------------------------------------
	 * HELPERS
	 * --------------------------------------------------------------- */

	/**
	 * Extract an email address from the answer string.
	 * The frontend sends: "{answer} | Note: {extra_field_value}".
	 *
	 * @param string $answer Full answer text.
	 * @return string Email or empty string.
	 */
	private static function extract_email_from_answer( $answer ) {
		if ( strpos( $answer, '| Note: ' ) !== false ) {
			$parts     = explode( '| Note: ', $answer, 2 );
			$candidate = trim( $parts[1] ?? '' );
			if ( is_email( $candidate ) ) {
				return $candidate;
			}
		}
		$trimmed = trim( $answer );
		if ( is_email( $trimmed ) ) {
			return $trimmed;
		}
		return '';
	}
}
