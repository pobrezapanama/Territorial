<?php
/**
 * Deactivator Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Deactivator
 *
 * Runs on plugin deactivation.
 */
class PSP_Territorial_Deactivator {

	/**
	 * Deactivate the plugin.
	 * Note: Data is NOT deleted on deactivation (only on uninstall).
	 */
	public static function deactivate() {
		// Clear any scheduled events.
		wp_clear_scheduled_hook( 'psp_territorial_cron' );

		// Clear transients/cache.
		self::clear_cache();
	}

	/**
	 * Clear all PSP territorial transients.
	 */
	private static function clear_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_psp_territorial_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_psp_territorial_%'" );
	}
}
