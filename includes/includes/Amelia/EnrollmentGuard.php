<?php

namespace AmeliaTutor\Amelia;

use AmeliaTutor\Helpers\LessonCounter;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CRITICAL FIX: This class NO LONGER blocks booking submission
 * 
 * All validation has been moved to shortcode rendering stage
 * This prevents booking rollback issues
 */
class EnrollmentGuard {

    public static function init() {
        // REMOVED: amelia_before_booking_added_filter hook
        // That hook was causing booking failures
        
        // Instead, we now wrap the default Amelia shortcode
        add_action( 'init', [ __CLASS__, 'wrap_amelia_shortcode' ], 999 );
        
        // NEW: Handle post-login redirect
        add_action( 'wp_login', [ __CLASS__, 'handle_post_login_redirect' ], 10, 2 );
        
        // NEW: Fallback redirect check on dashboard page
        add_action( 'template_redirect', [ __CLASS__, 'check_pending_redirect' ], 1 );
    }

    /**
     * Wrap the default [ameliabooking] shortcode to add our validation
     * This happens BEFORE rendering, not during booking submission
     */
    public static function wrap_amelia_shortcode() {
        
        // Store original Amelia shortcode handler
        global $shortcode_tags;
        
        if ( ! isset( $shortcode_tags['ameliabooking'] ) ) {
            return; // Amelia not loaded yet
        }
        
        // Store original handler
        $original_handler = $shortcode_tags['ameliabooking'];
        
        // Replace with our wrapper
        remove_shortcode( 'ameliabooking' );
        add_shortcode( 'ameliabooking', function( $atts ) use ( $original_handler ) {
            return self::validate_and_render( $atts, $original_handler );
        });
    }

    /**
     * Validate access BEFORE rendering booking form
     * 
     * @param array $atts Shortcode attributes
     * @param callable $original_handler Original Amelia shortcode handler
     * @return string HTML output
     */
    protected static function validate_and_render( $atts, $original_handler ) {
        
        // Parse shortcode attributes to get service ID
        $atts = shortcode_atts( [
            'service' => '',
            'category' => '',
            'employee' => '',
            'location' => '',
        ], $atts );

        $service_id = intval( $atts['service'] );
        
        if ( ! $service_id ) {
            // No service specified, let Amelia handle it
            return call_user_func( $original_handler, $atts );
        }

        // Get service mapping
        $mappings = get_option( 'ameliatutor_service_mappings', [] );
        
        if ( empty( $mappings[ $service_id ] ) ) {
            // Service not mapped, no restrictions
            return call_user_func( $original_handler, $atts );
        }

        $course_id = intval( $mappings[ $service_id ]['course_id'] );

        // ============================================
        // CHECK 1: User must be logged in
        // ============================================
        if ( ! is_user_logged_in() ) {
            return self::render_login_required( $service_id, $course_id );
        }

        $user_id = get_current_user_id();

        // ============================================
        // CHECK 2: User must be enrolled (if enabled)
        // ============================================
        if ( get_option( 'ameliatutor_require_enrollment', 'yes' ) === 'yes' ) {
            if ( ! tutor_utils()->is_enrolled( $course_id, $user_id ) ) {
                return self::render_enrollment_required( $service_id, $course_id );
            }
        }

        // ============================================
        // CHECK 3: Weekly booking limit (one active appointment per week)
        // ============================================
        if ( self::has_active_appointment_this_week( $user_id, $service_id ) ) {
            return self::render_weekly_limit_reached( $service_id, $course_id );
        }

        // ============================================
        // CHECK 4: Recurring session count validation
        // ============================================
        $lesson_count = LessonCounter::count_course_lessons( $course_id );
        
        if ( $lesson_count > 0 && self::is_recurring_service( $service_id ) ) {
            // Show info message about required session count
            $info_message = self::render_recurring_info( $lesson_count );
            return $info_message . call_user_func( $original_handler, $atts );
        }

        // All checks passed - render original Amelia booking form
        return call_user_func( $original_handler, $atts );
    }

    /**
     * Check if user has an active appointment for this service this week
     * 
     * @param int $user_id WordPress user ID
     * @param int $service_id Amelia service ID
     * @return bool
     */
    protected static function has_active_appointment_this_week( $user_id, $service_id ) {
        global $wpdb;

        // Get start and end of current week
        $week_start = date( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
        $week_end = date( 'Y-m-d 23:59:59', strtotime( 'sunday this week' ) );

        // Check our tracking table
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ameliatutor_bookings
                 WHERE user_id = %d
                 AND service_id = %d
                 AND booking_status IN ('pending', 'approved', 'completed')
                 AND created_at BETWEEN %s AND %s",
                $user_id,
                $service_id,
                $week_start,
                $week_end
            )
        );

