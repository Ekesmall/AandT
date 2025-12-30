<?php

namespace AmeliaTutor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Menu {

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
    }

    public static function register_menu() {
        
        add_menu_page(
            __( 'Amelia Tutor Integration', 'amelia-tutor-integration' ),
            __( 'Amelia Tutor', 'amelia-tutor-integration' ),
            'manage_options',
            'ameliatutor-settings',
            [ __CLASS__, 'render' ],
            'dashicons-admin-generic',
            55
        );
    }

    public static function render() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Access denied', 'amelia-tutor-integration' ) );
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__( 'Amelia – TutorLMS Integration', 'amelia-tutor-integration' ) . '</h1>';
        
        /**
         * Settings page content hook
         * Other classes can hook into this to render their content
         */
        do_action( 'ameliatutor_settings_page' );
        
        echo '</div>';
    }
}