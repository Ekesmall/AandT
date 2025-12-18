<?php

namespace AmeliaTutor\Amelia;

use Exception;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EnrollmentGuard {

    public static function init() {
        add_filter(
            'amelia_before_booking_added_filter',
            [ __CLASS__, 'verify_enrollment' ],
            10,
            2
        );
    }

    /**
     * Block booking if user is not enrolled in mapped course
     * AND verify session count matches lesson count for recurring bookings
     */
    public static function verify_enrollment( $booking, $service ) {

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            throw new Exception( __( 'Please log in to book this session.', 'amelia-tutor-integration' ) );
        }

        $service_id = intval( $service['id'] );
        $mappings   = get_option( 'ameliatutor_service_mappings', [] );

        // No mapping → allow booking (no restrictions)
        if ( empty( $mappings[ $service_id ]['course_id'] ) ) {
            return $booking;
        }

        $course_id = intval( $mappings[ $service_id ]['course_id'] );

        // Check 1: Enrollment verification (if enabled)
        if ( get_option( 'ameliatutor_require_enrollment', 'yes' ) === 'yes' ) {
            if ( ! tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
                $course_title = get_the_title( $course_id );
                throw new Exception(
                    sprintf(
                        __( 'You must enroll in "%s" before booking this session.', 'amelia-tutor-integration' ),
                        $course_title
                    )
                );
            }
        }

        // Check 2: Session count validation for recurring bookings
        if ( get_option( 'ameliatutor_enforce_session_count', 'yes' ) === 'yes' ) {
            
            // Check if this is a recurring booking
            $is_recurring = ! empty( $booking['recurring'] );
            
            if ( $is_recurring ) {
                
                // Get number of sessions being booked
                $session_count = count( $booking['recurring'] );
                
                // Get number of lessons in the course
                $lesson_count = self::count_course_lessons( $course_id );
                
                if ( $lesson_count === 0 ) {
                    throw new Exception(
                        sprintf(
                            __( 'The course "%s" has no lessons. Please contact support.', 'amelia-tutor-integration' ),
                            get_the_title( $course_id )
                        )
                    );
                }
                
                // Enforce exact match
                if ( $session_count !== $lesson_count ) {
                    
                    $course_title = get_the_title( $course_id );
                    
                    throw new Exception(
                        sprintf(
                            __( 'Session count mismatch! The course "%s" has %d lessons, but you selected %d sessions. Please select exactly %d sessions to match the course structure.', 'amelia-tutor-integration' ),
                            $course_title,
                            $lesson_count,
                            $session_count,
                            $lesson_count
                        )
                    );
                }
            }
        }

        return $booking;
    }

    /**
     * Count total lessons in a course
     */
    protected static function count_course_lessons( $course_id ) {
        
        $lesson_count = 0;

        // Get topics for the course
        $topics = tutor_utils()->get_topics( $course_id );

        if ( $topics && ! empty( $topics->posts ) ) {
            foreach ( $topics->posts as $topic ) {
                // Get lessons for each topic
                $topic_contents = tutor_utils()->get_course_contents_by_topic( $topic->ID );
                
                if ( $topic_contents && ! empty( $topic_contents->posts ) ) {
                    foreach ( $topic_contents->posts as $content ) {
                        if ( $content->post_type === 'lesson' ) {
                            $lesson_count++;
                        }
                    }
                }
            }
        }

        // Fallback: Get lessons directly associated with course
        if ( $lesson_count === 0 ) {
            $direct_lessons = get_posts( [
                'post_type'      => 'lesson',
                'posts_per_page' => -1,
                'meta_key'       => '_tutor_course_id_for_lesson',
                'meta_value'     => $course_id,
                'fields'         => 'ids',
            ] );

            $lesson_count = count( $direct_lessons );
        }

        return $lesson_count;
    }
}