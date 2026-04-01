<?php
/**
 * Database operations for PSP Territorial.
 *
 * @package PSPTerritorial
 */

namespace PSPTerritorial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all custom database table creation and queries.
 */
class Database {

	/**
	 * Table name (without prefix).
	 */
	const TABLE = 'psp_territorial_items';

	/**
	 * DB version option key.
	 */
	const DB_VERSION_OPTION = 'psp_territorial_db_version';

	/**
	 * Current DB schema version.
	 */
	const DB_VERSION = '1.0';

	/**
	 * Singleton instance.
	 *
	 * @var Database|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return Database
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – checks if upgrade is needed.
	 */
	private function __construct() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Return the full table name with WP prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create (or upgrade) the database table.
	 * Called on plugin activation and on version mismatch.
	 */
	public static function install() {
		global $wpdb;

		$table      = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id           BIGINT(20)   UNSIGNED NOT NULL AUTO_INCREMENT,
			name         VARCHAR(255) NOT NULL DEFAULT '',
			slug         VARCHAR(255) NOT NULL DEFAULT '',
			type         ENUM('province','district','corregimiento','community') NOT NULL,
			parent_id    BIGINT(20)   UNSIGNED NULL DEFAULT NULL,
			description  TEXT         NULL,
			created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY type     (type),
			KEY parent_id(parent_id),
			KEY slug     (slug(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		// Import default Panama data if table is empty.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			self::import_default_data();
		}
	}

	/**
	 * Import the bundled Panama JSON data.
	 */
	public static function import_default_data() {
		$json_file = PSP_TERRITORIAL_DIR . 'data/panama_data.json';
		if ( ! file_exists( $json_file ) ) {
			return;
		}

		$raw = file_get_contents( $json_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $raw ) {
			return;
		}

		$provinces = json_decode( $raw, true );
		if ( ! is_array( $provinces ) ) {
			return;
		}

		$importer = new Importer\CsvImporter();
		$importer->import_json( $provinces );
	}

	/**
	 * Plugin deactivation hook – nothing to clean yet.
	 */
	public static function deactivate() {
		// Flush rewrite rules so REST routes are removed cleanly.
		flush_rewrite_rules();
	}

	// -------------------------------------------------------------------------
	// CRUD helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all items of a given type.
	 *
	 * @param string   $type      'province'|'district'|'corregimiento'|'community'.
	 * @param int|null $parent_id Filter by parent.
	 * @return array
	 */
	public static function get_items( $type, $parent_id = null ) {
		global $wpdb;
		$table = self::table();

		$allowed_types = array( 'province', 'district', 'corregimiento', 'community' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			return array();
		}

		$cache_key = "psp_items_{$type}_" . ( null === $parent_id ? 'all' : (int) $parent_id );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( null !== $parent_id ) {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE type = %s AND parent_id = %d ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type,
					(int) $parent_id
				),
				ARRAY_A
			);
		} else {
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE type = %s ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$type
				),
				ARRAY_A
			);
		}

		$cache_duration = (int) get_option( 'psp_territorial_cache_duration', 3600 );
		set_transient( $cache_key, $results, $cache_duration );

		return $results;
	}

	/**
	 * Get a single item by ID.
	 *
	 * @param int $id Item ID.
	 * @return array|null
	 */
	public static function get_item( $id ) {
		global $wpdb;
		$table = self::table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);
	}

	/**
	 * Insert a new item.
	 *
	 * @param array $data Associative array with keys: name, type, parent_id (opt), description (opt).
	 * @return int|false Inserted ID or false on failure.
	 */
	public static function insert_item( array $data ) {
		global $wpdb;
		$table = self::table();

		$insert = array(
			'name'        => sanitize_text_field( $data['name'] ),
			'slug'        => Helpers::slugify( $data['name'] ),
			'type'        => $data['type'],
			'parent_id'   => isset( $data['parent_id'] ) && $data['parent_id'] ? (int) $data['parent_id'] : null,
			'description' => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
		);

		/**
		 * Filter item data before saving.
		 *
		 * @param array $insert Data to be inserted.
		 * @param array $data   Original data.
		 */
		$insert = apply_filters( 'psp_territorial_item_data', $insert, $data );

		do_action( 'psp_territorial_before_save', $insert, null );

		$result = $wpdb->insert( $table, $insert );
		if ( false === $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		self::flush_type_cache( $insert['type'] );

		/**
		 * Fires after a territorial item is created.
		 *
		 * @param int   $id     New item ID.
		 * @param array $insert Inserted data.
		 */
		do_action( 'psp_territorial_item_created', $id, $insert );

		return $id;
	}

	/**
	 * Update an existing item.
	 *
	 * @param int   $id   Item ID.
	 * @param array $data Data to update.
	 * @return bool
	 */
	public static function update_item( $id, array $data ) {
		global $wpdb;
		$table = self::table();

		$update = array();
		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$update['slug'] = Helpers::slugify( $data['name'] );
		}
		if ( isset( $data['description'] ) ) {
			$update['description'] = sanitize_textarea_field( $data['description'] );
		}
		if ( array_key_exists( 'parent_id', $data ) ) {
			$update['parent_id'] = $data['parent_id'] ? (int) $data['parent_id'] : null;
		}

		/** This filter is documented in includes/Database.php */
		$update = apply_filters( 'psp_territorial_item_data', $update, $data );

		do_action( 'psp_territorial_before_save', $update, $id );

		$result = $wpdb->update( $table, $update, array( 'id' => (int) $id ) );

		$item = self::get_item( $id );
		if ( $item ) {
			self::flush_type_cache( $item['type'] );
		}

		/**
		 * Fires after a territorial item is updated.
		 *
		 * @param int   $id     Item ID.
		 * @param array $update Updated data.
		 */
		do_action( 'psp_territorial_item_updated', $id, $update );

		return false !== $result;
	}

	/**
	 * Delete an item and all its descendants.
	 *
	 * @param int $id Item ID.
	 * @return bool
	 */
	public static function delete_item( $id ) {
		global $wpdb;
		$table = self::table();

		$item = self::get_item( $id );
		if ( ! $item ) {
			return false;
		}

		// Recursively delete children.
		$children = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", (int) $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		foreach ( $children as $child_id ) {
			self::delete_item( (int) $child_id );
		}

		$result = $wpdb->delete( $table, array( 'id' => (int) $id ) );

		self::flush_type_cache( $item['type'] );

		/**
		 * Fires after a territorial item is deleted.
		 *
		 * @param int   $id   Deleted item ID.
		 * @param array $item Deleted item data.
		 */
		do_action( 'psp_territorial_item_deleted', $id, $item );

		return false !== $result;
	}

	/**
	 * Search items by name.
	 *
	 * @param string      $query  Search string.
	 * @param string|null $type   Optional type filter.
	 * @return array
	 */
	public static function search_items( $query, $type = null ) {
		global $wpdb;
		$table = self::table();

		$like = '%' . $wpdb->esc_like( sanitize_text_field( $query ) ) . '%';

		/**
		 * Filter the search query args.
		 *
		 * @param array $args Search arguments.
		 */
		$args = apply_filters( 'psp_territorial_query_args', array( 'query' => $query, 'type' => $type ) );

		if ( $args['type'] ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE type = %s AND name LIKE %s ORDER BY type, name LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$args['type'],
					$like
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE name LIKE %s ORDER BY type, name LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$like
			),
			ARRAY_A
		);
	}

	/**
	 * Build a complete nested hierarchy from the database.
	 *
	 * @return array
	 */
	public static function get_hierarchy() {
		$cache_key = 'psp_territorial_hierarchy';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$provinces = self::get_items( 'province' );
		foreach ( $provinces as &$province ) {
			$province['districts'] = self::get_items( 'district', $province['id'] );
			foreach ( $province['districts'] as &$district ) {
				$district['corregimientos'] = self::get_items( 'corregimiento', $district['id'] );
				foreach ( $district['corregimientos'] as &$corregimiento ) {
					$corregimiento['communities'] = self::get_items( 'community', $corregimiento['id'] );
				}
			}
		}

		$cache_duration = (int) get_option( 'psp_territorial_cache_duration', 3600 );
		set_transient( $cache_key, $provinces, $cache_duration );

		return $provinces;
	}

	/**
	 * Flush all cached transients for a given item type.
	 *
	 * @param string $type Item type.
	 */
	public static function flush_type_cache( $type ) {
		global $wpdb;

		// Delete transients matching psp_items_*.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'_transient_psp_items_%',
				'_transient_psp_territorial_hierarchy'
			)
		);
	}

	/**
	 * Get item counts grouped by type.
	 *
	 * @return array
	 */
	public static function get_counts() {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			"SELECT type, COUNT(*) as total FROM {$table} GROUP BY type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		$counts = array(
			'province'      => 0,
			'district'      => 0,
			'corregimiento' => 0,
			'community'     => 0,
		);

		foreach ( $rows as $row ) {
			$counts[ $row['type'] ] = (int) $row['total'];
		}

		return $counts;
	}
}
