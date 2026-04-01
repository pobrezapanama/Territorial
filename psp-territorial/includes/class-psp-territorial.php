<?php
/**
 * Core class for PSP Territorial.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial
 *
 * Bootstraps the plugin, loads dependencies, and registers hooks.
 */
class PSP_Territorial {

	/** @var PSP_Territorial|null Singleton instance. */
	private static ?PSP_Territorial $instance = null;

	/** @var PSP_Admin */
	private PSP_Admin $admin;

	/** @var PSP_API */
	private PSP_API $api;

	/**
	 * Private constructor – use get_instance().
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->admin = new PSP_Admin();
		$this->api   = new PSP_API();
	}

	/**
	 * Get (or create) the singleton instance.
	 *
	 * @return PSP_Territorial
	 */
	public static function get_instance(): PSP_Territorial {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function run(): void {
		add_action( 'init',             [ $this, 'load_textdomain' ] );
		add_action( 'rest_api_init',    [ $this->api, 'register_routes' ] );

		if ( is_admin() ) {
			$this->admin->init();
		}
	}

	/**
	 * Load the plugin text domain for i18n.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'psp-territorial',
			false,
			dirname( PSP_TERRITORIAL_BASENAME ) . '/languages'
		);
	}

	// -------------------------------------------------------------------------
	// Lifecycle hooks (called from the main plugin file)
	// -------------------------------------------------------------------------

	/**
	 * Plugin activation: create DB tables and import initial data.
	 */
	public static function activate(): void {
		PSP_Database::create_tables();

		if ( PSP_Database::is_empty() ) {
			$importer = new PSP_Importer();
			$importer->import( false );
		}

		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Plugin uninstall (called from uninstall.php).
	 */
	public static function uninstall(): void {
		PSP_Database::drop_tables();
		delete_option( 'psp_territorial_db_version' );
		delete_option( 'psp_territorial_imported' );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Require all dependency class files.
	 */
	private function load_dependencies(): void {
		$includes = PSP_TERRITORIAL_PATH . 'includes/';

		require_once $includes . 'class-psp-database.php';
		require_once $includes . 'class-psp-importer.php';
		require_once $includes . 'class-psp-api.php';
		require_once $includes . 'class-psp-admin.php';
	}
}
