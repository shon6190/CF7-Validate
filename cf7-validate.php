<?php
/**
 * Plugin Name:       CF7 Validate
 * Plugin URI:        https://github.com/shon6190/cf7-validate
 * Description:       Comprehensive validation addon for Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Requires Plugins:  contact-form-7
 * Author:            Shon
 * Author URI:        https://github.com/shon6190
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cf7-validate
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CFV_VERSION', '1.0.0' );
define( 'CFV_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CFV_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Show admin notice when CF7 is missing.
 */
function cfv_missing_cf7_notice(): void {
    echo '<div class="notice notice-error is-dismissible"><p>'
        . esc_html__( 'CF7 Validate Pro requires Contact Form 7 to be installed and active.', 'cf7-validate-pro' )
        . '</p></div>';
}

/**
 * Deactivate this plugin when CF7 is not active (runs on admin_init).
 */
function cfv_deactivate_self(): void {
    $network_wide = is_plugin_active_for_network( CFV_PLUGIN_BASENAME );
    deactivate_plugins( CFV_PLUGIN_BASENAME, true, $network_wide );
}

/**
 * Check CF7 dependency on plugins_loaded.
 * If missing, schedule deactivation and notice for admin context.
 * If present, load all plugin classes.
 */
function cfv_check_cf7_dependency(): void {
    if ( ! class_exists( 'WPCF7' ) ) {
        add_action( 'admin_init',    'cfv_deactivate_self' );
        add_action( 'admin_notices', 'cfv_missing_cf7_notice' );
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
