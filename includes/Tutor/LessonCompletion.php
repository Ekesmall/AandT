<?php

namespace AmeliaTutor\Tutor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LessonCompletion {

    /**
     * Decide if a lesson should be marked complete
     */
    public static function maybe_complete( array $booking ) {

        // Check if auto-completion is enabled
        if ( get_option( 'ameliatutor_auto_complete_lesson', 'yes' ) !== 'yes' ) {
            return;
        }

        if ( empty( $booking['lesson_id'] ) || empty( $booking['user_id'] ) ) {
            return;
        }

        // Only complete when booking is approved or completed
        if ( ! in_array( $booking['booking_status'], [ 'approved', 'completed' ], true ) ) {
            return;
        }

        // Handle recurring sessions
        if ( intval( $booking['is_recurring'] ) === 1 ) {

            // Check if we should complete all recurring sessions first
            $complete_all = get_option( 'ameliatutor_recurring_complete_all', 'no' ) === 'yes';

            if ( $complete_all ) {
                $completed = self::count_completed_sessions( $booking['appointment_id'] );

                // Only mark complete when all sessions are done
                if ( $completed < intval( $booking['recurring_count'] ) ) {
                    return;
                }
            }
        }

        // Check if already completed to avoid duplicate completions
        if ( tutor_utils()->is_completed_lesson( intval( $booking['lesson_id'] ), intval( $booking['user_id'] ) ) ) {
            return;
        }

        // Mark lesson as complete
        tutor_utils()->mark_lesson_complete(
            intval( $booking['lesson_id'] ),
            intval( $booking['user_id'] )
        );
    }

    /**
     * Count approved/completed sessions for recurring appointments
     */
    protected static function count_completed_sessions( $appointment_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ameliatutor_bookings
                 WHERE appointment_id = %d
                 AND booking_status IN ('approved','completed')",
                $appointment_id
            )
        );
    }
}