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
            return; // No lesson to complete
        }

        // Check if already completed to avoid duplicate completions
        if ( tutor_utils()->is_completed_lesson( intval( $lesson_id ), intval( $booking['user_id'] ) ) ) {
            return;
        }

        // Mark lesson as complete
        tutor_utils()->mark_lesson_complete(
            intval( $lesson_id ),
            intval( $booking['user_id'] )
        );
    }

    /**
     * Get the correct lesson for this session
     */
    protected static function get_lesson_for_session( array $booking ) {

        // If recurring appointment, use sequential lesson mapping
        if ( intval( $booking['is_recurring'] ) === 1 ) {
            
            $session_number = intval( $booking['session_number'] );
            $course_id = intval( $booking['course_id'] );

            // Get all lessons for the course in order
            $lessons = self::get_course_lessons_ordered( $course_id );

            if ( empty( $lessons ) ) {
                return 0;
            }

            // Session 1 → lessons[0], Session 2 → lessons[1], etc.
            $lesson_index = $session_number - 1;

            if ( isset( $lessons[ $lesson_index ] ) ) {
                return intval( $lessons[ $lesson_index ]->ID );
            }

            // If session number exceeds available lessons, complete the last lesson
            return intval( end( $lessons )->ID );

        } else {
            // Single appointment - use the mapped lesson_id
            return intval( $booking['lesson_id'] );
        }
    }

    /**
     * Get all lessons for a course ordered by menu_order
     */
    protected static function get_course_lessons_ordered( $course_id ) {

        static $cache = [];

        if ( isset( $cache[ $course_id ] ) ) {
            return $cache[ $course_id ];
        }

        $lessons = [];

        // Get topics for the course
        $topics = tutor_utils()->get_topics( $course_id );

        if ( $topics && ! empty( $topics->posts ) ) {
            foreach ( $topics->posts as $topic ) {
                // Get lessons for each topic
                $topic_contents = tutor_utils()->get_course_contents_by_topic( $topic->ID );
                
                if ( $topic_contents && ! empty( $topic_contents->posts ) ) {
                    foreach ( $topic_contents->posts as $content ) {
                        if ( $content->post_type === 'lesson' ) {
                            $lessons[] = $content;
                        }
                    }
                }
            }
        }

        // Fallback: Get lessons directly associated with course
        if ( empty( $lessons ) ) {
            $lessons = get_posts( [
                'post_type'      => 'lesson',
                'posts_per_page' => -1,
                'meta_key'       => '_tutor_course_id_for_lesson',
                'meta_value'     => $course_id,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
        }

        $cache[ $course_id ] = $lessons;

        return $lessons;
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