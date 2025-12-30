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
        $charset = $wpdb->get_charset_collate();

        // Existing bookings tracking table
        $table_bookings = $wpdb->prefix . 'ameliatutor_bookings';
        
        $sql_bookings = "CREATE TABLE {$table_bookings} (
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
            KEY booking_status (booking_status),
            KEY service_user_week (service_id, user_id, created_at)
        ) {$charset};";

        // New table for booking notices (mismatch tracking)
        $table_notices = $wpdb->prefix . 'ameliatutor_booking_notices';
        
        $sql_notices = "CREATE TABLE {$table_notices} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            appointment_id BIGINT UNSIGNED NOT NULL,
            notice_type VARCHAR(50) NOT NULL,
            notice_data TEXT,
            resolved TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY appointment_id (appointment_id),
            KEY notice_type (notice_type),
            KEY resolved (resolved)
        ) {$charset};";

        // New table for customer notes
        $table_notes = $wpdb->prefix . 'ameliatutor_customer_notes';
        
        $sql_notes = "CREATE TABLE {$table_notes} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT UNSIGNED NOT NULL,
            note TEXT NOT NULL,
            note_type VARCHAR(50) DEFAULT 'general',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY booking_id (booking_id),
            KEY note_type (note_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        
        dbDelta( $sql_bookings );
        dbDelta( $sql_notices );
        dbDelta( $sql_notes );
    }
}