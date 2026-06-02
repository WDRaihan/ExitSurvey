<?php
/**
 * Targeting Rules.
 *
 * Self-contained module: hooks into the core plugin via action/filter hooks.
 * Can be extracted to a standalone pro plugin in the future.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Targeting_Rules {

	/**
	 * Register all hooks.
	 */
	public static function init() {
		// Database migration.
		add_action( 'exitsurvey_database_update', [ __CLASS__, 'migrate_database' ] );

		// Admin: inject UI.
		add_action( 'exitsurvey_question_targeting_fields', [ __CLASS__, 'render_targeting_fields' ], 10, 2 );

		// Admin: save data.
		add_action( 'exitsurvey_save_question', [ __CLASS__, 'save_targeting_fields' ], 10, 2 );

		// Frontend: filter/enrich question data for JS.
		add_filter( 'exitsurvey_question_row_data', [ __CLASS__, 'filter_and_enrich_question_data' ] );
	}

	/**
	 * Migrate database (add segment_rules if missing).
	 */
	public static function migrate_database() {
		global $wpdb;
		$q_table = $wpdb->prefix . 'exitsurvey_questions';

		$seg_col = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'segment_rules' AND TABLE_SCHEMA = %s",
				$q_table,
				DB_NAME
			)
		);

		if ( empty( $seg_col ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN segment_rules TEXT DEFAULT NULL AFTER extra_field_label" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}

	/**
	 * Render the Targeting Rules fields inside each question card.
	 *
	 * @param array $q         Question data row.
	 * @param int   $row_index Current row index.
	 */
	public static function render_targeting_fields( $q, $row_index ) {
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
		<?php
	}

	/**
	 * Save targeting rules for a question.
	 *
	 * @param int $question_id
	 * @param int $row_index
	 */
	public static function save_targeting_fields( $question_id, $row_index ) {
		global $wpdb;

		$seg_user = array_map( 'sanitize_text_field', wp_unslash( $_POST['q_seg_user_type'] ?? [] ) );
		$seg_mino = array_map( 'absint', wp_unslash( $_POST['q_seg_min_orders'] ?? [] ) );
		$seg_maxo = array_map( 'absint', wp_unslash( $_POST['q_seg_max_orders'] ?? [] ) );
		$seg_minc = wp_unslash( $_POST['q_seg_min_cart'] ?? [] );
		$seg_maxc = wp_unslash( $_POST['q_seg_max_cart'] ?? [] );

		$segment_rules = wp_json_encode( [
			'user_type'      => $seg_user[ $row_index ] ?? 'all',
			'min_orders'     => $seg_mino[ $row_index ] ?? 0,
			'max_orders'     => $seg_maxo[ $row_index ] ?? 0,
			'min_cart_value'  => floatval( $seg_minc[ $row_index ] ?? 0 ),
			'max_cart_value'  => floatval( $seg_maxc[ $row_index ] ?? 0 ),
		] );

		$wpdb->update(
			$wpdb->prefix . 'exitsurvey_questions',
			[ 'segment_rules' => $segment_rules ],
			[ 'id' => $question_id ]
		);
	}

	/**
	 * Filter and enrich question data by targeting rules.
	 *
	 * @param array $row Question data row.
	 * @return array|false Question row, or false if it should be skipped.
	 */
	public static function filter_and_enrich_question_data( $row ) {
		// If $row is already false, pass it through.
		if ( ! $row ) {
			return $row;
		}

		$is_logged_in = is_user_logged_in();
		$order_count  = 0;
		if ( $is_logged_in && function_exists( 'wc_get_customer_order_count' ) ) {
			$order_count = wc_get_customer_order_count( get_current_user_id() );
		}

		$seg = json_decode( $row['segment_rules'] ?? '{}', true ) ?: [];
		$seg = wp_parse_args( $seg, [
			'user_type'      => 'all',
			'min_orders'     => 0,
			'max_orders'     => 0,
			'min_cart_value'  => 0,
			'max_cart_value'  => 0,
		] );

		// Filter: User type
		if ( 'guest' === $seg['user_type'] && $is_logged_in ) {
			return false;
		}
		if ( 'logged_in' === $seg['user_type'] && ! $is_logged_in ) {
			return false;
		}

		// Filter: Order history (only for logged-in users)
		if ( $seg['min_orders'] > 0 && $order_count < $seg['min_orders'] ) {
			return false;
		}
		if ( $seg['max_orders'] > 0 && $order_count > $seg['max_orders'] ) {
			return false;
		}

		// Pass cart value rules to JS for client-side filtering
		$row['segment_rules'] = $seg;

		return $row;
	}
}
