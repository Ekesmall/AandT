<?php

namespace AmeliaTutor\Admin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Settings {

    public static function init() {
        add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function register_settings() {

        register_setting( 'ameliatutor_settings', 'ameliatutor_service_mappings' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_auto_complete_lesson' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_require_enrollment' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_recurring_complete_all' );
        register_setting( 'ameliatutor_settings', 'ameliatutor_show_dashboard_widgets' );
    }

    public static function enqueue_assets( $hook ) {

        if ( strpos( $hook, 'ameliatutor-settings' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ameliatutor-admin',
            AMELIATUTOR_URL . 'assets/admin.css',
            [],
            AMELIATUTOR_VERSION
        );

        wp_enqueue_script(
            'ameliatutor-admin',
            AMELIATUTOR_URL . 'assets/admin.js',
            [ 'jquery' ],
            AMELIATUTOR_VERSION,
            true
        );

        wp_localize_script(
            'ameliatutor-admin',
            'AmeliaTutorAdmin',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ameliatutor_admin_nonce' ),
            ]
        );
    }

    public static function render() {

        $mappings = get_option( 'ameliatutor_service_mappings', [] );
        ?>

        <div class="wrap">
            <h1><?php esc_html_e( 'Amelia–TutorLMS Integration', 'amelia-tutorlms' ); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'ameliatutor_settings' );
                do_settings_sections( 'ameliatutor_settings' );
                ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Require Course Enrollment', 'amelia-tutorlms' ); ?></th>
                        <td>
                            <input type="checkbox" name="ameliatutor_require_enrollment" value="yes"
                                <?php checked( get_option( 'ameliatutor_require_enrollment', 'yes' ), 'yes' ); ?> />
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Auto-complete Lessons', 'amelia-tutorlms' ); ?></th>
                        <td>
                            <input type="checkbox" name="ameliatutor_auto_complete_lesson" value="yes"
                                <?php checked( get_option( 'ameliatutor_auto_complete_lesson', 'yes' ), 'yes' ); ?> />
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Complete All Lessons for Recurring Bookings', 'amelia-tutorlms' ); ?></th>
                        <td>
                            <input type="checkbox" name="ameliatutor_recurring_complete_all" value="yes"
                                <?php checked( get_option( 'ameliatutor_recurring_complete_all', 'no' ), 'yes' ); ?> />
                        </td>
                    </tr>

                    <tr>
                        <th><?php esc_html_e( 'Show Dashboard Widgets', 'amelia-tutorlms' ); ?></th>
                        <td>
                            <input type="checkbox" name="ameliatutor_show_dashboard_widgets" value="yes"
                                <?php checked( get_option( 'ameliatutor_show_dashboard_widgets', 'yes' ), 'yes' ); ?> />
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Service → Course Mapping', 'amelia-tutorlms' ); ?></h2>
            <div id="ameliatutor-mapping-app">
                <!-- Mapping UI handled by admin.js -->
            </div>

        </div>
        <?php
    }
}
