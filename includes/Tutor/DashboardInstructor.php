<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardInstructor {

    public static function init() {
        add_action(
            'tutor_dashboard/instructor/after',
            [ __CLASS__, 'render_instructor_sessions' ]
        );
    }

    /**
     * Render instructor Amelia sessions
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

        echo '<div class="ameliatutor-instructor-sessions">';
        echo '<h3>' . esc_html__( 'Upcoming Live Sessions', 'amelia-tutor-integration' ) . '</h3>';

        foreach ( $sessions as $session ) {

            $student = get_userdata( $session['user_id'] );
            $student_name = $student ? $student->display_name : __( 'Unknown Student', 'amelia-tutor-integration' );

            $lesson_title = ! empty( $session['lesson_id'] )
                ? get_the_title( $session['lesson_id'] )
                : __( 'General Session', 'amelia-tutor-integration' );

            echo '<div class="ameliatutor-session">';
            echo '<strong>' . esc_html( $lesson_title ) . '</strong><br>';
            echo '<small>' . esc_html( get_the_title( $session['course_id'] ) ) . ' – ' . esc_html( $student_name ) . '</small><br>';

            if ( ! empty( $session['zoom_host_url'] ) ) {
                echo '<a class="button button-primary" target="_blank" rel="noopener noreferrer" href="' .
                     esc_url( $session['zoom_host_url'] ) . '">';
                esc_html_e( 'Start Session', 'amelia-tutor-integration' );
                echo '</a>';
            } else {
                echo '<span class="ameliatutor-status">';
                esc_html_e( 'Host link not yet available.', 'amelia-tutor-integration' );
                echo '</span>';
            }

            echo '</div>';
        }

        echo '</div>';
    }
}