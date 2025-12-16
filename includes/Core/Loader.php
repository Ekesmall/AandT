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

    if ( $hook !== 'toplevel_page_ameliatutor-settings' ) {
        return;
    }

    wp_enqueue_style(
        'ameliatutor-admin',
        AMELIATUTOR_URL . 'assets/admin.css',
        [],
        AMELIATUTOR_VERSION
    );

    wp_enqueue_script(
        'ameliatutor-admin',
        AMELIATUTOR_URL . 'assets/admin.js',
        [ 'jquery' ],
        AMELIATUTOR_VERSION,
        true
    );

    wp_localize_script(
        'ameliatutor-admin',
        'AmeliaTutor',
        [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ameliatutor_nonce' ),
        ]
    );
}
