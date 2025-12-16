<?php
/**
 * Plugin Name: Amelia-TutorLMS Complete Integration
 * Plugin URI: https://ekesmall.com
 * Description: Complete integration between Amelia and TutorLMS - enrollment verification, lesson completion, dashboard widgets
 * Version: 1.0.1
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
define( 'AMELIATUTOR_VERSION', '1.0.0' );
define( 'AMELIATUTOR_PATH', plugin_dir_path( __FILE__ ) );
define( 'AMELIATUTOR_URL', plugin_dir_url( __FILE__ ) );
define( 'AMELIATUTOR_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load dependencies (ALL class files)
 */
require_once AMELIATUTOR_PATH . 'includes/Core/Dependencies.php';
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
    if ( ! class_exists( 'Tutor' ) || ! defined( 'AMELIA_VERSION' ) ) {
        return;
    }

    AmeliaTutor\Core\Loader::init();
});
