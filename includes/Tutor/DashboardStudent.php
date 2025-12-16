<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DashboardStudent {

    public static function init() {
        add_filter(
            'tutor_course/single/after/contents',
            [ __CLASS__, 'render_student_sessions' ]
        );
    }

    /**
     * Render student Amelia sessions
     */
    public static function render_student_sessions() {

        if ( ! is_user_logged_in() ) {
            return;
        }

        global $wpdb;

        $user_id = get_current_user_id();
        $table   = $wpdb->prefix . 'ameliatutor_bookings';

        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE user_id = %d
                 AND booking_status IN ('approved','completed')",
                $user_id
            ),
            ARRAY_A
        );

        if ( empty( $sessions ) ) {
            return;
        }

        echo '<div class="ameliatutor-sessions">';
        echo '<h3>' . esc_html__( 'Your Live Sessions', 'amelia-tutorlms' ) . '</h3>';

        foreach ( $sessions as $session ) {

            echo '<div class="ameliatutor-session">';
            echo '<strong>' . esc_html( get_the_title( $session['course_id'] ) ) . '</strong><br>';

            if ( ! empty( $session['zoom_join_url'] ) ) {
                echo '<a class="button" target="_blank" href="' .
                     esc_url( $session['zoom_join_url'] ) . '">';
                esc_html_e( 'Join Live Session', 'amelia-tutorlms' );
                echo '</a>';
            } else {
                esc_html_e( 'Zoom link will be available soon.', 'amelia-tutorlms' );
            }

            echo '</div>';
        }

        echo '</div>';
    }
}
