<?php
/**
 * Admin Panel Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Admin
 *
 * Manages the WordPress admin panel for PSP Territorial.
 */
class PSP_Territorial_Admin {

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param PSP_Territorial_Database $db Database instance.
	 */
	public function __construct( PSP_Territorial_Database $db ) {
		$this->db = $db;
		$this->init_hooks();
	}

	/**
	 * Register admin hooks.
	 */
	private function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_psp_territorial_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_psp_territorial_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_psp_territorial_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_psp_territorial_export', array( $this, 'handle_export' ) );
		add_action( 'admin_init', array( $this, 'flush_rewrite_rules' ) );
	}

	/**
	 * Add admin menu pages.
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'PSP Territorial', 'psp-territorial' ),
			__( 'PSP Territorial', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'page_list' ),
			'dashicons-location-alt',
			30
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Territorios', 'psp-territorial' ),
			__( 'Territorios', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'page_list' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Agregar Territorio', 'psp-territorial' ),
			__( 'Agregar Nuevo', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-add',
			array( $this, 'page_edit' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Vista de Árbol', 'psp-territorial' ),
			__( 'Vista de Árbol', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-tree',
			array( $this, 'page_tree' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Importar / Exportar', 'psp-territorial' ),
			__( 'Importar / Exportar', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-import',
			array( $this, 'page_import_export' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Configuración', 'psp-territorial' ),
			__( 'Configuración', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-settings',
			array( $this, 'page_settings' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$psp_pages = array(
			'toplevel_page_psp-territorial',
			'psp-territorial_page_psp-territorial-add',
			'psp-territorial_page_psp-territorial-tree',
			'psp-territorial_page_psp-territorial-import',
			'psp-territorial_page_psp-territorial-settings',
		);

		if ( ! in_array( $hook, $psp_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'psp-territorial-admin',
			PSP_TERRITORIAL_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			PSP_TERRITORIAL_VERSION
		);

		wp_enqueue_style(
			'psp-territorial-tree',
			PSP_TERRITORIAL_PLUGIN_URL . 'admin/css/tree-view.css',
			array(),
			PSP_TERRITORIAL_VERSION
		);

		wp_enqueue_script(
			'psp-territorial-admin',
			PSP_TERRITORIAL_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			PSP_TERRITORIAL_VERSION,
			true
		);

		wp_enqueue_script(
			'psp-territorial-tree',
			PSP_TERRITORIAL_PLUGIN_URL . 'admin/js/hierarchy-tree.js',
			array( 'jquery' ),
			PSP_TERRITORIAL_VERSION,
			true
		);

		wp_localize_script( 'psp-territorial-admin', 'pspTerritorial', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'restUrl'   => rest_url( 'psp/v1' ),
			'nonce'     => wp_create_nonce( 'psp_territorial_nonce' ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'i18n'      => array(
				'confirmDelete' => __( '¿Está seguro de eliminar este territorio? Esta acción eliminará también todos sus elementos hijos.', 'psp-territorial' ),
				'loading'       => __( 'Cargando...', 'psp-territorial' ),
				'error'         => __( 'Ha ocurrido un error. Por favor intente de nuevo.', 'psp-territorial' ),
			),
		) );
	}

	/**
	 * Flush rewrite rules if needed.
	 */
	public function flush_rewrite_rules() {
		if ( get_option( 'psp_territorial_flush_rewrite' ) ) {
			flush_rewrite_rules();
			delete_option( 'psp_territorial_flush_rewrite' );
		}
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the territories list page.
	 */
	public function page_list() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'psp-territorial' ) );
		}
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'admin/views/territories-list.php';
	}

	/**
	 * Render the add/edit territory page.
	 */
	public function page_edit() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'psp-territorial' ) );
		}
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'admin/views/edit-territory.php';
	}

	/**
	 * Render the tree hierarchy page.
	 */
	public function page_tree() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'psp-territorial' ) );
		}
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'admin/views/tree-hierarchy.php';
	}

	/**
	 * Render the import/export page.
	 */
	public function page_import_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'psp-territorial' ) );
		}
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'admin/views/import-export.php';
	}

	/**
	 * Render the settings page.
	 */
	public function page_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', 'psp-territorial' ) );
		}
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// Form handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle save (create/update) form submission.
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permiso.', 'psp-territorial' ) );
		}

		check_admin_referer( 'psp_territorial_save', 'psp_nonce' );

		$id   = isset( $_POST['territory_id'] ) ? absint( $_POST['territory_id'] ) : 0;
		$data = PSP_Territorial_Utils::validate_input( wp_unslash( $_POST ) );

		if ( is_wp_error( $data ) ) {
			$redirect = add_query_arg(
				array(
					'page'    => $id ? 'psp-territorial' : 'psp-territorial-add',
					'action'  => $id ? 'edit' : 'add',
					'id'      => $id ?: '',
					'error'   => urlencode( implode( ' ', $data->get_error_messages() ) ),
				),
				admin_url( 'admin.php' )
			);
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( $id ) {
			$result = $this->db->update( $id, $data );
			$msg    = $result ? 'updated' : 'error';
		} else {
			$result = $this->db->insert( $data );
			$msg    = $result ? 'created' : 'error';
		}

		PSP_Territorial_Utils::clear_cache();

		$redirect = add_query_arg(
			array(
				'page' => 'psp-territorial',
				'msg'  => $msg,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle delete form submission.
	 */
	public function handle_delete() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permiso.', 'psp-territorial' ) );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'psp_territorial_delete_' . $id );

		$cascade = isset( $_GET['cascade'] ) && '1' === $_GET['cascade'];
		$result  = $this->db->delete( $id, $cascade );

		PSP_Territorial_Utils::clear_cache();

		$redirect = add_query_arg(
			array(
				'page' => 'psp-territorial',
				'msg'  => $result ? 'deleted' : 'error',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle CSV/JSON import form submission.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permiso.', 'psp-territorial' ) );
		}

		check_admin_referer( 'psp_territorial_import', 'psp_nonce' );

		$truncate = isset( $_POST['truncate'] ) && '1' === $_POST['truncate'];
		$source   = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : 'json';

		$importer = new PSP_Territorial_Importer( $this->db );

		if ( 'csv' === $source && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
			$tmp = $_FILES['csv_file']['tmp_name'];
			$result = $importer->import_from_csv( $tmp, $truncate );
		} else {
			$result = $importer->import_from_json( $truncate );
		}

		PSP_Territorial_Utils::clear_cache();

		$msg = $result['success'] ? urlencode( $result['message'] ) : urlencode( $result['message'] );
		$redirect = add_query_arg(
			array(
				'page'       => 'psp-territorial-import',
				'import_msg' => $msg,
				'success'    => $result['success'] ? '1' : '0',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle export form submission.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permiso.', 'psp-territorial' ) );
		}

		check_admin_referer( 'psp_territorial_export', 'psp_nonce' );

		$format = isset( $_POST['format'] ) ? sanitize_text_field( wp_unslash( $_POST['format'] ) ) : 'json';
		$type   = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';

		$args = array( 'limit' => 99999 );
		if ( ! empty( $type ) && PSP_Territorial_Utils::is_valid_type( $type ) ) {
			$args['type'] = $type;
		}

		$territories = $this->db->get_territories( $args );

		if ( 'csv' === $format ) {
			$this->export_csv( $territories );
		} else {
			$this->export_json( $territories );
		}
		exit;
	}

	/**
	 * Output territories as CSV download.
	 *
	 * @param array $territories Array of territory objects.
	 */
	private function export_csv( array $territories ) {
		$filename = 'psp-territorial-export-' . date( 'Y-m-d' ) . '.csv';
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		fputcsv( $out, array( 'id', 'name', 'slug', 'code', 'type', 'parent_id', 'level', 'is_active' ) );

		foreach ( $territories as $t ) {
			fputcsv( $out, array(
				$t->id,
				$t->name,
				$t->slug,
				$t->code,
				$t->type,
				$t->parent_id,
				$t->level,
				$t->is_active,
			) );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
	}

	/**
	 * Output territories as JSON download.
	 *
	 * @param array $territories Array of territory objects.
	 */
	private function export_json( array $territories ) {
		$filename = 'psp-territorial-export-' . date( 'Y-m-d' ) . '.json';
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$data = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $territories );
		echo wp_json_encode( array(
			'exported_at' => current_time( 'c' ),
			'total'       => count( $data ),
			'data'        => $data,
		), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
