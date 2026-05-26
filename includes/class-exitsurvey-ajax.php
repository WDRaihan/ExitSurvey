<?php
/**
 * AJAX endpoints.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Ajax {

	public static function init() {
		// Both logged-in and guest users
		add_action( 'wp_ajax_exitsurvey_submit',        [ __CLASS__, 'submit_response' ] );
		add_action( 'wp_ajax_nopriv_exitsurvey_submit', [ __CLASS__, 'submit_response' ] );

		add_action( 'wp_ajax_exitsurvey_get_questions',        [ __CLASS__, 'get_questions' ] );
		add_action( 'wp_ajax_nopriv_exitsurvey_get_questions', [ __CLASS__, 'get_questions' ] );

		add_action( 'wp_ajax_exitsurvey_get_cart',        [ __CLASS__, 'get_cart' ] );
		add_action( 'wp_ajax_nopriv_exitsurvey_get_cart', [ __CLASS__, 'get_cart' ] );
	}

	/**
	 * Save a survey response.
	 */
	public static function submit_response() {
		check_ajax_referer( 'exitsurvey_nonce', 'nonce' );

		global $wpdb;

		$session_id    = sanitize_text_field( wp_unslash( $_POST['session_id'] ?? '' ) );
		$question_id   = sanitize_text_field( wp_unslash( $_POST['question_id'] ?? '' ) );
		$question_text = sanitize_textarea_field( wp_unslash( $_POST['question_text'] ?? '' ) );
		$answer        = sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) );
		$trigger_type  = sanitize_text_field( wp_unslash( $_POST['trigger_type'] ?? 'general' ) );
		$cart_value    = isset( $_POST['cart_value'] ) ? floatval( wp_unslash( $_POST['cart_value'] ) ) : null;
		$cart_items    = sanitize_textarea_field( wp_unslash( $_POST['cart_items'] ?? '' ) );
		$page_history  = sanitize_textarea_field( wp_unslash( $_POST['page_history'] ?? '' ) );

		if ( empty( $session_id ) || empty( $question_id ) || empty( $answer ) ) {
			wp_send_json_error( [ 'message' => 'Missing required fields.' ], 400 );
		}

		$table  = $wpdb->prefix . 'exitsurvey_responses';
		$result = $wpdb->insert( $table, [
			'session_id'    => $session_id,
			'user_id'       => get_current_user_id() ?: null,
			'question_id'   => $question_id,
			'question_text' => $question_text,
			'answer'        => $answer,
			'trigger_type'  => $trigger_type,
			'cart_value'    => $cart_value,
			'cart_items'    => $cart_items,
			'page_history'  => $page_history,
			'ip_address'    => self::get_ip(),
			'user_agent'    => substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 500 ),
		] );

		if ( false === $result ) {
			wp_send_json_error( [ 'message' => 'Could not save response.' ], 500 );
		}

		// Allow modules to react to saved response (e.g. email marketing)
		do_action( 'exitsurvey_after_response_saved', $question_id, $answer, $wpdb->insert_id );

		// Email notification
		if ( ExitSurvey_Settings::get( 'email_notify' ) === 'yes' ) {
			self::send_notification( $question_text, $answer, $trigger_type, $cart_value );
		}

		wp_send_json_success( [ 'message' => 'Response saved.', 'id' => $wpdb->insert_id ] );
	}

	/**
	 * Return questions for the current trigger context.
	 */
	public static function get_questions() {
		check_ajax_referer( 'exitsurvey_nonce', 'nonce' );

		$trigger     = sanitize_text_field( wp_unslash( $_POST['trigger'] ?? 'general' ) );
		$all         = ExitSurvey_Settings::get_questions_by_trigger();
		$questions   = [];

		// Priority: specific trigger > cart (if cart items present) > general
		if ( ! empty( $all[ $trigger ] ) ) {
			$questions = $all[ $trigger ];
		} elseif ( ! empty( $all['general'] ) ) {
			$questions = $all['general'];
		}

		wp_send_json_success( [ 'questions' => array_values( $questions ) ] );
	}

	/**
	 * Return current WooCommerce cart data.
	 */
	public static function get_cart() {
		check_ajax_referer( 'exitsurvey_nonce', 'nonce' );

		$cart_data = [];

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$cart  = WC()->cart;
			$items = [];

			foreach ( $cart->get_cart() as $item_key => $item ) {
				$product = $item['data'];
				if ( ! $product ) {
					continue;
				}

				$image_id  = $product->get_image_id();
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );

				$items[] = [
					'key'       => $item_key,
					'name'      => $product->get_name(),
					'qty'       => $item['quantity'],
					'price'     => wc_price( $item['line_total'] ),
					'raw_price' => (float) $item['line_total'],
					'image'     => $image_url,
					'url'       => get_permalink( $product->get_id() ),
				];
			}

			$cart_data = [
				'items'       => $items,
				'total'       => $cart->get_cart_total(),
				'raw_total'   => (float) $cart->get_subtotal(),
				'count'       => $cart->get_cart_contents_count(),
				'checkout_url'=> wc_get_checkout_url(),
				'cart_url'    => wc_get_cart_url(),
			];
		}

		wp_send_json_success( $cart_data );
	}

	/**
	 * Send admin email notification.
	 */
	private static function send_notification( $question, $answer, $trigger, $cart_value ) {
		$to      = ExitSurvey_Settings::get( 'notify_email', get_option( 'admin_email' ) );
		$subject = sprintf( '[ExitSurvey] New response — %s trigger', ucfirst( $trigger ) );
		$body    = "A new ExitSurvey response has been recorded:\n\n";
		$body   .= "Trigger: " . ucfirst( $trigger ) . "\n";
		if ( $cart_value ) {
			$body .= "Cart Value: " . wc_price( $cart_value ) . "\n";
		}
		$body .= "\nQuestion: {$question}\n";
		$body .= "Answer: {$answer}\n";
		$body .= "\nView all responses in WP Admin → ExitSurvey → Responses";

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Get visitor IP safely.
	 */
	private static function get_ip() {
		foreach ( [ 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ] as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( explode( ',', wp_unslash( $_SERVER[ $key ] ) )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
