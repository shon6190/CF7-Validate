<?php
/**
 * Plugin Name:       CF7 Validate Pro
 * Plugin URI:        https://github.com/shon6190/CF7-Validate-Pro
 * Description:       Comprehensive validation addon for Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Shon
 * Text Domain:       cf7-validate-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFV_VERSION', '1.0.0' );
define( 'CFV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check that CF7 is active. If not, deactivate this plugin and show a notice.
 */
function cfv_check_cf7_dependency(): void {
    if ( ! function_exists( 'wpcf7' ) ) {
        deactivate_plugins( CFV_PLUGIN_BASENAME );
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error is-dismissible"><p>'
                . esc_html__( 'CF7 Validate Pro requires Contact Form 7 to be installed and active.', 'cf7-validate-pro' )
                . '</p></div>';
        } );
        return;
    }

    // CF7 is active — load the plugin.
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-config.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-field-decorator.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-validator.php';
    require_once CFV_PLUGIN_DIR . 'includes/class-cfv-hooks.php';

    CFV_Hooks::init();
}
add_action( 'plugins_loaded', 'cfv_check_cf7_dependency' );
