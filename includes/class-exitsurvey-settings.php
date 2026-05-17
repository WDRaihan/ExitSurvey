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
			'adminBypass'     => self::get( 'admin_bypass', 'no' ) === 'yes',
			'popupTitle'      => self::get( 'popup_title', 'Wait! Before you go...' ),
			'popupSubtitle'   => self::get( 'popup_subtitle', 'Help us understand how we can serve you better.' ),
			'brandingColor'   => self::get( 'branding_color', '#2563eb' ),
			'brandingColor2'  => self::get( 'branding_color_2', '#3b82f6' ),
			'extraFieldEnabled'     => self::get( 'extra_field_enabled', 'no' ) === 'yes',
			'extraFieldLabel'       => self::get( 'extra_field_label', 'Share your email for a discount code' ),
			'extraFieldPlaceholder' => self::get( 'extra_field_placeholder', 'Your email address...' ),
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
	 * Filters by user segmentation rules (user type + order history).
	 * Cart value filtering happens client-side.
	 */
	public static function get_questions_by_trigger() {
		global $wpdb;
		$table = sanitize_key( $wpdb->prefix . 'exitsurvey_questions' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM " . $table . " WHERE is_active = 1 ORDER BY sort_order ASC" ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		$is_logged_in = is_user_logged_in();
		$order_count  = 0;
		if ( $is_logged_in && function_exists( 'wc_get_customer_order_count' ) ) {
			$order_count = wc_get_customer_order_count( get_current_user_id() );
		}

		$grouped = [];
		foreach ( $rows as $row ) {
			$row['options'] = $row['options'] ? json_decode( $row['options'], true ) : [];
			$row['extra_field_enabled'] = (bool) $row['extra_field_enabled'];

			// Parse segment rules
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
				continue;
			}
			if ( 'logged_in' === $seg['user_type'] && ! $is_logged_in ) {
				continue;
			}

			// Filter: Order history (only for logged-in users)
			if ( $seg['min_orders'] > 0 && $order_count < $seg['min_orders'] ) {
				continue;
			}
			if ( $seg['max_orders'] > 0 && $order_count > $seg['max_orders'] ) {
				continue;
			}

			// Pass cart value rules to JS for client-side filtering
			$row['segment_rules'] = $seg;

			$grouped[ $row['trigger_type'] ][] = $row;
		}
		return $grouped;
	}
}
