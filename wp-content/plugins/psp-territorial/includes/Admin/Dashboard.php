<?php
/**
 * Admin dashboard for PSP Territorial.
 *
 * @package PSPTerritorial
 */

namespace PSPTerritorial\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PSPTerritorial\Database;
use PSPTerritorial\Helpers;
use PSPTerritorial\Importer\CsvImporter;

/**
 * Handles the WordPress admin interface pages.
 */
class Dashboard {

	/**
	 * Constructor – registers hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_psp_territorial_save_item', array( $this, 'ajax_save_item' ) );
		add_action( 'wp_ajax_psp_territorial_delete_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_psp_territorial_import_csv', array( $this, 'ajax_import_csv' ) );
		add_action( 'wp_ajax_psp_territorial_get_children', array( $this, 'ajax_get_children' ) );
		add_action( 'wp_ajax_psp_territorial_export', array( $this, 'ajax_export' ) );
	}

	/**
	 * Register admin menu pages.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'PSP Territorial', 'psp-territorial' ),
			__( 'PSP Territorial', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'render_main_page' ),
			'dashicons-location-alt',
			30
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Hierarchy', 'psp-territorial' ),
			__( 'Hierarchy', 'psp-territorial' ),
			'manage_options',
			'psp-territorial',
			array( $this, 'render_main_page' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Import / Export', 'psp-territorial' ),
			__( 'Import / Export', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-import',
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			'psp-territorial',
			__( 'Settings', 'psp-territorial' ),
			__( 'Settings', 'psp-territorial' ),
			'manage_options',
			'psp-territorial-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue CSS and JS assets only on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		$pages = array(
			'toplevel_page_psp-territorial',
			'psp-territorial_page_psp-territorial-import',
			'psp-territorial_page_psp-territorial-settings',
		);

		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'psp-territorial-admin',
			PSP_TERRITORIAL_URL . 'assets/css/admin.css',
			array(),
			PSP_TERRITORIAL_VERSION
		);

		wp_enqueue_script(
			'psp-territorial-admin',
			PSP_TERRITORIAL_URL . 'assets/js/admin.js',
			array( 'jquery', 'wp-util' ),
			PSP_TERRITORIAL_VERSION,
			true
		);

		wp_localize_script(
			'psp-territorial-admin',
			'PSPTerritorial',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'psp_territorial_nonce' ),
				'i18n'    => array(
					'confirmDelete' => __( 'Are you sure you want to delete this item and ALL its children? This cannot be undone.', 'psp-territorial' ),
					'saving'        => __( 'Saving…', 'psp-territorial' ),
					'saved'         => __( 'Saved!', 'psp-territorial' ),
					'deleting'      => __( 'Deleting…', 'psp-territorial' ),
					'error'         => __( 'An error occurred. Please try again.', 'psp-territorial' ),
					'addProvince'   => __( 'Add Province', 'psp-territorial' ),
					'addDistrict'   => __( 'Add District', 'psp-territorial' ),
					'addCorregimiento' => __( 'Add Corregimiento', 'psp-territorial' ),
					'addCommunity'  => __( 'Add Community', 'psp-territorial' ),
				),
			)
		);
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the main hierarchy page.
	 */
	public function render_main_page() {
		$this->check_capability();
		$counts = Database::get_counts();
		?>
		<div class="wrap psp-territorial-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'PSP Territorial – Hierarchy', 'psp-territorial' ); ?></h1>

			<div class="psp-stats">
				<span><?php echo esc_html( number_format( $counts['province'] ) ); ?> <?php esc_html_e( 'Provinces', 'psp-territorial' ); ?></span>
				<span><?php echo esc_html( number_format( $counts['district'] ) ); ?> <?php esc_html_e( 'Districts', 'psp-territorial' ); ?></span>
				<span><?php echo esc_html( number_format( $counts['corregimiento'] ) ); ?> <?php esc_html_e( 'Corregimientos', 'psp-territorial' ); ?></span>
				<span><?php echo esc_html( number_format( $counts['community'] ) ); ?> <?php esc_html_e( 'Communities', 'psp-territorial' ); ?></span>
			</div>

			<div class="psp-toolbar">
				<div class="psp-search-box">
					<input type="text" id="psp-search" placeholder="<?php esc_attr_e( 'Search by name…', 'psp-territorial' ); ?>" class="regular-text" />
					<button type="button" class="button" id="psp-search-btn"><?php esc_html_e( 'Search', 'psp-territorial' ); ?></button>
					<button type="button" class="button" id="psp-search-clear" style="display:none"><?php esc_html_e( 'Clear', 'psp-territorial' ); ?></button>
				</div>
				<button type="button" class="button button-primary" id="psp-add-province-btn">
					+ <?php esc_html_e( 'Add Province', 'psp-territorial' ); ?>
				</button>
			</div>

			<!-- Search results container -->
			<div id="psp-search-results" style="display:none">
				<h2><?php esc_html_e( 'Search Results', 'psp-territorial' ); ?></h2>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Name', 'psp-territorial' ); ?></th>
							<th><?php esc_html_e( 'Type', 'psp-territorial' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'psp-territorial' ); ?></th>
						</tr>
					</thead>
					<tbody id="psp-search-results-body"></tbody>
				</table>
			</div>

			<!-- Tree container -->
			<div id="psp-tree-container" class="psp-tree">
				<div id="psp-tree-root">
					<?php $this->render_province_list(); ?>
				</div>
			</div>

			<!-- Item modal -->
			<?php $this->render_item_modal(); ?>
		</div>
		<?php
	}

