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

        if ( ! defined( 'TUTOR_VERSION' ) ) {
            $missing[] = 'TutorLMS';
        }

        if ( ! class_exists( 'Amelia\\Plugin' ) ) {
            $missing[] = 'Amelia Booking';
        }

        if ( ! empty( $missing ) ) {
            add_action( 'admin_notices', function () use ( $missing ) {
                ?>
                <div class="notice notice-error">
                    <p>
                        <strong>Amelia–TutorLMS Integration</strong> requires:
                        <strong><?php echo esc_html( implode( ' and ', $missing ) ); ?></strong>
                    </p>
                </div>
                <?php
            } );
        }
    }
}

add_action( 'admin_init', [ 'AmeliaTutor\\Core\\Dependencies', 'check' ] );
