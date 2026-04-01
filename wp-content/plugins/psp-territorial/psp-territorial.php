<?php
/**
 * Plugin Name:       PSP Territorial
 * Plugin URI:        https://github.com/pobrezapanama/Territorial
 * Description:       Manages hierarchical geographical data for Panama (Provinces, Districts, Corregimientos, and Communities) with REST API and admin interface.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            PSP Panama
 * Author URI:        https://github.com/pobrezapanama
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       psp-territorial
 * Domain Path:       /languages
 */

namespace PSPTerritorial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PSP_TERRITORIAL_VERSION', '1.0.0' );
define( 'PSP_TERRITORIAL_FILE', __FILE__ );
define( 'PSP_TERRITORIAL_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSP_TERRITORIAL_URL', plugin_dir_url( __FILE__ ) );
define( 'PSP_TERRITORIAL_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
spl_autoload_register(
	function ( $class ) {
		$prefix = 'PSPTerritorial\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative = substr( $class, $len );
		$file     = PSP_TERRITORIAL_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Activation / deactivation hooks.
register_activation_hook( __FILE__, array( 'PSPTerritorial\\Database', 'install' ) );
register_deactivation_hook( __FILE__, array( 'PSPTerritorial\\Database', 'deactivate' ) );

/**
 * Boot the plugin.
 */
function psp_territorial_boot() {
	// Init database (checks if tables exist).
	Database::get_instance();

	// Register REST API routes.
	add_action( 'rest_api_init', array( 'PSPTerritorial\\Api\\LocationController', 'register_routes' ) );

	// Init admin.
	if ( is_admin() ) {
		new Admin\Dashboard();
	}
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\\psp_territorial_boot' );
