<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardStudent {

    public static function init() {
        
        // Add menu item to TutorLMS student dashboard
        add_filter( 'tutor_dashboard/nav_items', [ __CLASS__, 'add_dashboard_menu' ], 20 );
        
        // Keep existing session display on course pages
        add_action(
            'tutor_course/single/after/contents',
            [ __CLASS__, 'render_student_sessions' ]
        );
    }

    /**
     * Add "My Appointments" menu item to student dashboard
     * 
     * @param array $nav_items Existing navigation items
     * @return array Modified navigation items
     */
    public static function add_dashboard_menu( $nav_items ) {
        
        // Only show for students (not instructors)
        if ( tutor_utils()->is_instructor() ) {
            return $nav_items;
        }

        // Get Amelia customer panel URL
        $customer_panel_url = self::get_amelia_customer_panel_url();
        
        if ( ! $customer_panel_url ) {
            return $nav_items; // Amelia customer panel not configured
        }

        // Insert after "My Courses" if it exists
        $new_item = [
            'my-appointments' => [
                'title' => __( 'My Appointments', 'amelia-tutor-integration' ),
                'icon'  => 'tutor-icon-calendar',
                'url'   => $customer_panel_url,
            ],
        ];

        // Find position to insert
        $position = 2; // Default after first items
        $keys = array_keys( $nav_items );
        
        if ( in_array( 'my-courses', $keys, true ) ) {
            $position = array_search( 'my-courses', $keys, true ) + 1;
        }

        $nav_items = array_slice( $nav_items, 0, $position, true ) +
                     $new_item +
                     array_slice( $nav_items, $position, null, true );

        return $nav_items;
    }

    /**
     * Get Amelia customer panel URL
     * 
     * @return string|false Customer panel URL or false if not found
     */
    protected static function get_amelia_customer_panel_url() {
        
        // Try to find page with Amelia customer panel shortcode
        global $wpdb;
        
        $page = $wpdb->get_row(
            "SELECT ID, post_name 
             FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish'
             AND post_content LIKE '%[ameliacustomerpanel%'
             LIMIT 1"
        );

        if ( $page ) {
            return get_permalink( $page->ID );
        }

        // Fallback: check for common slug
        $common_slugs = [ 'my-bookings', 'appointments', 'my-appointments', 'customer-panel' ];
        
        foreach ( $common_slugs as $slug ) {
            $page = get_page_by_path( $slug );
            if ( $page && $page->post_status === 'publish' ) {
                return get_permalink( $page->ID );
            }
        }

        // Last resort: return generic Amelia URL
        // Admin can set this in plugin settings
        $custom_url = get_option( 'ameliatutor_customer_panel_url', '' );
        
        return $custom_url ?: false;
    }

    /**
     * Render student Amelia sessions on course page
     * (Existing functionality preserved)
     */
    public static function render_student_sessions() {

        // Check if widgets are enabled
        if ( get_option( 'ameliatutor_show_dashboard_widgets', 'yes' ) !== 'yes' ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            return;
        }

        global $wpdb;

        $user_id = get_current_user_id();
        $course_id = get_the_ID();
        $table   = $wpdb->prefix . 'ameliatutor_bookings';

        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                 AND course_id = %d
                 AND booking_status IN ('approved','completed')
                 ORDER BY created_at DESC",
                $user_id,
                $course_id
            ),
            ARRAY_A
        );

        if ( empty( $sessions ) ) {
            return;
        }

        echo '<div class="ameliatutor-sessions" style="margin-top: 30px;">';
        echo '<h3>' . esc_html__( 'Your Live Sessions', 'amelia-tutor-integration' ) . '</h3>';

        foreach ( $sessions as $session ) {

            $lesson_title = ! empty( $session['lesson_id'] )
                ? get_the_title( $session['lesson_id'] )
                : __( 'General Session', 'amelia-tutor-integration' );

            echo '<div class="ameliatutor-session" style="padding: 15px; margin: 10px 0; background: #f6f7f7; border-left: 3px solid #2271b1; border-radius: 4px;">';
            echo '<strong>' . esc_html( $lesson_title ) . '</strong><br>';
            
            if ( intval( $session['is_recurring'] ) === 1 ) {
                echo '<small>' . sprintf(
                    esc_html__( 'Session %d of %d', 'amelia-tutor-integration' ),
                    intval( $session['session_number'] ),
                    intval( $session['recurring_count'] )
                ) . '</small><br>';
            }

            if ( ! empty( $session['zoom_join_url'] ) ) {
                echo '<a class="button button-primary" style="margin-top: 10px;" target="_blank" rel="noopener noreferrer" href="' .
                     esc_url( $session['zoom_join_url'] ) . '">';
                esc_html_e( 'Join Live Session', 'amelia-tutor-integration' );
                echo '</a>';
            } else {
                echo '<span class="ameliatutor-status" style="color: #646970; font-size: 13px;">';
                esc_html_e( 'Zoom link will be available soon.', 'amelia-tutor-integration' );
                echo '</span>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}