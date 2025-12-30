<?php
/**
 * Plugin Name: Amelia-TutorLMS Complete Integration
 * Plugin URI: https://ekesmall.com
 * Description: Complete integration between Amelia and TutorLMS - enrollment verification, lesson completion, dashboard widgets
 * Version: 1.0.5
 * Author: Ekemode Quazim (Ekesmall)
 * Author URI: https://ekesmall.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * Text Domain: amelia-tutor-integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Plugin constants
 */
define( 'AMELIATUTOR_VERSION', '1.0.1' );
define( 'AMELIATUTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMELIATUTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'AMELIATUTOR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for plugin classes
 */
spl_autoload_register( function( $class ) {
    // Only load our namespace
    if ( strpos( $class, 'AmeliaTutor\\' ) !== 0 ) {
        return;
    }

    // Convert namespace to file path
    $class = str_replace( 'AmeliaTutor\\', '', $class );
    $class = str_replace( '\\', '/', $class );
    $file  = AMELIATUTOR_PATH . 'includes/' . $class . '.php';

    if ( file_exists( $file ) ) {
        require_once $file;
    }
});

/**
 * Load core files
 */
require_once AMELIATUTOR_PATH . 'includes/Core/Activator.php';
require_once AMELIATUTOR_PATH . 'includes/Core/Deactivator.php';

/**
 * Activation / Deactivation hooks
 */
register_activation_hook(
    __FILE__,
    [ 'AmeliaTutor\\Core\\Activator', 'activate' ]
);

register_deactivation_hook(
    __FILE__,
    [ 'AmeliaTutor\\Core\\Deactivator', 'deactivate' ]
);

/**
 * Boot plugin AFTER all plugins are loaded
 */
add_action( 'plugins_loaded', function () {

    // Safety: ensure required plugins exist
    $has_tutor = defined( 'TUTOR_VERSION' ) || class_exists( 'Tutor' );
    $has_amelia = defined( 'AMELIA_VERSION' ) || class_exists( 'AmeliaBooking\\Plugin' );

    if ( ! $has_tutor || ! $has_amelia ) {
        // Show admin notice about missing dependencies
        add_action( 'admin_notices', function() use ( $has_tutor, $has_amelia ) {
            $missing = [];
            if ( ! $has_tutor ) $missing[] = 'TutorLMS';
            if ( ! $has_amelia ) $missing[] = 'Amelia Booking';
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e( 'Amelia–TutorLMS Integration', 'amelia-tutor-integration' ); ?></strong>
                    <?php esc_html_e( ' requires the following plugins:', 'amelia-tutor-integration' ); ?>
                    <strong><?php echo esc_html( implode( ' and ', $missing ) ); ?></strong>
                </p>
            </div>
            <?php
        });
        return;
    }

// Initialize the plugin
if ( class_exists( 'AmeliaTutor\\Core\\Loader' ) ) {
    AmeliaTutor\Core\Loader::init();
}


}, 20 ); // Priority 20 to ensure Tutor and Amelia are loaded first

/**
 * Add settings link on plugins page
 */
add_filter( 'plugin_action_links_' . AMELIATUTOR_BASENAME, function( $links ) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=ameliatutor-settings' ),
        __( 'Settings', 'amelia-tutor-integration' )
    );
    array_unshift( $links, $settings_link );
    return $links;
});

/**
 * Frontend redirect after TutorLMS Vue dashboard login
 */
add_action( 'wp_enqueue_scripts', function () {

    // Only on dashboard page and if redirect_to is present
    if ( ! is_page( 'dashboard' ) || empty( $_GET['redirect_to'] ) ) {
        return;
    }

    wp_add_inline_script(
        'jquery',
        "
        (function () {
            const params = new URLSearchParams(window.location.search);
            const redirectTo = params.get('redirect_to');

            if (!redirectTo) return;

            const targetUrl = decodeURIComponent(redirectTo);

            if (!targetUrl.startsWith(window.location.origin)) return;

            // Observe DOM for Vue login → dashboard swap
            const observer = new MutationObserver(() => {
                if (document.body.classList.contains('logged-in')) {
                    observer.disconnect();
                    window.location.href = targetUrl;
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        })();
        "
    );
});