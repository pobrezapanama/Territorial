<?php
/**
 * Uninstall handler for PSP Territorial.
 *
 * Runs when the plugin is deleted via the WordPress admin.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-psp-database.php';

PSP_Database::drop_tables();
delete_option( 'psp_territorial_db_version' );
delete_option( 'psp_territorial_imported' );
