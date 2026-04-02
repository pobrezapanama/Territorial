<?php
/**
 * Admin Handler – PSP Territorial V2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_Admin
 */
class PSPV2_Admin {

	/** @var PSPV2_Database */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param PSPV2_Database $db
	 */
	public function __construct( PSPV2_Database $db ) {
		$this->db = $db;
		$this->init_hooks();
	}

	/** Register hooks. */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pspv2_import', array( $this, 'handle_import' ) );
		add_action( 'admin_init', array( $this, 'flush_rewrites_if_needed' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_pspv2_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_pspv2_export_json', array( $this, 'ajax_export_json' ) );
	}

	/** Add admin menu pages (same slug as original plugin). */
	public function add_menu_pages() {
		add_menu_page(
			__( 'PSP Territorial V2', 'psp-territorial-v2' ),
			__( 'PSP Territorial V2', 'psp-territorial-v2' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'page_list' ),
			'dashicons-location-alt',
			31
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Territorios', 'psp-territorial-v2' ),
			__( 'Territorios', 'psp-territorial-v2' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'page_list' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Importar / Estadísticas', 'psp-territorial-v2' ),
			__( 'Importar / Stats', 'psp-territorial-v2' ),
			'manage_options',
			'psp-territorial-import',
			array( $this, 'page_import' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current page hook.
	 */
	public function enqueue_assets( $hook ) {
		$psp_pages = array(
			'toplevel_page_psp-territorial',
			'psp-territorial_page_psp-territorial-import',
		);

		if ( ! in_array( $hook, $psp_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'pspv2-admin',
			PSPV2_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			PSPV2_VERSION
		);

		wp_enqueue_script(
			'pspv2-admin',
			PSPV2_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			PSPV2_VERSION,
			true
		);

		wp_localize_script( 'pspv2-admin', 'pspv2', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'pspv2_nonce' ),
			'i18n'    => array(
				'importing' => __( 'Importando…', 'psp-territorial-v2' ),
				'success'   => __( 'Importación completada.', 'psp-territorial-v2' ),
				'error'     => __( 'Error al importar.', 'psp-territorial-v2' ),
			),
		) );
	}

	/** Flush rewrite rules if flagged. */
	public function flush_rewrites_if_needed() {
		if ( get_option( 'pspv2_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'pspv2_flush_rewrite' );
		}
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/** Render the territories list page. */
	public function page_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'psp-territorial-v2' ) );
		}
		require_once PSPV2_PLUGIN_DIR . 'admin/views/list.php';
	}

	/** Render the import/stats page. */
	public function page_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'psp-territorial-v2' ) );
		}
		require_once PSPV2_PLUGIN_DIR . 'admin/views/import.php';
	}

	// -------------------------------------------------------------------------
	// Form / AJAX handlers
	// -------------------------------------------------------------------------

	/** Handle form-based import (fallback). */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Acceso denegado.', 'psp-territorial-v2' ) );
		}
		check_admin_referer( 'pspv2_import_action', 'pspv2_import_nonce' );

		$truncate  = isset( $_POST['truncate'] ) && '1' === $_POST['truncate'];
		$json_path = PSPV2_PLUGIN_DIR . 'assets/data/panama_full_geography.clean.json';

		$importer = new PSPV2_Importer( $this->db );
		$result   = $importer->import_from_file( $json_path, $truncate );

		$msg = $result['success'] ? 'imported' : 'error';
		wp_safe_redirect( add_query_arg( 'msg', $msg, admin_url( 'admin.php?page=psp-territorial-import' ) ) );
		exit;
	}

	/** AJAX import handler. */
	public function ajax_import() {
		check_ajax_referer( 'pspv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Acceso denegado.' ) );
		}

		$truncate  = isset( $_POST['truncate'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['truncate'] ) );
		$json_path = PSPV2_PLUGIN_DIR . 'assets/data/panama_full_geography.clean.json';

		$importer = new PSPV2_Importer( $this->db );
		$result   = $importer->import_from_file( $json_path, $truncate );

		if ( $result['success'] ) {
			wp_send_json_success( array(
				'message'  => $result['message'],
				'inserted' => $result['inserted'],
				'skipped'  => $result['skipped'],
				'errors'   => $result['errors'],
			) );
		} else {
			wp_send_json_error( array( 'message' => $result['message'] ) );
		}
	}

	/** AJAX JSON export. */
	public function ajax_export_json() {
		check_ajax_referer( 'pspv2_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Acceso denegado.' );
		}

		$all   = $this->db->get_items( array( 'limit' => 100000 ) );
		$rows  = array_map( function( $item ) {
			return array(
				'id'        => (int) $item->id,
				'name'      => $item->name,
				'slug'      => $item->slug,
				'code'      => $item->code,
				'type'      => $item->type,
				'parent_id' => null !== $item->parent_id ? (int) $item->parent_id : null,
				'level'     => (int) $item->level,
			);
		}, $all );

		$export = array(
			'meta' => array(
				'exported_at' => current_time( 'c' ),
				'total'       => count( $rows ),
				'plugin'      => 'psp-territorial-v2',
			),
			'data' => $rows,
		);

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="psp-territorial-v2-export-' . date( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
		exit;
	}
}
