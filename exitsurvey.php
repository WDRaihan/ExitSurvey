<?php
/**
 * Plugin Name: ExitSurvey - Smart Exit Survey for WooCommerce
 * Description: Tracks user browsing behavior and shows smart exit-intent surveys with cart data to recover abandoned carts.
 * Version:     1.0.1
 * Author:      WDRaihan
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: exitsurvey
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main ExitSurvey Class.
 *
 * @final
 */
final class ExitSurvey {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version = '1.0.1';

	/**
	 * The single instance of the class.
	 *
	 * @var ExitSurvey
	 */
	protected static $_instance = null;

	/**
	 * Main ExitSurvey Instance.
	 *
	 * Ensures only one instance of ExitSurvey is loaded or can be loaded.
	 *
	 * @static
	 * @return ExitSurvey - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * ExitSurvey Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define Plugin Constants.
	 */
	private function define_constants() {
		$this->define( 'EXITSURVEY_VERSION', $this->version );
		$this->define( 'EXITSURVEY_FILE', __FILE__ );
		$this->define( 'EXITSURVEY_PATH', plugin_dir_path( __FILE__ ) );
		$this->define( 'EXITSURVEY_URL', plugin_dir_url( __FILE__ ) );
		$this->define( 'EXITSURVEY_BASENAME', plugin_basename( __FILE__ ) );
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param string      $name  Constant name.
	 * @param string|bool $value Constant value.
	 */
	private function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Hook into WordPress.
	 */
	private function init_hooks() {
		register_activation_hook( EXITSURVEY_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( EXITSURVEY_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->includes();
		$this->boot();
		
		do_action( 'exitsurvey_loaded' );
	}

	/**
	 * Load core files.
	 */
	private function includes() {
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-install.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-settings.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-ajax.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-survey.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-responses.php';
		require_once EXITSURVEY_PATH . 'admin/class-exitsurvey-admin.php';
		require_once EXITSURVEY_PATH . 'public/class-exitsurvey-public.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-email-marketing.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-targeting-rules.php';
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-zapier-webhook.php';
	}

	/**
	 * Boot modules.
	 */
	private function boot() {
		ExitSurvey_Install::init();
		ExitSurvey_Ajax::init();
		ExitSurvey_Public::init();

		if ( is_admin() ) {
			ExitSurvey_Admin::init();
		}

		ExitSurvey_Email_Marketing::init();
		ExitSurvey_Targeting_Rules::init();
		ExitSurvey_Zapier_Webhook::init();
	}

	/**
	 * Activation hook.
	 */
	public function activate() {
		require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-install.php';
		ExitSurvey_Install::activate();
	}

	/**
	 * Deactivation hook.
	 */
	public function deactivate() {
		// Cleanup transients on deactivation
		delete_transient( 'exitsurvey_cart_data' );
	}

}

/**
 * Returns the main instance of ExitSurvey.
 *
 * @return ExitSurvey
 */
function exitsurvey() {
	return ExitSurvey::instance();
}

// Initialize the plugin.
exitsurvey();


