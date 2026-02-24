<?php
/**
 * Uninstall Scheduled Content Dashboard
 *
 * This file runs when the plugin is deleted from the WordPress admin.
 * It cleans up any options or data created by the plugin.
 *
 * @package Scheduled_Content_Dashboard
 * @since 1.0.0
 */

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/*
 * This plugin does not store any options or custom data in the database,
 * so there is nothing to clean up on uninstall.
 *
 * If future versions add options, they should be deleted here.
 * Example:
 * delete_option( 'scheduled_content_dashboard_settings' );
 *
 * For multisite, you would loop through all sites:
 * if ( is_multisite() ) {
 *     $sites = get_sites();
 *     foreach ( $sites as $site ) {
 *         switch_to_blog( $site->blog_id );
 *         delete_option( 'scheduled_content_dashboard_settings' );
 *         restore_current_blog();
 *     }
 * }
 */
