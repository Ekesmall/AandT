<?php

namespace AmeliaTutor\Core;

use AmeliaTutor\Admin;
use AmeliaTutor\Amelia;
use AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Loader {

    public static function init() {

        // Admin
        if ( is_admin() ) {
            Admin\Menu::init();
            Admin\Settings::init();
            Admin\Ajax::init();

            add_action( 'admin_enqueue_scripts', [ __CLASS__, 'load_admin_assets' ] );
        }

        // Amelia
        Amelia\EnrollmentGuard::init();
        Amelia\BookingTracker::init();

        // Tutor
        Tutor\DashboardStudent::init();
        Tutor\DashboardInstructor::init();

        // Frontend assets
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'load_frontend_assets' ] );
    }

    /**
     * Admin CSS & JS
     */
    public static function load_admin_assets( $hook ) {

        // Only load on our settings page
        if ( $hook !== 'toplevel_page_ameliatutor-settings' ) {
            return;
        }

        // Enqueue admin CSS
        wp_enqueue_style(
            'ameliatutor-admin',
            AMELIATUTOR_URL . 'assets/admin.css',
            [],
            AMELIATUTOR_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'ameliatutor-admin',
            AMELIATUTOR_URL . 'assets/admin.js',
            [ 'jquery' ],
            AMELIATUTOR_VERSION,
            true
        );

        // Pass data to JavaScript
        wp_localize_script(
            'ameliatutor-admin',
            'AmeliaTutor',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ameliatutor_admin_nonce' ),
                'strings' => [
                    'confirmDelete' => __( 'Are you sure you want to remove this mapping?', 'amelia-tutor-integration' ),
                    'savingText'    => __( 'Saving...', 'amelia-tutor-integration' ),
                    'savedText'     => __( '✓ Saved!', 'amelia-tutor-integration' ),
                    'errorText'     => __( 'Error saving mappings', 'amelia-tutor-integration' ),
                    'loadingText'   => __( 'Loading...', 'amelia-tutor-integration' ),
                ],
            ]
        );
    }

    /**
     * Frontend CSS & JS
     */
    public static function load_frontend_assets() {

        // Check if we're on a TutorLMS page
        if ( ! function_exists( 'tutor' ) ) {
            return;
        }

        wp_enqueue_style(
            'ameliatutor-frontend',
            AMELIATUTOR_URL . 'assets/frontend.css',
            [],
            AMELIATUTOR_VERSION
        );
    }
}