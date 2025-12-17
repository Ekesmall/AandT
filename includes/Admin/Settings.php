<?php

namespace AmeliaTutor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'ameliatutor_settings_page', [ __CLASS__, 'render' ] );
    }

    public static function register_settings() {

        register_setting( 'ameliatutor_settings', 'ameliatutor_service_mappings' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_auto_complete_lesson' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_require_enrollment' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_recurring_complete_all' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_show_dashboard_widgets' );
    }

    public static function render() {

        $mappings = get_option( 'ameliatutor_service_mappings', [] );
        $services = self::get_amelia_services();
        $courses  = self::get_tutor_courses();
        
        ?>

        <div class="ameliatutor-settings-wrap">

            <!-- General Settings Form -->
            <form method="post" action="options.php">
                <?php
                settings_fields( 'ameliatutor_settings' );
                do_settings_sections( 'ameliatutor_settings' );
                ?>

                <h2><?php esc_html_e( 'General Settings', 'amelia-tutor-integration' ); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Require Course Enrollment', 'amelia-tutor-integration' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ameliatutor_require_enrollment" 
                                       value="yes"
                                    <?php checked( get_option( 'ameliatutor_require_enrollment', 'yes' ), 'yes' ); ?> />
                                <?php esc_html_e( 'Students must be enrolled in the course before booking sessions', 'amelia-tutor-integration' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When enabled, students cannot book Amelia appointments unless they are enrolled in the mapped TutorLMS course.', 'amelia-tutor-integration' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Auto-complete Lessons', 'amelia-tutor-integration' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ameliatutor_auto_complete_lesson" 
                                       value="yes"
                                    <?php checked( get_option( 'ameliatutor_auto_complete_lesson', 'yes' ), 'yes' ); ?> />
                                <?php esc_html_e( 'Automatically mark lessons as complete when session is approved/completed', 'amelia-tutor-integration' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'When a student completes an Amelia appointment, the corresponding TutorLMS lesson will be marked complete.', 'amelia-tutor-integration' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Complete All Lessons for Recurring Bookings', 'amelia-tutor-integration' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ameliatutor_recurring_complete_all" 
                                       value="yes"
                                    <?php checked( get_option( 'ameliatutor_recurring_complete_all', 'no' ), 'yes' ); ?> />
                                <?php esc_html_e( 'Only mark lesson complete after all recurring sessions are done', 'amelia-tutor-integration' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'For recurring appointments, wait until all sessions are completed before marking the lesson complete.', 'amelia-tutor-integration' ); ?>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <?php esc_html_e( 'Show Dashboard Widgets', 'amelia-tutor-integration' ); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="ameliatutor_show_dashboard_widgets" 
                                       value="yes"
                                    <?php checked( get_option( 'ameliatutor_show_dashboard_widgets', 'yes' ), 'yes' ); ?> />
                                <?php esc_html_e( 'Display Amelia session information in TutorLMS dashboards', 'amelia-tutor-integration' ); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e( 'Show upcoming Amelia sessions on student and instructor TutorLMS dashboards.', 'amelia-tutor-integration' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr style="margin: 40px 0;">

            <!-- Service Mapping Section -->
            <div id="ameliatutor-mapping-app">
                <h2><?php esc_html_e( 'Service → Course → Lesson Mapping', 'amelia-tutor-integration' ); ?></h2>
                
                <div class="ameliatutor-info-box">
                    <p><strong><?php esc_html_e( 'How it works:', 'amelia-tutor-integration' ); ?></strong></p>
                    <ul>
                        <li><?php esc_html_e( 'Connect each Amelia service to a TutorLMS course and optionally a specific lesson', 'amelia-tutor-integration' ); ?></li>
                        <li><?php esc_html_e( 'Students must enroll in the course before booking (if enabled above)', 'amelia-tutor-integration' ); ?></li>
                        <li><?php esc_html_e( 'When appointments are completed, the lesson is auto-marked complete (if enabled)', 'amelia-tutor-integration' ); ?></li>
                        <li><?php esc_html_e( 'If no lesson is selected, the entire course progress is tracked instead', 'amelia-tutor-integration' ); ?></li>
                    </ul>
                </div>

                <?php if ( empty( $services ) ): ?>
                    <div class="ameliatutor-error">
                        <p><strong><?php esc_html_e( 'No Amelia services found!', 'amelia-tutor-integration' ); ?></strong></p>
                        <p><?php esc_html_e( 'Please create at least one service in Amelia before setting up mappings.', 'amelia-tutor-integration' ); ?></p>
                    </div>
                <?php elseif ( empty( $courses ) ): ?>
                    <div class="ameliatutor-error">
                        <p><strong><?php esc_html_e( 'No TutorLMS courses found!', 'amelia-tutor-integration' ); ?></strong></p>
                        <p><?php esc_html_e( 'Please create at least one course in TutorLMS before setting up mappings.', 'amelia-tutor-integration' ); ?></p>
                    </div>
                <?php else: ?>
                    
                    <div class="ameliatutor-mapping-controls">
                        <p>
                            <strong><?php echo count( $mappings ); ?></strong>
                            <?php esc_html_e( 'mapping(s) configured', 'amelia-tutor-integration' ); ?>
                        </p>
                        <div>
                            <button type="button" id="ameliatutor-add-mapping" class="button">
                                <span class="dashicons dashicons-plus-alt"></span>
                                <?php esc_html_e( 'Add Mapping', 'amelia-tutor-integration' ); ?>
                            </button>
                            <button type="button" id="ameliatutor-save-mappings" class="button button-primary">
                                <?php esc_html_e( 'Save Mappings', 'amelia-tutor-integration' ); ?>
                            </button>
                        </div>
                    </div>

                    <div id="ameliatutor-mappings-container">
                        <!-- Mappings will be rendered here via JavaScript -->
                    </div>

                <?php endif; ?>
            </div>

        </div>
        <?php
    }

    /**
     * Get all Amelia services
     */
    protected static function get_amelia_services() {
        global $wpdb;

        $services = $wpdb->get_results(
            "SELECT id, name, status 
             FROM {$wpdb->prefix}amelia_services 
             WHERE status = 'visible'
             ORDER BY name ASC",
            ARRAY_A
        );

        if ( ! $services ) {
            return [];
        }

        return array_map( function( $service ) {
            return [
                'id'   => intval( $service['id'] ),
                'name' => $service['name'],
            ];
        }, $services );
    }

    /**
     * Get all TutorLMS courses
     */
    protected static function get_tutor_courses() {
        
        $courses = get_posts( [
            'post_type'      => 'courses',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        if ( ! $courses ) {
            return [];
        }

        return array_map( function( $course ) {
            return [
                'id'    => $course->ID,
                'title' => $course->post_title,
            ];
        }, $courses );
    }
}