<?php

namespace AmeliaTutor\Amelia;

use AmeliaTutor\Helpers\LessonCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validates recurring session count AFTER booking is created
 * 
 * This does NOT block the booking during submission
 * Instead, it validates and sends notifications if mismatch occurs
 */
class RecurringSessionValidator {

    public static function init() {
        
        // Hook AFTER booking is successfully added
        add_action(
            'AmeliaBookingAddedBeforeNotify',
            [ __CLASS__, 'validate_session_count' ],
            5, // Priority 5 to run before BookingTracker
            1
        );
    }

    /**
     * Validate recurring session count matches course lesson count
     * Runs AFTER booking is created, not during validation
     * 
     * @param array $appointment Appointment data from Amelia
     */
    public static function validate_session_count( $appointment ) {

        // Only validate if enforcement is enabled
        if ( get_option( 'ameliatutor_enforce_session_count', 'yes' ) !== 'yes' ) {
            return;
        }

        $service_id = intval( $appointment['serviceId'] );
        $mappings = get_option( 'ameliatutor_service_mappings', [] );

        // Service not mapped - skip validation
        if ( empty( $mappings[ $service_id ]['course_id'] ) ) {
            return;
        }

        $course_id = intval( $mappings[ $service_id ]['course_id'] );

        // Check if this is a recurring booking
        $is_recurring = ! empty( $appointment['recurring'] );
        
        if ( ! $is_recurring ) {
            return; // Only validate recurring bookings
        }

        // Get number of sessions booked
        $session_count = count( $appointment['recurring'] );
        
        // Get number of lessons in course
        $lesson_count = LessonCounter::count_course_lessons( $course_id );

        if ( $lesson_count === 0 ) {
            // Course has no lessons - log warning
            error_log( sprintf(
                'AmeliaTutor: Booking %d created for service %d mapped to course %d, but course has no lessons.',
                $appointment['id'],
                $service_id,
                $course_id
            ));
            return;
        }

        // Check for mismatch
        if ( $session_count !== $lesson_count ) {
            
            // Log the mismatch
            error_log( sprintf(
                'AmeliaTutor: Session count mismatch! Booking %d has %d sessions but course %d has %d lessons.',
                $appointment['id'],
                $session_count,
                $course_id,
                $lesson_count
            ));

            // Store mismatch info in appointment meta for admin reference
            self::store_mismatch_notice(
                $appointment['id'],
                $service_id,
                $course_id,
                $session_count,
                $lesson_count
            );

            // Send email notification to admin
            self::notify_admin_of_mismatch(
                $appointment,
                $service_id,
                $course_id,
                $session_count,
                $lesson_count
            );

            // Add customer note about the mismatch
            self::add_customer_note(
                $appointment,
                $session_count,
                $lesson_count
            );
        }
    }

    /**
     * Store mismatch notice for admin review
     */
    protected static function store_mismatch_notice( $appointment_id, $service_id, $course_id, $session_count, $lesson_count ) {
        
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ameliatutor_booking_notices',
            [
                'appointment_id' => $appointment_id,
                'notice_type'    => 'session_mismatch',
                'notice_data'    => wp_json_encode( [
                    'service_id'    => $service_id,
                    'course_id'     => $course_id,
                    'session_count' => $session_count,
                    'lesson_count'  => $lesson_count,
                ] ),
                'created_at'     => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Notify admin of session count mismatch
     */
    protected static function notify_admin_of_mismatch( $appointment, $service_id, $course_id, $session_count, $lesson_count ) {
        
        $admin_email = get_option( 'admin_email' );
        $course_title = get_the_title( $course_id );
        
        $customer_id = $appointment['bookings'][0]['customerId'] ?? 0;
        $customer_email = self::get_customer_email( $customer_id );

        $subject = sprintf(
            __( '[AmeliaTutor] Session Count Mismatch - Booking #%d', 'amelia-tutor-integration' ),
            $appointment['id']
        );

        $message = sprintf(
            __( "A booking was created with mismatched session count:\n\nBooking ID: %d\nCustomer: %s\nCourse: %s\nSessions Booked: %d\nLessons in Course: %d\n\nAction Required:\nPlease contact the customer to adjust their booking.\n\nView Booking: %s", 'amelia-tutor-integration' ),
            $appointment['id'],
            $customer_email,
            $course_title,
            $session_count,
            $lesson_count,
            admin_url( 'admin.php?page=wpamelia-appointments' )
        );

        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Add note to customer booking about mismatch
     */
    protected static function add_customer_note( $appointment, $session_count, $lesson_count ) {
        
        // This would typically add a note to Amelia's internal notes system
        // The implementation depends on Amelia's available hooks
        
        global $wpdb;

        $booking_id = $appointment['bookings'][0]['id'] ?? 0;
        
        if ( ! $booking_id ) {
            return;
        }

        $note = sprintf(
            __( 'Note: You booked %d sessions, but the course has %d lessons. Please contact support to adjust your booking for optimal learning experience.', 'amelia-tutor-integration' ),
            $session_count,
            $lesson_count
        );

        // Store in custom table for now
        $wpdb->insert(
            $wpdb->prefix . 'ameliatutor_customer_notes',
            [
                'booking_id'  => $booking_id,
                'note'        => $note,
                'note_type'   => 'mismatch_warning',
                'created_at'  => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );
    }

    /**
     * Get customer email from Amelia database
     */
    protected static function get_customer_email( $customer_id ) {
        global $wpdb;

        $email = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}amelia_customers WHERE id = %d",
                $customer_id
            )
        );

        return $email ?: __( 'Unknown', 'amelia-tutor-integration' );
    }
}