<?php
/**
 * Plugin Name:       PSP Territorial
 * Plugin URI:        https://github.com/pobrezapanama/Territorial
 * Description:       API centralizada de datos territoriales de Panamá (provincias, distritos, corregimientos y comunidades). Sirve como base de datos compartida para que otros plugins consulten y utilicen estos datos.
 * Version:           1.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            PSP
 * Author URI:        https://github.com/pobrezapanama
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       psp-territorial
 * Domain Path:       /languages
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PSP_TERRITORIAL_VERSION', '1.0.0' );
define( 'PSP_TERRITORIAL_PLUGIN_FILE', __FILE__ );
define( 'PSP_TERRITORIAL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSP_TERRITORIAL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PSP_TERRITORIAL_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoloader for PSP Territorial classes.
 *
 * @param string $class_name The class name to load.
 */
function psp_territorial_autoloader( $class_name ) {
	$prefix = 'PSP_Territorial_';

	if ( strpos( $class_name, $prefix ) !== 0 ) {
		return;
	}

	$class_file = str_replace( $prefix, '', $class_name );
	$class_file = strtolower( str_replace( '_', '-', $class_file ) );
	$file       = PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-' . $class_file . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
		return;
	}

	// Check activators directory.
	$activator_file = PSP_TERRITORIAL_PLUGIN_DIR . 'includes/activators/class-' . $class_file . '.php';
	if ( file_exists( $activator_file ) ) {
		require_once $activator_file;
	}
}

spl_autoload_register( 'psp_territorial_autoloader' );

/**
 * Load public functions.
 */
require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/functions.php';

/**
 * Plugin activation hook.
 */
function psp_territorial_activate() {
	require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/activators/class-activator.php';
	PSP_Territorial_Activator::activate();
}
register_activation_hook( __FILE__, 'psp_territorial_activate' );

/**
 * Plugin deactivation hook.
 */
function psp_territorial_deactivate() {
	require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/activators/class-deactivator.php';
	PSP_Territorial_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'psp_territorial_deactivate' );

/**
 * Initialize the plugin.
 *
 * @return PSP_Territorial_Plugin
 */
function psp_territorial() {
	return PSP_Territorial_Plugin::get_instance();
}

// Boot the plugin.
add_action( 'plugins_loaded', 'psp_territorial' );
