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
     */
    public static function verify_enrollment( $booking, $service ) {

        if ( get_option( 'ameliatutor_require_enrollment', 'yes' ) !== 'yes' ) {
            return $booking;
        }

        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            throw new Exception( __( 'Please log in to book this session.', 'amelia-tutorlms' ) );
        }

        $service_id = intval( $service['id'] );
        $mappings   = get_option( 'ameliatutor_service_mappings', [] );

        // No mapping → allow booking
        if ( empty( $mappings[ $service_id ]['course_id'] ) ) {
            return $booking;
        }

        $course_id = intval( $mappings[ $service_id ]['course_id'] );

        if ( ! tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
            $course_title = get_the_title( $course_id );
            throw new Exception(
                sprintf(
                    __( 'You must enroll in "%s" before booking this session.', 'amelia-tutorlms' ),
                    $course_title
                )
            );
        }

        return $booking;
    }
}
