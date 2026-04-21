<?php
/**
 * Uninstall Scheduled Content Dashboard
 *
 * @package Scheduled_Content_Dashboard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function scd_uninstall_cleanup() {
    global $wpdb;

    $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_scd_mine_only' ) );

    delete_transient( 'scd_last_auto_fix' );
}

if ( is_multisite() ) {
    $sites = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $sites as $site_id ) {
        switch_to_blog( $site_id );
        scd_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    scd_uninstall_cleanup();
}
