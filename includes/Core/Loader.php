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

    // Shortcodes
    \AmeliaTutor\Shortcodes\BookingShortcode::init();

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

    // Get services and courses data
    $mappings = get_option( 'ameliatutor_service_mappings', [] );
    $services = self::get_amelia_services();
    $courses  = self::get_tutor_courses();

    // Pass data to JavaScript
    wp_localize_script(
        'ameliatutor-admin',
        'AmeliaTutor',
        [
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'nonce'            => wp_create_nonce( 'ameliatutor_admin_nonce' ),
            'services'         => $services,
            'courses'          => $courses,
            'existing_mappings'=> $mappings,
            'strings'          => [
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
 * Get all Amelia services
 */
protected static function get_amelia_services() {
    global $wpdb;

    $services = $wpdb->get_results(
        "SELECT id, name, status 
         FROM {$wpdb->prefix}amelia_services 
         WHERE status = 'visible'
         ORDER BY name ASC",
        ARRAY_A
    );

    if ( ! $services ) {
        return [];
    }

    return array_map( function( $service ) {
        return [
            'id'   => intval( $service['id'] ),
            'name' => $service['name'],
        ];
    }, $services );
}

/**
 * Get all TutorLMS courses
 */
protected static function get_tutor_courses() {
    
    $courses = get_posts( [
        'post_type'      => 'courses',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    if ( ! $courses ) {
        return [];
    }

    return array_map( function( $course ) {
        return [
            'id'    => $course->ID,
            'title' => $course->post_title,
        ];
    }, $courses );
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