	/**
	 * Render the list of provinces as tree nodes.
	 */
	private function render_province_list() {
		$provinces = Database::get_items( 'province' );

		if ( empty( $provinces ) ) {
			echo '<p class="psp-empty">' . esc_html__( 'No territorial data found. Use Import / Export to load data.', 'psp-territorial' ) . '</p>';
			return;
		}

		echo '<ul class="psp-level-province">';
		foreach ( $provinces as $province ) {
			$this->render_tree_node( $province );
		}
		echo '</ul>';
	}

	/**
	 * Render a single tree node (province, district, corregimiento, or community).
	 *
	 * @param array $item   DB row.
	 * @param bool  $nested Whether this is pre-loaded (false = lazy-load children via JS).
	 */
	private function render_tree_node( array $item, $nested = false ) {
		$type       = $item['type'];
		$child_type = Helpers::child_type( $type );
		$has_children = $child_type !== null;

		$classes = array( 'psp-node', "psp-node-{$type}" );
		if ( $has_children ) {
			$classes[] = 'psp-has-children';
			$classes[] = 'psp-collapsed';
		}

		echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '" data-id="' . esc_attr( $item['id'] ) . '" data-type="' . esc_attr( $type ) . '" data-name="' . esc_attr( $item['name'] ) . '">';

		if ( $has_children ) {
			echo '<span class="psp-toggle dashicons dashicons-arrow-right-alt2"></span>';
		} else {
			echo '<span class="psp-toggle-placeholder"></span>';
		}

		echo '<span class="psp-node-name">' . esc_html( $item['name'] ) . '</span>';
		echo '<span class="psp-node-type-badge">' . esc_html( Helpers::type_label( $type ) ) . '</span>';

		echo '<span class="psp-node-actions">';
		if ( $has_children ) {
			echo '<button type="button" class="button button-small psp-add-child-btn" data-parent-id="' . esc_attr( $item['id'] ) . '" data-parent-type="' . esc_attr( $type ) . '">';
			echo '+ ' . esc_html( Helpers::type_label( $child_type ) );
			echo '</button>';
		}
		echo '<button type="button" class="button button-small psp-edit-btn" data-id="' . esc_attr( $item['id'] ) . '" data-type="' . esc_attr( $type ) . '">' . esc_html__( 'Edit', 'psp-territorial' ) . '</button>';
		echo '<button type="button" class="button button-small button-link-delete psp-delete-btn" data-id="' . esc_attr( $item['id'] ) . '" data-type="' . esc_attr( $type ) . '" data-name="' . esc_attr( $item['name'] ) . '">' . esc_html__( 'Delete', 'psp-territorial' ) . '</button>';
		echo '</span>';

		if ( $has_children ) {
			echo '<ul class="psp-children psp-level-' . esc_attr( $child_type ) . '" style="display:none" data-loaded="false"></ul>';
		}

		echo '</li>';
	}

