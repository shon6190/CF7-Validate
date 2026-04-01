<?php
/**
 * Uninstall handler for CF7 Validate Pro.
 *
 * Removes all per-form validation configuration stored as post meta
 * when the plugin is deleted via the WordPress admin.
 *
 * @package CF7_Validate_Pro
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete all validation config stored on CF7 form posts.
$wpdb->delete(
    $wpdb->postmeta,
    [ 'meta_key' => '_cfv_validation_config' ],
    [ '%s' ]
);
