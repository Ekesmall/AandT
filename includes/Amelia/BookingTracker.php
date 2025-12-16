<?php

namespace AmeliaTutor\Amelia;

use AmeliaTutor\Helpers\UserResolver;
use AmeliaTutor\Helpers\ZoomExtractor;
use AmeliaTutor\Tutor\LessonCompletion;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingTracker {

    public static function init() {

        add_action(
            'AmeliaBookingAddedBeforeNotify',
            [ __CLASS__, 'store_booking' ],
            10,
            1
        );

        add_action(
            'AmeliaAppointmentStatusUpdated',
            [ __CLASS__, 'handle_status_change' ],
            10,
            3
        );

        add_action(
            'AmeliaBookingCanceled',
            [ __CLASS__, 'handle_cancellation' ],
            10,
            1
        );
    }

    /**
     * Store booking data for TutorLMS tracking
     */
    public static function store_booking( $appointment ) {

        global $wpdb;

        $service_id = intval( $appointment['serviceId'] );
        $mappings   = get_option( 'ameliatutor_service_mappings', [] );

        if ( empty( $mappings[ $service_id ] ) ) {
            return;
        }

        $course_id = intval( $mappings[ $service_id ]['course_id'] );
        $lesson_id = intval( $mappings[ $service_id ]['lesson_id'] );

        $customer_id = $appointment['bookings'][0]['customerId'];
        $user_id     = UserResolver::by_customer_id( $customer_id );

        if ( ! $user_id ) {
            return;
        }

        $zoom = ZoomExtractor::from_appointment( $appointment );

        $is_recurring     = ! empty( $appointment['recurring'] );
        $recurring_count  = $is_recurring ? count( $appointment['recurring'] ) : 1;

        $wpdb->insert(
            $wpdb->prefix . 'ameliatutor_bookings',
            [
                'appointment_id' => $appointment['id'],
                'booking_id'     => $appointment['bookings'][0]['id'],
                'customer_id'    => $customer_id,
                'user_id'        => $user_id,
                'course_id'      => $course_id,
                'lesson_id'      => $lesson_id,
                'service_id'     => $service_id,
                'zoom_join_url'  => $zoom['join'],
                'zoom_host_url'  => $zoom['host'],
                'booking_status' => $appointment['status'],
                'is_recurring'   => $is_recurring ? 1 : 0,
                'recurring_count'=> $recurring_count,
            ]
        );
    }

    /**
     * Handle appointment status updates
     */
    public static function handle_status_change( $appointment_id, $new_status, $old_status ) {

        if ( ! in_array( $new_status, [ 'approved', 'completed' ], true ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ameliatutor_bookings';

        $booking = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE appointment_id = %d",
                $appointment_id
            ),
            ARRAY_A
        );

        if ( ! $booking ) {
            return;
        }

        $wpdb->update(
            $table,
            [ 'booking_status' => $new_status ],
            [ 'appointment_id' => $appointment_id ]
        );

        LessonCompletion::maybe_complete( $booking );
    }

    /**
     * Handle booking cancellation
     */
    public static function handle_cancellation( $appointment ) {

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ameliatutor_bookings',
            [ 'booking_status' => 'cancelled' ],
            [ 'appointment_id' => $appointment['id'] ]
        );
    }
}
