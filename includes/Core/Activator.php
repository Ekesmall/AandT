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
            user_id BIGINT UNSIGNED NOT NULL,
            course_id BIGINT UNSIGNED NOT NULL,
            lesson_id BIGINT UNSIGNED NULL,
            appointment_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
