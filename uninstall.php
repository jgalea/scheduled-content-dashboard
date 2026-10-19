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
    wp_clear_scheduled_hook( 'scd_send_digest' );

    delete_metadata( 'user', 0, '_scd_mine_only', '', true );
    delete_metadata( 'user', 0, '_scd_view', '', true );
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
