<?php

namespace AmeliaTutor\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Activator {

    public static function activate() {
        self::create_tables();
    }

    private static function create_tables() {
        global $wpdb;

        $table = $wpdb->prefix . 'ameliatutor_bookings';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            booking_id BIGINT UNSIGNED NOT NULL,
            customer_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            lesson_id BIGINT UNSIGNED NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            zoom_join_url VARCHAR(500) DEFAULT '',
            zoom_host_url VARCHAR(500) DEFAULT '',
            booking_status VARCHAR(50) DEFAULT 'pending',
            is_recurring TINYINT(1) DEFAULT 0,
            recurring_count INT DEFAULT 1,
            session_number INT DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY appointment_id (appointment_id),
            KEY booking_status (booking_status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}