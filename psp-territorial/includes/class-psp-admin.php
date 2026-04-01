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
		add_action( 'admin_post_psp_territorial_repair', array( $this, 'handle_repair' ) );
		add_action( 'admin_init', array( $this, 'flush_rewrite_rules' ) );
		add_action( 'admin_notices', array( $this, 'data_integrity_notice' ) );
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

		$importer = new PSP_Importer_V2( $this->db );

		if ( 'csv' === $source && ! empty( $_FILES['csv_file']['tmp_name'] ) ) {
			$old_importer = new PSP_Territorial_Importer( $this->db );
			$rows         = $old_importer->parse_csv( $_FILES['csv_file']['tmp_name'] );
			if ( ! empty( $rows ) ) {
				$result = $importer->import_with_validation( $rows, $truncate );
			} else {
				$result = array(
					'success' => false,
					'message' => __( 'No se encontraron datos en el CSV.', 'psp-territorial' ),
				);
			}
		} else {
			$clean_path  = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_clean.json';
			$legacy_path = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_data.json';
			$json_path   = file_exists( $clean_path ) ? $clean_path : $legacy_path;
			$result      = $importer->import_from_file( $json_path, $truncate );
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

	/**
	 * Show an admin notice when the territory data has orphaned parent_id
	 * references — a sign that the data was imported with the old broken
	 * importer that omitted the id column from its batch INSERT statements.
	 */
	public function data_integrity_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show on PSP Territorial admin pages.
		$screen = get_current_screen();
		if ( ! $screen || strpos( $screen->id, 'psp-territorial' ) === false ) {
			return;
		}

		// Skip if the notice was already dismissed this session.
		if ( get_transient( 'psp_territorial_integrity_ok' ) ) {
			return;
		}

		if ( ! $this->db->tables_exist() ) {
			return;
		}

		if ( ! $this->db->has_orphaned_records() ) {
			// Mark data as clean so we don't re-check on every page load.
			set_transient( 'psp_territorial_integrity_ok', 1, HOUR_IN_SECONDS );
			return;
		}

		$repair_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=psp_territorial_repair' ),
			'psp_territorial_repair'
		);
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( '⚠️ PSP Territorial: datos corruptos detectados', 'psp-territorial' ); ?></strong>
			</p>
			<p>
				<?php
				esc_html_e(
					'Se detectaron registros con referencias a territorios padre que no existen. Esto ocurre cuando los datos fueron importados con una versión anterior del plugin que no asignaba los IDs correctamente. Como resultado, los corregimientos y comunidades muestran IDs, códigos, padres e hijos incorrectos en el buscador.',
					'psp-territorial'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $repair_url ); ?>" class="button button-primary">
					<?php esc_html_e( '🔧 Reparar datos ahora (reimportar)', 'psp-territorial' ); ?>
				</a>
				&nbsp;
				<em><?php esc_html_e( 'Nota: esto eliminará los datos actuales y reimportará desde el JSON incluido.', 'psp-territorial' ); ?></em>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle the one-click data repair action.
	 *
	 * Truncates the territory table and re-imports from the bundled clean JSON
	 * using the V2 importer so that all IDs and parent_id references are correct.
	 */
	public function handle_repair() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sin permiso.', 'psp-territorial' ) );
		}

		check_admin_referer( 'psp_territorial_repair' );

		$clean_path  = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_clean.json';
		$legacy_path = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_data.json';
		$json_path   = file_exists( $clean_path ) ? $clean_path : $legacy_path;

		$importer = new PSP_Importer_V2( $this->db );
		$result   = $importer->import_from_file( $json_path, true );

		PSP_Territorial_Utils::clear_cache();
		delete_transient( 'psp_territorial_integrity_ok' );

		$msg     = urlencode( $result['message'] );
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
}
