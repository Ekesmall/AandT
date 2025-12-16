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

    public static function get_lessons() {

        check_ajax_referer( 'ameliatutor_admin_nonce', 'nonce' );

        $course_id = intval( $_POST['course_id'] );

        $lessons = get_posts( [
            'post_type'      => 'lesson',
            'posts_per_page' => -1,
            'meta_key'       => '_tutor_course_id_for_lesson',
            'meta_value'     => $course_id,
        ] );

        $data = [];

        foreach ( $lessons as $lesson ) {
            $data[] = [
                'id'    => $lesson->ID,
                'title' => $lesson->post_title,
            ];
        }

        wp_send_json_success( $data );
    }

    public static function save_mappings() {

        check_ajax_referer( 'ameliatutor_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $mappings = isset( $_POST['mappings'] )
            ? wp_unslash( $_POST['mappings'] )
            : [];

        update_option( 'ameliatutor_service_mappings', $mappings );

        wp_send_json_success();
    }
}
