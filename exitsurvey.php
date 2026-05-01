<?php
/**
 * Plugin Name: ExitSurvey - Smart Exit Survey for WooCommerce
 * Plugin URI:  https://github.com/exitsurvey-woo
 * Description: Tracks user browsing behavior and shows smart exit-intent surveys with cart data to recover abandoned carts.
 * Version:     1.0.0
 * Author:      ExitSurvey
 * Author URI:  https://exitsurvey.io
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: exitsurvey
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package ExitSurvey
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'EXITSURVEY_VERSION', '1.0.0' );
define( 'EXITSURVEY_FILE', __FILE__ );
define( 'EXITSURVEY_PATH', plugin_dir_path( __FILE__ ) );
define( 'EXITSURVEY_URL', plugin_dir_url( __FILE__ ) );
define( 'EXITSURVEY_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Initialize plugin.
 */
function exitsurvey_init() {
	// Load core files
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-install.php';
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-settings.php';
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-ajax.php';
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-survey.php';
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-responses.php';
	require_once EXITSURVEY_PATH . 'admin/class-exitsurvey-admin.php';
	require_once EXITSURVEY_PATH . 'public/class-exitsurvey-public.php';

	// Boot modules
	ExitSurvey_Install::init();
	ExitSurvey_Ajax::init();
	ExitSurvey_Public::init();

	if ( is_admin() ) {
		ExitSurvey_Admin::init();
	}
}
add_action( 'plugins_loaded', 'exitsurvey_init' );

/**
 * Activation hook.
 */
register_activation_hook( __FILE__, function() {
	require_once EXITSURVEY_PATH . 'includes/class-exitsurvey-install.php';
	ExitSurvey_Install::activate();
} );

/**
 * Deactivation hook.
 */
register_deactivation_hook( __FILE__, function() {
	// Cleanup transients on deactivation
	delete_transient( 'exitsurvey_cart_data' );
} );
