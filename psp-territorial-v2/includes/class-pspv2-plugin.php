<?php
/**
 * Main Plugin Class – PSP Territorial V2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_Plugin
 *
 * Singleton orchestrator. Loads all subsystems.
 */
class PSPV2_Plugin {

	/** @var PSPV2_Plugin|null */
	private static $instance = null;

	/** @var PSPV2_Database */
	public $database;

	/** @var PSPV2_Api */
	public $api;

	/** @var PSPV2_Admin */
	public $admin;

	/**
	 * Get singleton instance.
	 *
	 * @return PSPV2_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Constructor. */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/** Load required files. */
	private function load_dependencies() {
		require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-database.php';
		require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-importer.php';
		require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-api.php';

		if ( is_admin() ) {
			require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-admin.php';
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once PSPV2_PLUGIN_DIR . 'includes/class-pspv2-cli.php';
		}
	}

	/** Register WordPress hooks. */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this, 'init_api' ) );

		$this->database = new PSPV2_Database();

		if ( is_admin() ) {
			$this->admin = new PSPV2_Admin( $this->database );
		}
	}

	/** Load plugin text domain. */
	public function load_textdomain() {
		load_plugin_textdomain(
			'psp-territorial-v2',
			false,
			dirname( PSPV2_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/** Initialize REST API. */
	public function init_api() {
		$this->api = new PSPV2_Api( $this->database );
		$this->api->register_routes();
	}
}
