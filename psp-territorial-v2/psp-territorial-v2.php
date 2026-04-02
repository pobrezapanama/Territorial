<?php
/**
 * Plugin Name:       PSP Territorial V2
 * Plugin URI:        https://github.com/pobrezapanama/Territorial
 * Description:       API centralizada de datos territoriales de Panamá (provincias, distritos, corregimientos y comunidades) — versión 2 con IDs explícitos y jerarquía estricta.
 * Version:           2.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            PSP
 * Author URI:        https://github.com/pobrezapanama
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       psp-territorial-v2
 * Domain Path:       /languages
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PSPV2_VERSION', '2.0.0' );
define( 'PSPV2_PLUGIN_FILE', __FILE__ );
define( 'PSPV2_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PSPV2_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PSPV2_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Plugin activation hook.
 *
 * Runs before plugins_loaded, so we load the dependencies manually.
 */
function pspv2_activate() {
	require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-database.php';
	$db = new PSPV2_Database();
	$db->create_tables();

	// Auto-import on activation if data file exists and table is empty.
	require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-importer.php';
	$importer = new PSPV2_Importer( $db );
	$data_file = PSPV2_PLUGIN_DIR . 'assets/data/panama_full_geography.clean.json';
	if ( file_exists( $data_file ) && 0 === $db->count_items() ) {
		$importer->import_from_file( $data_file, false );
	}

	add_option( 'pspv2_flush_rewrite', true );
}
register_activation_hook( __FILE__, 'pspv2_activate' );

/**
 * Plugin deactivation hook.
 */
function pspv2_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'pspv2_deactivate' );

/**
 * Initialize the plugin.
 *
 * @return PSPV2_Plugin
 */
function pspv2() {
	static $instance = null;
	if ( null === $instance ) {
		require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-plugin.php';
		$instance = PSPV2_Plugin::get_instance();
	}
	return $instance;
}

// Boot the plugin.
add_action( 'plugins_loaded', 'pspv2' );
