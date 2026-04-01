<?php
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
