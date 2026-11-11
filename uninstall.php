<?php
/**
 * Uninstall Scheduled Content Dashboard
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function scheduled_content_dashboard_uninstall_cleanup() {
    delete_option( 'scheduled_content_dashboard_settings' );
    delete_transient( 'scd_last_auto_fix' );
    delete_option( 'scd_auto_fix_log' );
    wp_clear_scheduled_hook( 'scd_send_digest' );
    wp_clear_scheduled_hook( 'scd_auto_fix_missed' );

    delete_metadata( 'user', 0, '_scd_mine_only', '', true );
    delete_metadata( 'user', 0, '_scd_view', '', true );
    delete_metadata( 'user', 0, '_scd_fix_seen', '', true );
}

function scheduled_content_dashboard_uninstall() {
    if ( is_multisite() ) {
        foreach ( get_sites( array( 'fields' => 'ids' ) ) as $site_id ) {
            switch_to_blog( $site_id );
            scheduled_content_dashboard_uninstall_cleanup();
            restore_current_blog();
        }
    } else {
        scheduled_content_dashboard_uninstall_cleanup();
    }
}

scheduled_content_dashboard_uninstall();
