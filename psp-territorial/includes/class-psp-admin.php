<?php
/**
 * Admin panel for PSP Territorial.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Admin
 *
 * Registers WordPress admin menus, scripts, styles, and handles admin-side
 * CRUD logic for territorial entities.
 */
class PSP_Admin {

	/**
	 * Bootstrap the admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu',            [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_psp_get_parents', [ $this, 'ajax_get_parents' ] );
		add_action( 'admin_init',            [ $this, 'handle_delete' ] );
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	/**
	 * Register admin menu pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'PSP Territorial', 'psp-territorial' ),
			__( 'Territorial', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			[ $this, 'render_list' ],
			'dashicons-admin-site-alt3',
			80
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Entidades', 'psp-territorial' ),
			__( 'Entidades', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			[ $this, 'render_list' ]
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Agregar / Editar', 'psp-territorial' ),
			__( 'Agregar nuevo', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-edit',
			[ $this, 'render_edit' ]
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Ajustes', 'psp-territorial' ),
			__( 'Ajustes', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-settings',
			[ $this, 'render_settings' ]
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue admin CSS and JS on our pages only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		$our_pages = [
			'toplevel_page_psp-territorial',
			'territorial_page_psp-territorial-edit',
			'territorial_page_psp-territorial-settings',
		];

		if ( ! in_array( $hook_suffix, $our_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'psp-territorial-admin',
			PSP_TERRITORIAL_URL . 'admin/css/admin.css',
			[],
			PSP_TERRITORIAL_VERSION
		);

		wp_enqueue_script(
			'psp-territorial-admin',
			PSP_TERRITORIAL_URL . 'admin/js/admin.js',
			[ 'jquery' ],
			PSP_TERRITORIAL_VERSION,
			true
		);

		wp_localize_script(
			'psp-territorial-admin',
			'pspTerritorial',
			[
				'confirmDelete' => __( '¿Eliminar "%s" y todos sus hijos? Esta acción no se puede deshacer.', 'psp-territorial' ),
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'psp_get_parents' ),
			]
		);
	}

	// -------------------------------------------------------------------------
	// Render callbacks
	// -------------------------------------------------------------------------

	/** Render the entity list page. */
	public function render_list(): void {
		require_once PSP_TERRITORIAL_PATH . 'admin/views/provinces-list.php';
	}

	/** Render the create/edit page. */
	public function render_edit(): void {
		require_once PSP_TERRITORIAL_PATH . 'admin/views/edit-entity.php';
	}

	/** Render the settings page. */
	public function render_settings(): void {
		require_once PSP_TERRITORIAL_PATH . 'admin/views/settings.php';
	}

	// -------------------------------------------------------------------------
	// Delete handler
	// -------------------------------------------------------------------------

	/**
	 * Handle delete action triggered by a GET request with a nonce.
	 */
	public function handle_delete(): void {
		if ( ! isset( $_GET['page'], $_GET['action'], $_GET['id'] ) ) {
			return;
		}
		if ( 'psp-territorial' !== $_GET['page'] || 'delete' !== $_GET['action'] ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permiso denegado.', 'psp-territorial' ) );
		}

		$id = absint( $_GET['id'] );
		check_admin_referer( 'psp_delete_' . $id );

		$entity = PSP_Database::get_by_id( $id );
		if ( $entity ) {
			$deleted = PSP_Database::delete( $id );

			/**
			 * Fires after a territorial entity is deleted.
			 *
			 * @param int    $id      Deleted entity ID.
			 * @param string $type    Entity type.
			 * @param int    $deleted Number of rows deleted (including descendants).
			 */
			do_action( 'psp_territorial_entity_deleted', $id, $entity->type, $deleted );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=psp-territorial&msg=deleted' ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX
	// -------------------------------------------------------------------------

	/**
	 * AJAX handler: return entities of a given type as options for the parent
	 * dropdown in the edit form.
	 */
	public function ajax_get_parents(): void {
		check_ajax_referer( 'psp_get_parents' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$type  = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
		$valid = [ 'provincia', 'distrito', 'corregimiento' ];

		if ( ! in_array( $type, $valid, true ) ) {
			wp_send_json_error( 'Invalid type', 400 );
		}

		$items = PSP_Database::get_by_type( $type );

		$data = array_map(
			static function ( $item ) {
				return [
					'id'   => (int) $item->id,
					'name' => $item->name,
				];
			},
			$items
		);

		wp_send_json_success( $data );
	}
}