	/**
	 * Render the add/edit modal dialog.
	 */
	private function render_item_modal() {
		?>
		<div id="psp-modal-overlay" class="psp-modal-overlay" style="display:none">
			<div class="psp-modal">
				<button type="button" class="psp-modal-close" id="psp-modal-close">&times;</button>
				<h2 id="psp-modal-title"><?php esc_html_e( 'Add Item', 'psp-territorial' ); ?></h2>

				<form id="psp-item-form">
					<?php wp_nonce_field( 'psp_territorial_nonce', 'psp_nonce' ); ?>
					<input type="hidden" id="psp-item-id" name="item_id" value="" />
					<input type="hidden" id="psp-item-type" name="item_type" value="" />
					<input type="hidden" id="psp-item-parent-id" name="parent_id" value="" />

					<table class="form-table">
						<tr>
							<th><label for="psp-item-name"><?php esc_html_e( 'Name', 'psp-territorial' ); ?> <span class="required">*</span></label></th>
							<td><input type="text" id="psp-item-name" name="name" class="regular-text" required /></td>
						</tr>
						<tr>
							<th><label for="psp-item-description"><?php esc_html_e( 'Description', 'psp-territorial' ); ?></label></th>
							<td><textarea id="psp-item-description" name="description" rows="3" class="regular-text"></textarea></td>
						</tr>
					</table>

					<p id="psp-form-messages"></p>

					<div class="psp-modal-footer">
						<button type="submit" class="button button-primary" id="psp-modal-save">
							<?php esc_html_e( 'Save', 'psp-territorial' ); ?>
						</button>
						<button type="button" class="button" id="psp-modal-cancel">
							<?php esc_html_e( 'Cancel', 'psp-territorial' ); ?>
						</button>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Import / Export page.
	 */
	public function render_import_page() {
		$this->check_capability();
		?>
		<div class="wrap psp-territorial-wrap">
			<h1><?php esc_html_e( 'Import / Export', 'psp-territorial' ); ?></h1>

			<div class="psp-cards">

				<!-- Import CSV -->
				<div class="psp-card">
					<h2><?php esc_html_e( 'Import from CSV', 'psp-territorial' ); ?></h2>
					<p><?php esc_html_e( 'Upload a semicolon-separated CSV file with columns: Provincia;Distrito;Corregimiento;Comunidad', 'psp-territorial' ); ?></p>

					<form id="psp-import-form" enctype="multipart/form-data">
						<?php wp_nonce_field( 'psp_territorial_nonce', 'psp_nonce' ); ?>
						<table class="form-table">
							<tr>
								<th><label for="psp-csv-file"><?php esc_html_e( 'CSV File', 'psp-territorial' ); ?></label></th>
								<td>
									<input type="file" id="psp-csv-file" name="csv_file" accept=".csv" required />
								</td>
							</tr>
						</table>
						<p>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Import', 'psp-territorial' ); ?>
							</button>
						</p>
					</form>

					<div id="psp-import-progress" style="display:none">
						<div class="psp-progress-bar"><div class="psp-progress-bar-inner" style="width:0%"></div></div>
						<p id="psp-import-status"></p>
					</div>

					<div id="psp-import-results" style="display:none"></div>
				</div>

				<!-- Export -->
				<div class="psp-card">
					<h2><?php esc_html_e( 'Export Data', 'psp-territorial' ); ?></h2>
					<p><?php esc_html_e( 'Download all territorial data in your preferred format.', 'psp-territorial' ); ?></p>

					<p>
						<button type="button" class="button button-primary" id="psp-export-json-btn" data-format="json">
							<?php esc_html_e( 'Export as JSON', 'psp-territorial' ); ?>
						</button>
						&nbsp;
						<button type="button" class="button" id="psp-export-csv-btn" data-format="csv">
							<?php esc_html_e( 'Export as CSV', 'psp-territorial' ); ?>
						</button>
					</p>
				</div>

				<!-- Re-import default data -->
				<div class="psp-card">
					<h2><?php esc_html_e( 'Reset to Default Panama Data', 'psp-territorial' ); ?></h2>
					<p><?php esc_html_e( 'WARNING: This will clear ALL existing data and re-import the bundled Panama dataset.', 'psp-territorial' ); ?></p>
					<form id="psp-reset-form">
						<?php wp_nonce_field( 'psp_territorial_nonce', 'psp_nonce' ); ?>
						<p>
							<button type="submit" class="button button-link-delete" id="psp-reset-btn">
								<?php esc_html_e( 'Reset &amp; Re-import Default Data', 'psp-territorial' ); ?>
							</button>
						</p>
					</form>
					<div id="psp-reset-results" style="display:none"></div>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Render the Settings page.
	 */
	public function render_settings_page() {
		$this->check_capability();

		if ( isset( $_POST['psp_territorial_save_settings'] ) ) {
			check_admin_referer( 'psp_territorial_settings_nonce' );
			$this->save_settings();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'psp-territorial' ) . '</p></div>';
		}

		$enable_api      = get_option( 'psp_territorial_enable_api', '1' );
		$manage_cap      = get_option( 'psp_territorial_manage_cap', 'manage_options' );
		$cache_duration  = get_option( 'psp_territorial_cache_duration', 3600 );
		$batch_size      = get_option( 'psp_territorial_batch_size', 500 );
		?>
		<div class="wrap psp-territorial-wrap">
			<h1><?php esc_html_e( 'PSP Territorial Settings', 'psp-territorial' ); ?></h1>

			<form method="post">
				<?php wp_nonce_field( 'psp_territorial_settings_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Enable REST API', 'psp-territorial' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enable_api" value="1" <?php checked( '1', $enable_api ); ?> />
								<?php esc_html_e( 'Expose territorial data via the WordPress REST API', 'psp-territorial' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><label for="manage_cap"><?php esc_html_e( 'Management Capability', 'psp-territorial' ); ?></label></th>
						<td>
							<input type="text" id="manage_cap" name="manage_cap" value="<?php echo esc_attr( $manage_cap ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'WordPress capability required to manage territorial data. Default: manage_options', 'psp-territorial' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cache_duration"><?php esc_html_e( 'Cache Duration (seconds)', 'psp-territorial' ); ?></label></th>
						<td>
							<input type="number" id="cache_duration" name="cache_duration" value="<?php echo esc_attr( $cache_duration ); ?>" min="0" max="86400" class="small-text" />
							<p class="description"><?php esc_html_e( 'How long to cache query results. Set to 0 to disable caching.', 'psp-territorial' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="batch_size"><?php esc_html_e( 'Import Batch Size', 'psp-territorial' ); ?></label></th>
						<td>
							<input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr( $batch_size ); ?>" min="50" max="2000" class="small-text" />
							<p class="description"><?php esc_html_e( 'Number of records to process per batch during CSV imports.', 'psp-territorial' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="psp_territorial_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'psp-territorial' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: Save (create or update) an item.
	 */
	public function ajax_save_item() {
		$this->verify_ajax_nonce();
		$this->check_capability();

		$item_id   = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;
		$item_type = isset( $_POST['item_type'] ) ? sanitize_key( $_POST['item_type'] ) : '';
		$name      = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent_id = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : null;
		$desc      = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		$type = Helpers::validate_type( $item_type );
		if ( ! $type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid type.', 'psp-territorial' ) ) );
		}

		if ( '' === $name ) {
			wp_send_json_error( array( 'message' => __( 'Name is required.', 'psp-territorial' ) ) );
		}

		if ( $item_id ) {
			// Update.
			$ok = Database::update_item( $item_id, array(
				'name'        => $name,
				'description' => $desc,
				'parent_id'   => $parent_id ?: null,
			) );

			if ( $ok ) {
				wp_send_json_success( array(
					'id'      => $item_id,
					'name'    => $name,
					'action'  => 'updated',
					'message' => __( 'Item updated.', 'psp-territorial' ),
				) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Update failed.', 'psp-territorial' ) ) );
			}
		} else {
			// Insert.
			$id = Database::insert_item( array(
				'name'        => $name,
				'type'        => $type,
				'parent_id'   => $parent_id ?: null,
				'description' => $desc,
			) );

			if ( $id ) {
				wp_send_json_success( array(
					'id'      => $id,
					'name'    => $name,
					'type'    => $type,
					'action'  => 'inserted',
					'message' => __( 'Item created.', 'psp-territorial' ),
				) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Insert failed.', 'psp-territorial' ) ) );
			}
		}
	}

	/**
	 * AJAX: Delete an item (and its children).
	 */
	public function ajax_delete_item() {
		$this->verify_ajax_nonce();
		$this->check_capability();

		$item_id = isset( $_POST['item_id'] ) ? (int) $_POST['item_id'] : 0;

		if ( ! $item_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid ID.', 'psp-territorial' ) ) );
		}

		$ok = Database::delete_item( $item_id );

		if ( $ok ) {
			wp_send_json_success( array(
				'id'      => $item_id,
				'message' => __( 'Item deleted.', 'psp-territorial' ),
			) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Delete failed.', 'psp-territorial' ) ) );
		}
	}

	/**
	 * AJAX: Lazy-load children of a node.
	 */
	public function ajax_get_children() {
		$this->verify_ajax_nonce();

		$parent_id   = isset( $_POST['parent_id'] ) ? (int) $_POST['parent_id'] : 0;
		$parent_type = isset( $_POST['parent_type'] ) ? sanitize_key( $_POST['parent_type'] ) : '';

		$child_type = Helpers::child_type( $parent_type );

		if ( ! $child_type ) {
			wp_send_json_error( array( 'message' => __( 'No children for this type.', 'psp-territorial' ) ) );
		}

		$children = Database::get_items( $child_type, $parent_id );

		ob_start();
		foreach ( $children as $child ) {
			$this->render_tree_node( $child );
		}
		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'       => $html,
			'count'      => count( $children ),
			'childType'  => $child_type,
		) );
	}

	/**
	 * AJAX: Import CSV file.
	 */
	public function ajax_import_csv() {
		$this->verify_ajax_nonce();
		$this->check_capability();

		$is_reset = ! empty( $_POST['reset'] );

		if ( $is_reset ) {
			// Clear all data and re-import default.
			global $wpdb;
			$table = Database::table();
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			Database::flush_type_cache( 'province' );
			Database::import_default_data();

			wp_send_json_success( array(
				'message' => __( 'Data reset to default Panama dataset.', 'psp-territorial' ),
			) );
		}

		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No file uploaded.', 'psp-territorial' ) ) );
		}

		// Validate mime type.
		$file_info = wp_check_filetype( sanitize_file_name( $_FILES['csv_file']['name'] ), array( 'csv' => 'text/csv' ) );
		$allowed   = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		$mime      = isset( $_FILES['csv_file']['type'] ) ? sanitize_mime_type( $_FILES['csv_file']['type'] ) : '';

		if ( ! in_array( $mime, $allowed, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Only CSV files are accepted.', 'psp-territorial' ) ) );
		}

		$tmp_path = sanitize_text_field( $_FILES['csv_file']['tmp_name'] );

		$importer = new CsvImporter();
		$results  = $importer->import_csv( $tmp_path );

		if ( ! empty( $results['errors'] ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', $results['errors'] ),
				'results' => $results,
			) );
		}

		wp_send_json_success( array(
			'inserted' => $results['inserted'],
			'skipped'  => $results['skipped'],
			'message'  => sprintf(
				/* translators: 1: inserted count, 2: skipped count */
				__( 'Import complete. %1$d items inserted, %2$d skipped (already exist).', 'psp-territorial' ),
				$results['inserted'],
				$results['skipped']
			),
		) );
	}

	/**
	 * AJAX: Export data as JSON or CSV.
	 */
	public function ajax_export() {
		$this->verify_ajax_nonce();
		$this->check_capability();

		$format = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : 'json';

		if ( 'json' === $format ) {
			$data = Database::get_hierarchy();
			wp_send_json_success( array(
				'data'     => $data,
				'filename' => 'psp-territorial-export-' . gmdate( 'Y-m-d' ) . '.json',
			) );
		} else {
			// CSV export – flat with 4 columns.
			global $wpdb;
			$table = Database::table();

			$rows = $wpdb->get_results(
				"SELECT * FROM {$table} ORDER BY type, parent_id, name", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				ARRAY_A
			);

			$lines = array( 'Provincia;Distrito;Corregimiento;Comunidad' );

			// Build id -> name lookup.
			$id_map = array();
			foreach ( $rows as $row ) {
				$id_map[ $row['id'] ] = $row;
			}

			foreach ( $rows as $row ) {
				if ( 'community' !== $row['type'] ) {
					continue;
				}
				$corr   = $id_map[ $row['parent_id'] ] ?? null;
				$dist   = $corr ? ( $id_map[ $corr['parent_id'] ] ?? null ) : null;
				$prov   = $dist ? ( $id_map[ $dist['parent_id'] ] ?? null ) : null;

				$lines[] = implode( ';', array(
					$prov ? $prov['name'] : '',
					$dist ? $dist['name'] : '',
					$corr ? $corr['name'] : '',
					$row['name'],
				) );
			}

			wp_send_json_success( array(
				'data'     => implode( "\n", $lines ),
				'filename' => 'psp-territorial-export-' . gmdate( 'Y-m-d' ) . '.csv',
			) );
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Verify the AJAX nonce.
	 */
	private function verify_ajax_nonce() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'psp_territorial_nonce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'psp-territorial' ) ) );
		}
	}

	/**
	 * Die if the current user lacks permission.
	 */
	private function check_capability() {
		$cap = get_option( 'psp_territorial_manage_cap', 'manage_options' );
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'psp-territorial' ) );
		}
	}

	/**
	 * Save settings from POST data.
	 */
	private function save_settings() {
		update_option( 'psp_territorial_enable_api', isset( $_POST['enable_api'] ) ? '1' : '0' );
		update_option( 'psp_territorial_manage_cap', sanitize_key( $_POST['manage_cap'] ?? 'manage_options' ) );
		update_option( 'psp_territorial_cache_duration', (int) ( $_POST['cache_duration'] ?? 3600 ) );
		update_option( 'psp_territorial_batch_size', (int) ( $_POST['batch_size'] ?? 500 ) );

		Database::flush_type_cache( 'province' );
	}
}