        return intval( $count ) > 0;
    }

    /**
     * Check if service is configured as recurring
     * 
     * @param int $service_id Amelia service ID
     * @return bool
     */
    protected static function is_recurring_service( $service_id ) {
        global $wpdb;

        $recurring = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT recurringCycle FROM {$wpdb->prefix}amelia_services WHERE id = %d",
                $service_id
            )
        );

        return ! empty( $recurring ) && $recurring !== 'disabled';
    }

    /**
     * Render login required message
     * UPDATED: Store return URL in transient for post-login redirect
     */
    protected static function render_login_required( $service_id, $course_id ) {
        
        // Build return URL with service_id parameter
        $return_url = add_query_arg( [
            'service_id' => $service_id,
        ], get_permalink() );

        // Store return URL in transient for this session
        // Use IP + user agent as session identifier
        $session_key = md5( 
            $_SERVER['REMOTE_ADDR'] . 
            $_SERVER['HTTP_USER_AGENT'] 
        );
        
        set_transient(
            'ameliatutor_return_url_' . $session_key,
            $return_url,
            300 // 5 minutes expiry
        );

        // Dashboard login URL (without redirect_to - we'll handle it ourselves)
        $login_url = site_url( '/dashboard/' );

        ob_start();
        ?>
        <div class="ameliatutor-gate-message ameliatutor-login-required">
            <div class="ameliatutor-gate-icon">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <div class="ameliatutor-gate-content">
                <h3><?php esc_html_e( 'Login Required', 'amelia-tutor-integration' ); ?></h3>
                <p><?php esc_html_e( 'Please log in to book your session.', 'amelia-tutor-integration' ); ?></p>
                <a href="<?php echo esc_url( $login_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Log In to Continue', 'amelia-tutor-integration' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle post-login redirect
     * Hooks into wp_login to force redirect to stored return URL
     */
    public static function handle_post_login_redirect( $user_login, $user ) {
        
        // Get session key
        $session_key = md5( 
            $_SERVER['REMOTE_ADDR'] . 
            $_SERVER['HTTP_USER_AGENT'] 
        );
        
        // Get stored return URL
        $return_url = get_transient( 'ameliatutor_return_url_' . $session_key );
        
        if ( ! $return_url ) {
            return; // No stored URL, let normal flow continue
        }
        
        // Delete transient
        delete_transient( 'ameliatutor_return_url_' . $session_key );
        
        // Store in user meta as fallback (in case redirect fails)
        update_user_meta( $user->ID, '_ameliatutor_pending_redirect', $return_url );
        
        // Force hard redirect
        wp_safe_redirect( $return_url );
        exit;
    }

    /**
     * Fallback: Check for pending redirect on dashboard page
     * In case wp_login hook didn't fire
     */
    public static function check_pending_redirect() {
        
        // Only on dashboard page
        if ( ! is_page( 'dashboard' ) || ! is_user_logged_in() ) {
            return;
        }
        
        $user_id = get_current_user_id();
        $pending_url = get_user_meta( $user_id, '_ameliatutor_pending_redirect', true );
        
        if ( ! $pending_url ) {
            return;
        }
        
        // Delete meta
        delete_user_meta( $user_id, '_ameliatutor_pending_redirect' );
        
        // Force redirect
        wp_safe_redirect( $pending_url );
        exit;
    }

    /**
     * Render enrollment required message
     */
    protected static function render_enrollment_required( $service_id, $course_id ) {
        
        $course_title = get_the_title( $course_id );
        $course_url = get_permalink( $course_id );

        ob_start();
        ?>
        <div class="ameliatutor-gate-message ameliatutor-enrollment-required">
            <div class="ameliatutor-gate-icon">
                <span class="dashicons dashicons-welcome-learn-more"></span>
            </div>
            <div class="ameliatutor-gate-content">
                <h3><?php esc_html_e( 'Enrollment Required', 'amelia-tutor-integration' ); ?></h3>
                <p>
                    <?php 
                    echo sprintf(
                        esc_html__( 'You must enroll in "%s" before booking sessions.', 'amelia-tutor-integration' ),
                        '<strong>' . esc_html( $course_title ) . '</strong>'
                    ); 
                    ?>
                </p>
                <a href="<?php echo esc_url( $course_url ); ?>" class="button button-primary">
                    <?php esc_html_e( 'View Course & Enroll', 'amelia-tutor-integration' ); ?>
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render weekly booking limit message
     */
    protected static function render_weekly_limit_reached( $service_id, $course_id ) {
        
        ob_start();
        ?>
        <div class="ameliatutor-gate-message ameliatutor-weekly-limit">
            <div class="ameliatutor-gate-icon">
                <span class="dashicons dashicons-calendar-alt"></span>
            </div>
            <div class="ameliatutor-gate-content">
                <h3><?php esc_html_e( 'Booking Limit Reached', 'amelia-tutor-integration' ); ?></h3>
                <p><?php esc_html_e( 'You already have an active appointment for this service this week.', 'amelia-tutor-integration' ); ?></p>
                <p><small><?php esc_html_e( 'Please complete your current appointment before booking another.', 'amelia-tutor-integration' ); ?></small></p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render recurring session info
     */
    protected static function render_recurring_info( $lesson_count ) {
        
        ob_start();
        ?>
        <div class="ameliatutor-gate-message ameliatutor-recurring-info" style="background: #e7f3ff; border-left-color: #2271b1;">
            <div class="ameliatutor-gate-icon">
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
            </div>
            <div class="ameliatutor-gate-content">
                <h4 style="margin: 0 0 10px 0; color: #1d2327;">
                    <?php esc_html_e( 'Session Booking Requirement', 'amelia-tutor-integration' ); ?>
                </h4>
                <p style="margin: 0;">
                    <?php 
                    echo sprintf(
                        esc_html__( 'This course has %d lessons. Please select exactly %d recurring sessions when booking.', 'amelia-tutor-integration' ),
                        '<strong>' . $lesson_count . '</strong>',
                        '<strong>' . $lesson_count . '</strong>'
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}