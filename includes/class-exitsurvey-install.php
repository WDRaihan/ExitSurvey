<?php
/**
 * Installation & database setup.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Install {

	public static function init() {
		if ( is_admin() ) {
			self::maybe_update_database();
		}
	}

	/**
	 * Runs on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::insert_default_questions();
		self::set_default_options();
		self::maybe_update_database();
		update_option( 'exitsurvey_version', EXITSURVEY_VERSION );
	}

	/**
	 * Create custom DB tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Survey responses table
		$responses_table = $wpdb->prefix . 'exitsurvey_responses';
		$sql_responses = "CREATE TABLE IF NOT EXISTS {$responses_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id    VARCHAR(64)         NOT NULL,
			user_id       BIGINT(20) UNSIGNED          DEFAULT NULL,
			question_id   VARCHAR(64)         NOT NULL,
			question_text TEXT                NOT NULL,
			answer        TEXT                NOT NULL,
			trigger_type  VARCHAR(32)         NOT NULL DEFAULT 'cart',
			cart_value    DECIMAL(12,2)                DEFAULT NULL,
			cart_items    LONGTEXT                     DEFAULT NULL,
			page_history  LONGTEXT                     DEFAULT NULL,
			ip_address    VARCHAR(45)                  DEFAULT NULL,
			user_agent    TEXT                         DEFAULT NULL,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_session  (session_id),
			KEY idx_trigger  (trigger_type),
			KEY idx_created  (created_at)
		) {$charset_collate};";

		// Questions table
		$questions_table = $wpdb->prefix . 'exitsurvey_questions';
		$sql_questions = "CREATE TABLE IF NOT EXISTS {$questions_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			question_key  VARCHAR(64)         NOT NULL,
			trigger_type  VARCHAR(32)         NOT NULL DEFAULT 'cart',
			question_text TEXT                NOT NULL,
			options       LONGTEXT                     DEFAULT NULL,
			question_type VARCHAR(32)         NOT NULL DEFAULT 'multiple_choice',
			is_active           TINYINT(1)          NOT NULL DEFAULT 1,
			sort_order          INT(11)             NOT NULL DEFAULT 0,
			extra_field_enabled TINYINT(1)          NOT NULL DEFAULT 0,
			extra_field_label   TEXT                         DEFAULT NULL,
			segment_rules       TEXT                         DEFAULT NULL,
			created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY  uq_key (question_key),
			KEY idx_trigger (trigger_type),
			KEY idx_active  (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_responses );
		dbDelta( $sql_questions );
	}

	/**
	 * Run lightweight migrations for existing tables.
	 */
	private static function maybe_update_database() {
		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_responses';

		// Add extra_info to responses table if missing
		$column = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'extra_info' AND TABLE_SCHEMA = %s", $table, DB_NAME ) );
		if ( empty( $column ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD COLUMN extra_info TEXT DEFAULT NULL AFTER answer" );
		}

		// Add extra_field columns to questions table if missing
		$q_table = $wpdb->prefix . 'exitsurvey_questions';
		$columns = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME IN ('extra_field_enabled', 'extra_field_label') AND TABLE_SCHEMA = %s", $q_table, DB_NAME ) );
		$existing_cols = wp_list_pluck( $columns, 'COLUMN_NAME' );

		if ( ! in_array( 'extra_field_enabled', $existing_cols ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN extra_field_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order" );
		}
		if ( ! in_array( 'extra_field_label', $existing_cols ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN extra_field_label TEXT DEFAULT NULL AFTER extra_field_enabled" );
		}

		// Add segment_rules column if missing
		$seg_col = $wpdb->get_results( $wpdb->prepare( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = %s AND COLUMN_NAME = 'segment_rules' AND TABLE_SCHEMA = %s", $q_table, DB_NAME ) );
		if ( empty( $seg_col ) ) {
			$wpdb->query( "ALTER TABLE {$q_table} ADD COLUMN segment_rules TEXT DEFAULT NULL AFTER extra_field_label" );
		}
	}

	/**
	 * Insert built-in default questions.
	 */
	private static function insert_default_questions() {
		global $wpdb;
		$table = sanitize_key( $wpdb->prefix . 'exitsurvey_questions' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Only seed if table is empty
		if ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . $table ) ) > 0 ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return;
		}

		$defaults = [
			// Cart abandonment questions
			[
				'question_key'  => 'cart_leaving_reason',
				'trigger_type'  => 'cart',
				'question_text' => 'You have items in your cart! What\'s stopping you from completing your purchase?',
				'options'       => json_encode( [
					'Too expensive',
					'Just browsing',
					'Need more time to decide',
					'Found a better deal elsewhere',
					'Shipping cost too high',
					'Payment issues',
					'Other',
				] ),
				'question_type' => 'multiple_choice',
				'sort_order'    => 1,
			],
			[
				'question_key'  => 'cart_discount_help',
				'trigger_type'  => 'cart',
				'question_text' => 'Would a discount help you complete your purchase today?',
				'options'       => json_encode( [ 'Yes, definitely!', 'Maybe', 'No, that\'s not the issue' ] ),
				'question_type' => 'multiple_choice',
				'sort_order'    => 2,
			],
			// Checkout abandonment
			[
				'question_key'  => 'checkout_abandon_reason',
				'trigger_type'  => 'checkout',
				'question_text' => 'You were almost done! Why are you leaving the checkout?',
				'options'       => json_encode( [
					'Unexpected shipping costs',
					'Wanted to use a coupon code',
					'Payment method not available',
					'Site felt insecure',
					'Technical problem',
					'Changed my mind',
					'Other',
				] ),
				'question_type' => 'multiple_choice',
				'sort_order'    => 3,
			],
			// Shop / category browsing
			[
				'question_key'  => 'shop_not_found',
				'trigger_type'  => 'shop',
				'question_text' => 'We noticed you were browsing our shop. Did you find what you were looking for?',
				'options'       => json_encode( [ 'Yes, but decided not to buy', 'No, couldn\'t find it', 'Just exploring', 'Price was too high' ] ),
				'question_type' => 'multiple_choice',
				'sort_order'    => 4,
			],
			// Product page abandonment
			[
				'question_key'  => 'product_no_add',
				'trigger_type'  => 'product',
				'question_text' => 'You viewed a product but didn\'t add it to your cart. What held you back?',
				'options'       => json_encode( [
					'Price too high',
					'Not sure about quality',
					'Need more product info',
					'Wrong size / colour available',
					'Will come back later',
					'Other',
				] ),
				'question_type' => 'multiple_choice',
				'sort_order'    => 5,
			],
			// Open feedback
			[
				'question_key'  => 'general_feedback',
				'trigger_type'  => 'general',
				'question_text' => 'Before you go — any feedback to help us improve your experience?',
				'options'       => null,
				'question_type' => 'text',
				'sort_order'    => 6,
			],
		];

		foreach ( $defaults as $q ) {
			$wpdb->insert( $table, [
				'question_key'  => $q['question_key'],
				'trigger_type'  => $q['trigger_type'],
				'question_text' => $q['question_text'],
				'options'       => $q['options'],
				'question_type' => $q['question_type'],
				'sort_order'    => $q['sort_order'],
				'is_active'     => 1,
			] );
		}
	}

	/**
	 * Set sane plugin defaults.
	 */
	private static function set_default_options() {
		$defaults = [
			'exitsurvey_enabled'          => 'yes',
			'exitsurvey_delay_ms'         => 500,
			'exitsurvey_sensitivity'      => 20,
			'exitsurvey_show_cart_items'  => 'yes',
			'exitsurvey_show_on_mobile'   => 'no',
			'exitsurvey_cookie_days'      => 3,
			'exitsurvey_popup_title'      => 'Wait! Before you go...',
			'exitsurvey_popup_subtitle'   => 'Help us understand how we can serve you better.',
			'exitsurvey_branding_color'   => '#7c3aed',
			'exitsurvey_submit_label'     => 'Submit & Continue',
			'exitsurvey_skip_label'       => 'No thanks, just leave',
			'exitsurvey_thank_you_msg'    => 'Thank you for your feedback! 🙏',
			'exitsurvey_email_notify'     => 'no',
			'exitsurvey_notify_email'     => get_option( 'admin_email' ),
			'exitsurvey_excluded_pages'   => [],
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}
}
