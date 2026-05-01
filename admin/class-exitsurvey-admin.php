<?php
/**
 * Admin area.
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

class ExitSurvey_Admin {

	public static function init() {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'admin_post_exitsurvey_save_settings',  [ __CLASS__, 'save_settings' ] );
		add_action( 'admin_post_exitsurvey_save_questions', [ __CLASS__, 'save_questions' ] );
		add_action( 'admin_post_exitsurvey_delete_response', [ __CLASS__, 'delete_response' ] );
		add_filter( 'plugin_action_links_' . EXITSURVEY_BASENAME, [ __CLASS__, 'plugin_links' ] );
	}

	/**
	 * Register top-level menu + sub-pages.
	 */
	public static function register_menus() {
		add_menu_page(
			__( 'ExitSurvey', 'exitsurvey' ),
			__( 'ExitSurvey', 'exitsurvey' ),
			'manage_woocommerce',
			'exitsurvey',
			[ __CLASS__, 'page_dashboard' ],
			'dashicons-feedback',
			56
		);

		add_submenu_page( 'exitsurvey', __( 'Dashboard', 'exitsurvey' ), __( 'Dashboard', 'exitsurvey' ), 'manage_woocommerce', 'exitsurvey', [ __CLASS__, 'page_dashboard' ] );
		add_submenu_page( 'exitsurvey', __( 'Responses', 'exitsurvey' ), __( 'Responses', 'exitsurvey' ), 'manage_woocommerce', 'exitsurvey-responses', [ __CLASS__, 'page_responses' ] );
		add_submenu_page( 'exitsurvey', __( 'Questions', 'exitsurvey' ), __( 'Questions', 'exitsurvey' ), 'manage_woocommerce', 'exitsurvey-questions', [ __CLASS__, 'page_questions' ] );
		add_submenu_page( 'exitsurvey', __( 'Settings', 'exitsurvey' ), __( 'Settings', 'exitsurvey' ), 'manage_woocommerce', 'exitsurvey-settings', [ __CLASS__, 'page_settings' ] );
	}

	/**
	 * Enqueue admin assets.
	 */
	public static function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'exitsurvey' ) === false ) {
			return;
		}
		wp_enqueue_style( 'exitsurvey-admin', EXITSURVEY_URL . 'admin/admin.css', [], EXITSURVEY_VERSION );
		wp_enqueue_script( 'exitsurvey-admin', EXITSURVEY_URL . 'admin/admin.js', [ 'jquery', 'wp-color-picker' ], EXITSURVEY_VERSION, true );
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Add links on plugins page.
	 */
	public static function plugin_links( $links ) {
		$extra = [
			'<a href="' . admin_url( 'admin.php?page=exitsurvey-settings' ) . '">' . __( 'Settings', 'exitsurvey' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=exitsurvey' ) . '">' . __( 'Dashboard', 'exitsurvey' ) . '</a>',
		];
		return array_merge( $extra, $links );
	}

	/* -----------------------------------------------------------------------
	 * PAGE RENDERERS
	 * -------------------------------------------------------------------- */

	public static function page_dashboard() {
		$stats = ExitSurvey_Responses::get_stats();
		include EXITSURVEY_PATH . 'admin/views/dashboard.php';
	}

	public static function page_responses() {
		$filter = [
			'trigger_type' => sanitize_text_field( wp_unslash( $_GET['trigger'] ?? '' ) ),
			'search'       => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'page'         => max( 1, (int) wp_unslash( $_GET['paged'] ?? 1 ) ),
		];
		$data = ExitSurvey_Responses::get_responses( $filter );
		include EXITSURVEY_PATH . 'admin/views/responses.php';
	}

	public static function page_questions() {
		global $wpdb;
		$table     = $wpdb->prefix . 'exitsurvey_questions';
		$questions = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY sort_order ASC", ARRAY_A );
		include EXITSURVEY_PATH . 'admin/views/questions.php';
	}

	public static function page_settings() {
		include EXITSURVEY_PATH . 'admin/views/settings.php';
	}

	/* -----------------------------------------------------------------------
	 * FORM HANDLERS
	 * -------------------------------------------------------------------- */

	public static function save_settings() {
		check_admin_referer( 'exitsurvey_settings' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		$fields = [
			'exitsurvey_enabled'          => 'yes_no',
			'exitsurvey_delay_ms'         => 'int',
			'exitsurvey_sensitivity'      => 'int',
			'exitsurvey_show_cart_items'  => 'yes_no',
			'exitsurvey_show_on_mobile'   => 'yes_no',
			'exitsurvey_cookie_days'      => 'int',
			'exitsurvey_popup_title'      => 'text',
			'exitsurvey_popup_subtitle'   => 'text',
			'exitsurvey_branding_color'   => 'color',
			'exitsurvey_submit_label'     => 'text',
			'exitsurvey_skip_label'       => 'text',
			'exitsurvey_thank_you_msg'    => 'text',
			'exitsurvey_email_notify'     => 'yes_no',
			'exitsurvey_notify_email'     => 'email',
		];

		foreach ( $fields as $key => $type ) {
			$raw = wp_unslash( $_POST[ $key ] ?? '' );
			switch ( $type ) {
				case 'yes_no':
					update_option( $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
					break;
				case 'int':
					update_option( $key, absint( $raw ) );
					break;
				case 'email':
					update_option( $key, sanitize_email( $raw ) );
					break;
				case 'color':
					update_option( $key, sanitize_hex_color( $raw ) ?: '#7c3aed' );
					break;
				default:
					update_option( $key, sanitize_text_field( $raw ) );
			}
		}

		wp_redirect( add_query_arg( [ 'page' => 'exitsurvey-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function save_questions() {
		check_admin_referer( 'exitsurvey_questions' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'exitsurvey_questions';

		$ids      = array_map( 'absint', wp_unslash( $_POST['q_id'] ?? [] ) );
		$texts    = wp_unslash( $_POST['q_text'] ?? [] );
		$types    = wp_unslash( $_POST['q_type'] ?? [] );
		$triggers = wp_unslash( $_POST['q_trigger'] ?? [] );
		$opts     = wp_unslash( $_POST['q_options'] ?? [] );
		$active   = wp_unslash( $_POST['q_active'] ?? [] );
		$orders   = wp_unslash( $_POST['q_order'] ?? [] );

		foreach ( $ids as $i => $id ) {
			if ( ! $id ) {
				continue;
			}
			$options_raw = trim( $opts[ $i ] ?? '' );
			$options_arr = array_map( 'sanitize_text_field', array_filter( array_map( 'trim', explode( "\n", $options_raw ) ) ) );

			$wpdb->update( $table, [
				'question_text' => sanitize_textarea_field( $texts[ $i ] ?? '' ),
				'question_type' => sanitize_text_field( $types[ $i ] ?? 'multiple_choice' ),
				'trigger_type'  => sanitize_text_field( $triggers[ $i ] ?? 'general' ),
				'options'       => $options_arr ? json_encode( array_values( $options_arr ) ) : null,
				'is_active'     => isset( $active[ $i ] ) ? 1 : 0,
				'sort_order'    => (int) ( $orders[ $i ] ?? 0 ),
			], [ 'id' => $id ] );
		}

		wp_redirect( add_query_arg( [ 'page' => 'exitsurvey-questions', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function delete_response() {
		check_admin_referer( 'exitsurvey_delete_response' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Unauthorized' );
		}
		$id = absint( wp_unslash( $_GET['id'] ?? 0 ) );
		if ( $id ) {
			ExitSurvey_Responses::delete( $id );
		}
		wp_redirect( add_query_arg( [ 'page' => 'exitsurvey-responses', 'deleted' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
