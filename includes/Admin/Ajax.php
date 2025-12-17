<?php

namespace AmeliaTutor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ajax {

    public static function init() {
        add_action( 'wp_ajax_ameliatutor_get_lessons', [ __CLASS__, 'get_lessons' ] );
        add_action( 'wp_ajax_ameliatutor_save_mappings', [ __CLASS__, 'save_mappings' ] );
    }

    /**
     * Get lessons for a specific course (AJAX handler)
     */
    public static function get_lessons() {

        check_ajax_referer( 'ameliatutor_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $course_id = isset( $_POST['course_id'] ) ? intval( $_POST['course_id'] ) : 0;

        if ( ! $course_id ) {
            wp_send_json_error( 'Invalid course ID' );
        }

        // Get topics for the course
        $topics = tutor_utils()->get_topics( $course_id );
        $lessons_data = [];

        if ( $topics ) {
            foreach ( $topics->posts as $topic ) {
                // Get lessons for each topic
                $lessons = tutor_utils()->get_course_contents_by_topic( $topic->ID );
                
                if ( $lessons ) {
                    foreach ( $lessons->posts as $lesson ) {
                        if ( $lesson->post_type === 'lesson' ) {
                            $lessons_data[] = [
                                'id'    => $lesson->ID,
                                'title' => $topic->post_title . ' → ' . $lesson->post_title,
                            ];
                        }
                    }
                }
            }
        }

        // Fallback: Get lessons directly associated with course
        if ( empty( $lessons_data ) ) {
            $direct_lessons = get_posts( [
                'post_type'      => 'lesson',
                'posts_per_page' => -1,
                'meta_key'       => '_tutor_course_id_for_lesson',
                'meta_value'     => $course_id,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );

            if ( $direct_lessons ) {
                foreach ( $direct_lessons as $lesson ) {
                    $lessons_data[] = [
                        'id'    => $lesson->ID,
                        'title' => $lesson->post_title,
                    ];
                }
            }
        }

        wp_send_json_success( $lessons_data );
    }

    /**
     * Save service mappings (AJAX handler)
     */
    public static function save_mappings() {

        check_ajax_referer( 'ameliatutor_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $mappings = isset( $_POST['mappings'] ) ? $_POST['mappings'] : [];

        // Validate mappings format
        if ( ! is_array( $mappings ) ) {
            wp_send_json_error( 'Invalid mappings format' );
        }

        // Sanitize and validate each mapping
        $sanitized_mappings = [];

        foreach ( $mappings as $service_id => $mapping ) {
            
            $service_id = intval( $service_id );
            
            if ( ! $service_id || ! isset( $mapping['course_id'] ) ) {
                continue;
            }

            $course_id = intval( $mapping['course_id'] );
            $lesson_id = isset( $mapping['lesson_id'] ) ? intval( $mapping['lesson_id'] ) : 0;

            // Verify the course exists
            if ( ! get_post( $course_id ) || get_post_type( $course_id ) !== 'courses' ) {
                continue;
            }

            // Verify the lesson exists if provided
            if ( $lesson_id > 0 ) {
                $lesson = get_post( $lesson_id );
                if ( ! $lesson || $lesson->post_type !== 'lesson' ) {
                    continue; // Skip invalid lesson
                }
            }

            $sanitized_mappings[ $service_id ] = [
                'course_id' => $course_id,
                'lesson_id' => $lesson_id,
            ];
        }

        // Save to options
        $updated = update_option( 'ameliatutor_service_mappings', $sanitized_mappings );

        if ( $updated !== false ) {
            wp_send_json_success( [
                'message' => 'Mappings saved successfully',
                'count'   => count( $sanitized_mappings ),
            ] );
        } else {
            wp_send_json_error( 'Failed to save mappings' );
        }
    }
}