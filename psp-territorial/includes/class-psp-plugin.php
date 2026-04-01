<?php
/**
 * Main Plugin Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Plugin
 *
 * Main plugin orchestrator. Initializes all subsystems.
 */
class PSP_Territorial_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PSP_Territorial_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Database handler.
	 *
	 * @var PSP_Territorial_Database
	 */
	public $database;

	/**
	 * REST API handler.
	 *
	 * @var PSP_Territorial_Api
	 */
	public $api;

	/**
	 * Admin handler.
	 *
	 * @var PSP_Territorial_Admin
	 */
	public $admin;

	/**
	 * Get the singleton instance.
	 *
	 * @return PSP_Territorial_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required dependencies.
	 */
	private function load_dependencies() {
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-database.php';
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-utils.php';
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-query.php';
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-importer.php';
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-importer-v2.php';
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-api.php';

		if ( is_admin() ) {
			require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-admin.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-cli.php';
		}
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'init_api' ) );

		$this->database = new PSP_Territorial_Database();

		if ( is_admin() ) {
			$this->admin = new PSP_Territorial_Admin( $this->database );
		}
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'psp-territorial',
			false,
			dirname( PSP_TERRITORIAL_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize the REST API.
	 */
	public function init_api() {
		$this->api = new PSP_Territorial_Api( $this->database );
		$this->api->register_routes();
	}
}
