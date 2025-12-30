<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardInstructor {

    public static function init() {
        
        // Add menu item to TutorLMS instructor dashboard
        add_filter( 'tutor_dashboard/nav_items', [ __CLASS__, 'add_dashboard_menu' ], 20 );
        
        // Keep existing session display
        add_action(
            'tutor_dashboard/instructor/after',
            [ __CLASS__, 'render_instructor_sessions' ]
        );
    }

    /**
     * Add "Teaching Schedule" menu item to instructor dashboard
     * 
     * @param array $nav_items Existing navigation items
     * @return array Modified navigation items
     */
    public static function add_dashboard_menu( $nav_items ) {
        
        // Only show for instructors
        if ( ! tutor_utils()->is_instructor() ) {
            return $nav_items;
        }

        // Get current user as Amelia employee
        $employee_id = self::get_current_user_employee_id();
        
        if ( ! $employee_id ) {
            return $nav_items; // User not set up as Amelia employee
        }

        // Get Amelia employee panel URL
        $employee_panel_url = self::get_amelia_employee_panel_url();
        
        if ( ! $employee_panel_url ) {
            return $nav_items; // Amelia employee panel not configured
        }

        // Insert after "Courses" or "My Courses"
        $new_item = [
            'teaching-schedule' => [
                'title' => __( 'Teaching Schedule', 'amelia-tutor-integration' ),
                'icon'  => 'tutor-icon-clock',
                'url'   => $employee_panel_url,
            ],
        ];

        // Find position to insert
        $position = 3; // Default position
        $keys = array_keys( $nav_items );
        
        if ( in_array( 'courses', $keys, true ) ) {
            $position = array_search( 'courses', $keys, true ) + 1;
        } elseif ( in_array( 'my-courses', $keys, true ) ) {
            $position = array_search( 'my-courses', $keys, true ) + 1;
        }

        $nav_items = array_slice( $nav_items, 0, $position, true ) +
                     $new_item +
                     array_slice( $nav_items, $position, null, true );

        return $nav_items;
    }

    /**
     * Get current user's Amelia employee ID
     * 
     * @return int|false Employee ID or false if not found
     */
    protected static function get_current_user_employee_id() {
        
        global $wpdb;
        $user_id = get_current_user_id();

        // Try to find employee by WordPress user ID
        $employee_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}amelia_users 
                 WHERE externalId = %d 
                 AND type = 'provider'
                 LIMIT 1",
                $user_id
            )
        );

        if ( $employee_id ) {
            return intval( $employee_id );
        }

        // Try to find by email
        $user = wp_get_current_user();
        
        $employee_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}amelia_users 
                 WHERE email = %s 
                 AND type = 'provider'
                 LIMIT 1",
                $user->user_email
            )
        );

        return $employee_id ? intval( $employee_id ) : false;
    }

    /**
     * Get Amelia employee panel URL
     * 
     * @return string|false Employee panel URL or false if not found
     */
    protected static function get_amelia_employee_panel_url() {
        
        // Try to find page with Amelia employee panel shortcode
        global $wpdb;
        
        $page = $wpdb->get_row(
            "SELECT ID, post_name 
             FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish'
             AND post_content LIKE '%[ameliaemployeepanel%'
             LIMIT 1"
        );

        if ( $page ) {
            return get_permalink( $page->ID );
        }

        // Fallback: check for common slugs
        $common_slugs = [ 'employee-panel', 'instructor-schedule', 'teaching-schedule' ];
        
        foreach ( $common_slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page && $page->post_status === 'publish' ) {
                return get_permalink( $page->ID );
            }
        }

        // Last resort: return generic Amelia URL
        $custom_url = get_option( 'ameliatutor_employee_panel_url', '' );
        
        return $custom_url ?: false;
    }

    /**
     * Render instructor Amelia sessions
     * (Existing functionality preserved)
     */
    public static function render_instructor_sessions() {

        // Check if widgets are enabled
        if ( get_option( 'ameliatutor_show_dashboard_widgets', 'yes' ) !== 'yes' ) {
            return;
        }

        if ( ! tutor_utils()->is_instructor() ) {
            return;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'ameliatutor_bookings';

        // Get courses taught by this instructor
        $instructor_courses = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_tutor_instructor_id'
                 AND meta_value = %d",
                $user_id
            )
        );

        if ( empty( $instructor_courses ) ) {
            return;
        }

        $course_ids = implode( ',', array_map( 'intval', $instructor_courses ) );

        $sessions = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE course_id IN ({$course_ids})
             AND booking_status IN ('approved','completed')
             ORDER BY created_at DESC
             LIMIT 10",
            ARRAY_A
        );

        if ( empty( $sessions ) ) {
            return;
        }

        echo '<div class="ameliatutor-instructor-sessions" style="margin-top: 30px;">';
        echo '<h3>' . esc_html__( 'Upcoming Live Sessions', 'amelia-tutor-integration' ) . '</h3>';

        foreach ( $sessions as $session ) {

            $student = get_userdata( $session['user_id'] );
            $student_name = $student ? $student->display_name : __( 'Unknown Student', 'amelia-tutor-integration' );

            $lesson_title = ! empty( $session['lesson_id'] )
                ? get_the_title( $session['lesson_id'] )
                : __( 'General Session', 'amelia-tutor-integration' );

            echo '<div class="ameliatutor-session" style="padding: 15px; margin: 10px 0; background: #f6f7f7; border-left: 3px solid #00a32a; border-radius: 4px;">';
            echo '<strong>' . esc_html( $lesson_title ) . '</strong><br>';
            echo '<small>' . esc_html( get_the_title( $session['course_id'] ) ) . ' – ' . esc_html( $student_name ) . '</small><br>';

            if ( intval( $session['is_recurring'] ) === 1 ) {
                echo '<small>' . sprintf(
                    esc_html__( 'Session %d of %d', 'amelia-tutor-integration' ),
                    intval( $session['session_number'] ),
                    intval( $session['recurring_count'] )
                ) . '</small><br>';
            }

            if ( ! empty( $session['zoom_host_url'] ) ) {
                echo '<a class="button button-primary" style="margin-top: 10px;" target="_blank" rel="noopener noreferrer" href="' .
                     esc_url( $session['zoom_host_url'] ) . '">';
                esc_html_e( 'Start Session', 'amelia-tutor-integration' );
                echo '</a>';
            } else {
                echo '<span class="ameliatutor-status" style="color: #646970; font-size: 13px;">';
                esc_html_e( 'Host link not yet available.', 'amelia-tutor-integration' );
                echo '</span>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}