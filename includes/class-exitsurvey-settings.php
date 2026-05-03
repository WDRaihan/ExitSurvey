<?php
/**
 * Settings helper.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Settings {

	/**
	 * Get a plugin option.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$full_key = strpos( $key, 'exitsurvey_' ) === 0 ? $key : 'exitsurvey_' . $key;
		return get_option( $full_key, $default );
	}

	/**
	 * Update a plugin option.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public static function set( $key, $value ) {
		$full_key = strpos( $key, 'exitsurvey_' ) === 0 ? $key : 'exitsurvey_' . $key;
		update_option( $full_key, $value );
	}

	/**
	 * Return all settings as an array for JS.
	 */
	public static function get_js_config() {
		return [
			'enabled'         => self::get( 'enabled', 'yes' ) === 'yes',
			'delayMs'         => (int) self::get( 'delay_ms', 500 ),
			'sensitivity'     => (int) self::get( 'sensitivity', 20 ),
			'showCartItems'   => self::get( 'show_cart_items', 'yes' ) === 'yes',
			'showOnMobile'    => self::get( 'show_on_mobile', 'no' ) === 'yes',
			'cookieDays'      => (int) self::get( 'cookie_days', 3 ),
			'popupTitle'      => self::get( 'popup_title', 'Wait! Before you go...' ),
			'popupSubtitle'   => self::get( 'popup_subtitle', 'Help us understand how we can serve you better.' ),
			'brandingColor'   => self::get( 'branding_color', '#7c3aed' ),
			'submitLabel'     => self::get( 'submit_label', 'Submit & Continue' ),
			'skipLabel'       => self::get( 'skip_label', 'No thanks, just leave' ),
			'thankYouMsg'     => self::get( 'thank_you_msg', 'Thank you for your feedback! 🙏' ),
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'exitsurvey_nonce' ),
			'currency'        => get_woocommerce_currency_symbol(),
			'excludedPages'   => (array) self::get( 'excluded_pages', [] ),
		];
	}

	/**
	 * Get all active questions grouped by trigger type.
	 */
	public static function get_questions_by_trigger() {
		global $wpdb;
		$table = sanitize_key( $wpdb->prefix . 'exitsurvey_questions' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM " . $table . " WHERE is_active = 1 ORDER BY sort_order ASC" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$grouped = [];
		foreach ( $rows as $row ) {
			$row['options'] = $row['options'] ? json_decode( $row['options'], true ) : [];
			$grouped[ $row['trigger_type'] ][] = $row;
		}
		return $grouped;
	}
}
