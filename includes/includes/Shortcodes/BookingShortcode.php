<?php

namespace AmeliaTutor\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class BookingShortcode {

    public static function init() {
        add_shortcode( 'ameliatutor_booking', [ __CLASS__, 'render' ] );
    }

    /**
     * Render the booking shortcode with enrollment verification
     * 
     * Usage: [ameliatutor_booking service=5]
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render( $atts ) {

        // Parse shortcode attributes
        $atts = shortcode_atts( [
            'service' => '',
            'employee' => '',
            'location' => '',
            'category' => '',
        ], $atts, 'ameliatutor_booking' );

        // Check if service ID is provided
        if ( empty( $atts['service'] ) ) {
            return self::render_error( __( 'Please specify a service ID. Example: [ameliatutor_booking service=5]', 'amelia-tutor-integration' ) );
        }

        // Get current course ID (we're in a course description)
        $course_id = get_the_ID();

        if ( ! $course_id || get_post_type( $course_id ) !== 'courses' ) {
            return self::render_error( __( 'This shortcode can only be used within a course.', 'amelia-tutor-integration' ) );
        }

        // Check 1: User must be logged in
        if ( ! is_user_logged_in() ) {
            return self::render_login_required( $course_id );
        }

        // Check 2: User must be enrolled (if setting is enabled)
        if ( get_option( 'ameliatutor_require_enrollment', 'yes' ) === 'yes' ) {
            
            $user_id = get_current_user_id();
            
            if ( ! tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
                return self::render_enrollment_required( $course_id );
            }
        }

        // Check 3: Verify service is mapped to this course
        $service_id = intval( $atts['service'] );
        $mappings = get_option( 'ameliatutor_service_mappings', [] );

        if ( ! empty( $mappings[ $service_id ] ) ) {
            $mapped_course_id = intval( $mappings[ $service_id ]['course_id'] );
            
            // If service is mapped to a different course, show warning
            if ( $mapped_course_id !== $course_id ) {
                $mapped_course_title = get_the_title( $mapped_course_id );
                return self::render_warning( 
                    sprintf(
                        __( 'Note: This service is mapped to "%s" course, not this course. Lesson completion will track in that course.', 'amelia-tutor-integration' ),
                        $mapped_course_title
                    )
                );
            }
        }

        // All checks passed - render Amelia booking form
        return self::render_booking_form( $atts );
    }

    /**
     * Render error message
     */
    protected static function render_error( $message ) {
        ob_start();
        ?>
        <div class="ameliatutor-shortcode-message ameliatutor-shortcode-error">
            <span class="dashicons dashicons-warning"></span>
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render warning message
     */
    protected static function render_warning( $message ) {
        ob_start();
        ?>
        <div class="ameliatutor-shortcode-message ameliatutor-shortcode-warning">
            <span class="dashicons dashicons-info"></span>
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
 * Render login required message
 */
protected static function render_login_required( $course_id ) {

    // Course URL
    $redirect_url = get_permalink( $course_id );

    // Dashboard login URL
    $dashboard_url = site_url( '/dashboard/' );

    // Add redirect_to parameter
    $login_url_with_redirect = add_query_arg(
        'redirect_to',
        urlencode( $redirect_url ),
        $dashboard_url
    );

    ob_start();
    ?>
    <div class="ameliatutor-shortcode-message ameliatutor-shortcode-login-required">
        <i class="fas fa-lock" aria-hidden="true"></i>
        <div class="ameliatutor-shortcode-content">
            <h4><?php esc_html_e( 'Login Required', 'amelia-tutor-integration' ); ?></h4>
            <p><?php esc_html_e( 'Please log in to book your sessions.', 'amelia-tutor-integration' ); ?></p>
            <a href="<?php echo esc_url( $login_url_with_redirect ); ?>" class="ameliatutor-button ameliatutor-button-primary">
                <?php esc_html_e( 'Log In', 'amelia-tutor-integration' ); ?>
            </a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}


    /**
     * Render enrollment required message
     */
    protected static function render_enrollment_required( $course_id ) {
        
        $course_title = get_the_title( $course_id );
        $enroll_url = get_permalink( $course_id );

        ob_start();
        ?>
        <div class="ameliatutor-shortcode-message ameliatutor-shortcode-enrollment-required">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <div class="ameliatutor-shortcode-content">
                <h4><?php esc_html_e( 'Enrollment Required', 'amelia-tutor-integration' ); ?></h4>
                <p>
                    <?php 
                    echo sprintf(
                        esc_html__( 'You must enroll in "%s" before booking sessions.', 'amelia-tutor-integration' ),
                        '<strong>' . esc_html( $course_title ) . '</strong>'
                    ); 
                    ?>
                </p>
                <a href="<?php echo esc_url( $enroll_url ); ?>" class="ameliatutor-button ameliatutor-button-primary">
                    <?php esc_html_e( 'Enroll Now', 'amelia-tutor-integration' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the Amelia booking form
     */
    protected static function render_booking_form( $atts ) {
        
        // Build Amelia shortcode
        $amelia_atts = [];
        
        if ( ! empty( $atts['service'] ) ) {
            $amelia_atts[] = 'service=' . intval( $atts['service'] );
        }
        
        if ( ! empty( $atts['employee'] ) ) {
            $amelia_atts[] = 'employee=' . intval( $atts['employee'] );
        }
        
        if ( ! empty( $atts['location'] ) ) {
            $amelia_atts[] = 'location=' . intval( $atts['location'] );
        }
        
        if ( ! empty( $atts['category'] ) ) {
            $amelia_atts[] = 'category=' . intval( $atts['category'] );
        }

        $shortcode = '[ameliabooking ' . implode( ' ', $amelia_atts ) . ']';

        ob_start();
        ?>
        <div class="ameliatutor-booking-wrapper">
            <?php echo do_shortcode( $shortcode ); ?>
        </div>
        <?php
        return ob_get_clean();
    }
}