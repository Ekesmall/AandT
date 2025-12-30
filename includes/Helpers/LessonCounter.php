<?php
/**
 * SURGICAL FIX FOR ISSUE 2 - LESSON COUNT SHOWING 0
 * 
 * CREATE NEW FILE: includes/Helpers/LessonCounter.php
 * 
 * This centralizes lesson counting logic that was duplicated
 * and broken in EnrollmentGuard.php
 */

namespace AmeliaTutor\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class LessonCounter {

    /**
     * Count published lessons in a TutorLMS course
     * 
     * This is the DEFINITIVE lesson counting method.
     * Used by both admin settings and frontend validation.
     * 
     * @param int $course_id TutorLMS course ID
     * @return int Number of published lessons
     */
    public static function count_course_lessons( $course_id ) {
        
        $lesson_count = 0;

        // Method 1: Get lessons via topics (most common structure)
        $topics = tutor_utils()->get_topics( $course_id );

        if ( $topics && ! empty( $topics->posts ) ) {
            foreach ( $topics->posts as $topic ) {
                
                // Get all content items in this topic
                $topic_contents = tutor_utils()->get_course_contents_by_topic( $topic->ID );
                
                if ( $topic_contents && ! empty( $topic_contents->posts ) ) {
                    foreach ( $topic_contents->posts as $content ) {
                        
                        // Count only published lessons
                        if ( $content->post_type === 'lesson' && 
                             $content->post_status === 'publish' ) {
                            $lesson_count++;
                        }
                    }
                }
            }
        }

        // Method 2: Fallback - Get lessons directly associated with course
        // This handles courses without topics
        if ( $lesson_count === 0 ) {
            
            $direct_lessons = get_posts( [
                'post_type'      => 'lesson',
                'post_status'    => 'publish', // Only published
                'posts_per_page' => -1,
                'meta_key'       => '_tutor_course_id_for_lesson',
                'meta_value'     => $course_id,
                'fields'         => 'ids',
            ] );

            $lesson_count = count( $direct_lessons );
        }

        return intval( $lesson_count );
    }

    /**
     * Get all lessons for a course (ordered)
     * Used by LessonCompletion for sequential mapping
     * 
     * @param int $course_id TutorLMS course ID
     * @return array Array of WP_Post objects
     */
    public static function get_course_lessons_ordered( $course_id ) {
        
        static $cache = [];

        if ( isset( $cache[ $course_id ] ) ) {
            return $cache[ $course_id ];
        }

        $lessons = [];

        // Get topics and their lessons in order
        $topics = tutor_utils()->get_topics( $course_id );

        if ( $topics && ! empty( $topics->posts ) ) {
            foreach ( $topics->posts as $topic ) {
                
                $topic_contents = tutor_utils()->get_course_contents_by_topic( $topic->ID );
                
                if ( $topic_contents && ! empty( $topic_contents->posts ) ) {
                    foreach ( $topic_contents->posts as $content ) {
                        
                        if ( $content->post_type === 'lesson' && 
                             $content->post_status === 'publish' ) {
                            $lessons[] = $content;
                        }
                    }
                }
            }
        }

        // Fallback: Direct lessons
        if ( empty( $lessons ) ) {
            $lessons = get_posts( [
                'post_type'      => 'lesson',
                'post_status'    => 'publish',
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
}