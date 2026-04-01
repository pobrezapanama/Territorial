<?php
/**
 * Activator Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Activator
 *
 * Runs on plugin activation.
 */
class PSP_Territorial_Activator {

	/**
	 * Activate the plugin.
	 */
	public static function activate() {
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-database.php';

		$db = new PSP_Territorial_Database();
		$db->create_tables();

		// Import data if table is empty.
		if ( self::should_import() ) {
			require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-utils.php';
			require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-importer.php';
			$importer = new PSP_Territorial_Importer( $db );
			$importer->import_from_json();
		}

		// Set a flag to flush rewrite rules.
		update_option( 'psp_territorial_flush_rewrite', 1 );
	}

	/**
	 * Determine whether to auto-import data.
	 *
	 * @return bool
	 */
	private static function should_import() {
		global $wpdb;
		$db    = new PSP_Territorial_Database();
		$table = $db->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		return $count === 0;
	}
}
