<?php

namespace AmeliaTutor\Tutor;

use AmeliaTutor\Helpers\LessonCounter;

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

        if ( empty( $booking['user_id'] ) || empty( $booking['course_id'] ) ) {
            return;
        }

        // Only complete when booking is approved or completed
        if ( ! in_array( $booking['booking_status'], [ 'approved', 'completed' ], true ) ) {
            return;
        }

        // Determine which lesson to complete
        $lesson_id = self::get_lesson_for_session( $booking );

        if ( ! $lesson_id ) {
            return;
        }

        // Avoid duplicate completion
        if ( tutor_utils()->is_completed_lesson(
            intval( $lesson_id ),
            intval( $booking['user_id'] )
        ) ) {
            return;
        }

        tutor_utils()->mark_lesson_complete(
            intval( $lesson_id ),
            intval( $booking['user_id'] )
        );
    }

    /**
     * Get the correct lesson for this session
     */
    protected static function get_lesson_for_session( array $booking ) {

        $course_id = intval( $booking['course_id'] );

        // Recurring appointment → sequential lesson mapping
        if ( ! empty( $booking['is_recurring'] ) && intval( $booking['is_recurring'] ) === 1 ) {

            $session_number = max( 1, intval( $booking['session_number'] ) );

            // 🔥 Use centralized lesson counter logic
            $lessons = LessonCounter::get_course_lessons_ordered( $course_id );

            if ( empty( $lessons ) ) {
                return 0;
            }

            // Session 1 → lesson[0], Session 2 → lesson[1]
            $lesson_index = $session_number - 1;

            if ( isset( $lessons[ $lesson_index ] ) ) {
                return intval( $lessons[ $lesson_index ]->ID );
            }

            // Safety fallback → last lesson
            return intval( end( $lessons )->ID );
        }

        // Single appointment → mapped lesson
        return ! empty( $booking['lesson_id'] )
            ? intval( $booking['lesson_id'] )
            : 0;
    }

    /**
     * Count approved/completed sessions for recurring appointments
     * (kept for future use / reporting)
     */
    protected static function count_completed_sessions( $appointment_id ) {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->prefix}ameliatutor_bookings
                 WHERE appointment_id = %d
                 AND booking_status IN ('approved','completed')",
                $appointment_id
            )
        );
    }
}
