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

    delete_option( 'scheduled_content_dashboard_settings' );
    delete_transient( 'scd_last_auto_fix' );

    $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_scd_mine_only' ) );
    $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_scd_view' ) );
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
