<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardStudent {

    public static function init() {
        add_action(
            'tutor_course/single/after/contents',
            [ __CLASS__, 'render_student_sessions' ]
        );
    }

    /**
     * Render student Amelia sessions
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

        echo '<div class="ameliatutor-sessions">';
        echo '<h3>' . esc_html__( 'Your Live Sessions', 'amelia-tutor-integration' ) . '</h3>';

        foreach ( $sessions as $session ) {

            $lesson_title = ! empty( $session['lesson_id'] )
                ? get_the_title( $session['lesson_id'] )
                : __( 'General Session', 'amelia-tutor-integration' );

            echo '<div class="ameliatutor-session">';
            echo '<strong>' . esc_html( $lesson_title ) . '</strong><br>';
            echo '<small>' . esc_html( get_the_title( $session['course_id'] ) ) . '</small><br>';

            if ( ! empty( $session['zoom_join_url'] ) ) {
                echo '<a class="button" target="_blank" rel="noopener noreferrer" href="' .
                     esc_url( $session['zoom_join_url'] ) . '">';
                esc_html_e( 'Join Live Session', 'amelia-tutor-integration' );
                echo '</a>';
            } else {
                echo '<span class="ameliatutor-status">';
                esc_html_e( 'Zoom link will be available soon.', 'amelia-tutor-integration' );
                echo '</span>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}