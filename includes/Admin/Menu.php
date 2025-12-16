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
            'dashicons-welcome-learn-more',
            56
        );
    }

    public static function render() {

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Sorry, you are not allowed to access this page.' ) );
        }

        ?>
        <div class="wrap">
            <h1>Amelia – TutorLMS Integration</h1>
            <p>The admin page is now working correctly ✅</p>

            <?php do_action( 'ameliatutor_settings_page' ); ?>
        </div>
        <?php
    }
}
