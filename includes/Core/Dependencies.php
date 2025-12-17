<?php

namespace AmeliaTutor\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Dependencies {

    public static function check() {

        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }

        $missing = [];

        // Check for TutorLMS
        if ( ! defined( 'TUTOR_VERSION' ) && ! class_exists( 'Tutor' ) ) {
            $missing[] = 'TutorLMS';
        }

        // Check for Amelia
        if ( ! defined( 'AMELIA_VERSION' ) && ! class_exists( 'AmeliaBooking\Plugin' ) ) {
            $missing[] = 'Amelia Booking';
        }

        if ( ! empty( $missing ) ) {
            add_action( 'admin_notices', function () use ( $missing ) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php esc_html_e( 'Amelia–TutorLMS Integration', 'amelia-tutor-integration' ); ?></strong>
                        <?php esc_html_e( ' requires the following plugins to be installed and activated:', 'amelia-tutor-integration' ); ?>
                        <strong><?php echo esc_html( implode( ' and ', $missing ) ); ?></strong>
                    </p>
                </div>
                <?php
            } );

            // Deactivate this plugin if dependencies are missing
            deactivate_plugins( plugin_basename( __FILE__ ) );
        }
    }
}

add_action( 'admin_init', [ 'AmeliaTutor\\Core\\Dependencies', 'check' ] );