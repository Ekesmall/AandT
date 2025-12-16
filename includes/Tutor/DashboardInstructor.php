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

        if ( ! tutor_utils()->is_instructor() ) {
            return;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'ameliatutor_bookings';

        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE booking_status IN ('approved','completed')
                 ORDER BY appointment_id DESC",
            ),
            ARRAY_A
        );

        if ( empty( $sessions ) ) {
            return;
        }

        echo '<div class="ameliatutor-instructor-sessions">';
        echo '<h3>' . esc_html__( 'Upcoming Live Sessions', 'amelia-tutorlms' ) . '</h3>';

        foreach ( $sessions as $session ) {

            echo '<div class="ameliatutor-session">';
            echo '<strong>' . esc_html( get_the_title( $session['course_id'] ) ) . '</strong><br>';

            if ( ! empty( $session['zoom_host_url'] ) ) {
                echo '<a class="button button-primary" target="_blank" href="' .
                     esc_url( $session['zoom_host_url'] ) . '">';
                esc_html_e( 'Start Session', 'amelia-tutorlms' );
                echo '</a>';
            } else {
                esc_html_e( 'Host link not yet available.', 'amelia-tutorlms' );
            }

            echo '</div>';
        }

        echo '</div>';
    }
}
