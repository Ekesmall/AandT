<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/**
 * Drop custom tables
 */
$table = $wpdb->prefix . 'ameliatutor_bookings';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

/**
 * Delete options
 */
$options = [
    'ameliatutor_service_mappings',
    'ameliatutor_auto_complete_lesson',
    'ameliatutor_require_enrollment',
    'ameliatutor_recurring_complete_all',
    'ameliatutor_show_dashboard_widgets',
];

foreach ( $options as $option ) {
    delete_option( $option );
}
