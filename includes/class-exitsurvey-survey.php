<?php
/**
 * Survey decision logic.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Survey {

	/**
	 * Determine the most relevant trigger based on context.
	 * Called from JS via data attribute; kept here for documentation.
	 *
	 * Priority: checkout > cart > product > shop > general
	 *
	 * @param array $context {
	 *   @type bool   $has_cart_items
	 *   @type bool   $visited_checkout
	 *   @type bool   $visited_product
	 *   @type bool   $visited_shop
	 * }
	 * @return string trigger type
	 */
	public static function resolve_trigger( array $context ) {
		if ( ! empty( $context['visited_checkout'] ) ) {
			return 'checkout';
		}
		if ( ! empty( $context['has_cart_items'] ) ) {
			return 'cart';
		}
		if ( ! empty( $context['visited_product'] ) ) {
			return 'product';
		}
		if ( ! empty( $context['visited_shop'] ) ) {
			return 'shop';
		}
		return 'general';
	}

	/**
	 * Get all questions for a trigger from DB.
	 */
	public static function get_questions_for_trigger( $trigger ) {
		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_questions';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE trigger_type = %s AND is_active = 1 ORDER BY sort_order ASC",
				$trigger
			),
			ARRAY_A
		);
	}
}
