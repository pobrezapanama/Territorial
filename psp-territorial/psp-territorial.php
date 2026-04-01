<?php
/**
 * Plugin Name:       PSP Territorial
 * Plugin URI:        https://github.com/pobrezapanama/Territorial
 * Description:       División Político Administrativa de la República de Panamá. Provincias, distritos, corregimientos y comunidades con API REST y panel CRUD.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            PSP – Panamá
 * Author URI:        https://github.com/pobrezapanama
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       psp-territorial
 * Domain Path:       /languages
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// -------------------------------------------------------------------------
// Constants
// -------------------------------------------------------------------------

define( 'PSP_TERRITORIAL_VERSION',  '1.0.0' );
define( 'PSP_TERRITORIAL_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PSP_TERRITORIAL_URL',      plugin_dir_url( __FILE__ ) );
define( 'PSP_TERRITORIAL_BASENAME', plugin_basename( __FILE__ ) );

// -------------------------------------------------------------------------
// Load core class and boot the plugin
// -------------------------------------------------------------------------

require_once PSP_TERRITORIAL_PATH . 'includes/class-psp-territorial.php';

/**
 * Return the main plugin instance.
 *
 * @return PSP_Territorial
 */
function psp_territorial(): PSP_Territorial {
	return PSP_Territorial::get_instance();
}

// Boot on plugins_loaded so that all WordPress APIs are available.
add_action( 'plugins_loaded', static function () {
	psp_territorial()->run();
} );

// -------------------------------------------------------------------------
// Lifecycle hooks
// -------------------------------------------------------------------------

register_activation_hook(
	__FILE__,
	static function () {
		// Load dependencies before activation runs.
		require_once PSP_TERRITORIAL_PATH . 'includes/class-psp-database.php';
		require_once PSP_TERRITORIAL_PATH . 'includes/class-psp-importer.php';
		PSP_Territorial::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	[ 'PSP_Territorial', 'deactivate' ]
);
