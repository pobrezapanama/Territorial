<?php
/**
 * Database Handler – PSP Territorial V2
 *
 * Table: {prefix}psp_territorial_v2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_Database
 *
 * All DB operations for the v2 territorial data.
 */
class PSPV2_Database {

	/** Primary table name (without prefix). */
	const TABLE = 'psp_territorial_v2';

	/** Valid territory types in hierarchy order. */
	const TYPES = array( 'province', 'district', 'corregimiento', 'community' );

	/** Valid parent type for each type. */
	const PARENT_TYPE = array(
		'district'      => 'province',
		'corregimiento' => 'district',
		'community'     => 'corregimiento',
	);

	/**
	 * Get full table name (with WP prefix).
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create plugin tables via dbDelta.
	 */
	public function create_tables() {
		global $wpdb;

		$table      = $this->get_table_name();
		$charset_cs = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id         BIGINT UNSIGNED NOT NULL,
			name       VARCHAR(255)    NOT NULL,
			slug       VARCHAR(255)    NOT NULL DEFAULT '',
			code       VARCHAR(50)     NOT NULL DEFAULT '',
			type       VARCHAR(20)     NOT NULL,
			parent_id  BIGINT UNSIGNED          DEFAULT NULL,
			level      TINYINT UNSIGNED NOT NULL DEFAULT 1,
			is_active  TINYINT(1)       NOT NULL DEFAULT 1,
			created_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_slug      (slug(191)),
			KEY idx_code      (code(50)),
			KEY idx_type      (type),
			KEY idx_parent_id (parent_id),
			KEY idx_level     (level),
			KEY idx_is_active (is_active)
		) {$charset_cs};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'pspv2_db_version', PSPV2_VERSION );
	}

	/**
	 * Check whether the table exists.
	 *
	 * @return bool
	 */
	public function table_exists() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
	}

	/**
	 * Drop the plugin table.
	 */
	public function drop_tables() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		delete_option( 'pspv2_db_version' );
	}

	/**
	 * Truncate the table (keep structure).
	 */
	public function truncate() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Count all items (optionally filtered).
	 *
	 * @param array $args Optional filter args (type, is_active).
	 * @return int
	 */
	public function count_items( $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();
		$where = $this->build_where( $args );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}{$where}" );
	}

	/**
	 * Insert a single item (explicit id, no autoincrement).
	 *
	 * @param array $data Associative array of column => value.
	 * @return int|false Inserted row count or false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$columns = implode( ', ', array_map( function( $c ) { return "`{$c}`"; }, array_keys( $data ) ) );
		$formats = implode( ', ', array_fill( 0, count( $data ), '%s' ) );
		$values  = array_values( $data );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$sql = $wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			"INSERT IGNORE INTO {$table} ({$columns}) VALUES ({$formats})",
			$values
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->query( $sql );
	}

	/**
	 * Get items with optional filters/pagination.
	 *
	 * @param array $args {
	 *   type, parent_id, level, search, is_active, limit, offset, orderby, order
	 * }
	 * @return array
	 */
	public function get_items( array $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'type'      => '',
			'parent_id' => '',
			'level'     => '',
			'search'    => '',
			'is_active' => '',
			'limit'     => 50,
			'offset'    => 0,
			'orderby'   => 'id',
			'order'     => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$conditions = array( '1=1' );
		$bindings   = array();

		if ( ! empty( $args['type'] ) ) {
			$conditions[] = 'type = %s';
			$bindings[]   = $args['type'];
		}
		if ( '' !== $args['parent_id'] && null !== $args['parent_id'] ) {
			if ( 'null' === $args['parent_id'] ) {
				$conditions[] = 'parent_id IS NULL';
			} else {
				$conditions[] = 'parent_id = %d';
				$bindings[]   = (int) $args['parent_id'];
			}
		}
		if ( ! empty( $args['level'] ) ) {
			$conditions[] = 'level = %d';
			$bindings[]   = (int) $args['level'];
		}
		if ( ! empty( $args['search'] ) ) {
			$conditions[] = '(name LIKE %s OR slug LIKE %s OR code LIKE %s)';
			$like         = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$bindings[]   = $like;
			$bindings[]   = $like;
			$bindings[]   = $like;
		}
		if ( '' !== $args['is_active'] ) {
			$conditions[] = 'is_active = %d';
			$bindings[]   = (int) $args['is_active'];
		}

		$allowed_order  = array( 'ASC', 'DESC' );
		$allowed_orderby = array( 'id', 'name', 'slug', 'code', 'type', 'level', 'parent_id' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'id';
		$order   = in_array( strtoupper( $args['order'] ), $allowed_order, true ) ? strtoupper( $args['order'] ) : 'ASC';

		$limit  = max( 1, (int) $args['limit'] );
		$offset = max( 0, (int) $args['offset'] );

		$where = 'WHERE ' . implode( ' AND ', $conditions );
		$sql   = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$bindings[] = $limit;
		$bindings[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $bindings ) );
	}

	/**
	 * Count items matching given args (ignoring limit/offset).
	 *
	 * @param array $args Same as get_items().
	 * @return int
	 */
	public function count_filtered( array $args = array() ) {
		$args['limit']  = PHP_INT_MAX;
		$args['offset'] = 0;
		return count( $this->get_items( $args ) );
	}

	/**
	 * Get a single item by id.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) );
	}

	/**
	 * Check if an id already exists.
	 *
	 * @param int $id
	 * @return bool
	 */
	public function id_exists( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d LIMIT 1", (int) $id ) );
	}

	/**
	 * Get a type of a given id.
	 *
	 * @param int $id
	 * @return string|null
	 */
	public function get_type( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( $wpdb->prepare( "SELECT type FROM {$table} WHERE id = %d LIMIT 1", (int) $id ) );
	}

	/**
	 * Get ancestor chain for an id (ascending order: self → province).
	 *
	 * @param int $id Starting id.
	 * @return array Ordered array of objects.
	 */
	public function get_path( $id ) {
		$path = array();
		$item = $this->get_by_id( $id );
		$seen = array();

		while ( $item && ! in_array( (int) $item->id, $seen, true ) ) {
			$path[] = $item;
			$seen[] = (int) $item->id;
			if ( empty( $item->parent_id ) ) {
				break;
			}
			$item = $this->get_by_id( (int) $item->parent_id );
		}

		return array_reverse( $path );
	}

	/**
	 * Get stats (count per type).
	 *
	 * @return array
	 */
	public function get_stats() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT type, COUNT(*) AS cnt FROM {$table} WHERE is_active = 1 GROUP BY type ORDER BY FIELD(type,'province','district','corregimiento','community')" );
		$stats = array();
		foreach ( $rows as $row ) {
			$stats[ $row->type ] = (int) $row->cnt;
		}
		return $stats;
	}

	/**
	 * Get orphaned items (parent_id points to non-existent id).
	 *
	 * @return array
	 */
	public function get_orphans() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT t.id, t.name, t.type, t.parent_id
			 FROM {$table} t
			 WHERE t.parent_id IS NOT NULL
			   AND NOT EXISTS (SELECT 1 FROM {$table} p WHERE p.id = t.parent_id)"
		);
	}

	/**
	 * Get items with invalid parent type (e.g. community → community parent).
	 *
	 * @return array
	 */
	public function get_invalid_parents() {
		global $wpdb;
		$table  = $this->get_table_name();
		$issues = array();

		foreach ( self::PARENT_TYPE as $child_type => $expected_parent_type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"SELECT c.id, c.name, c.type, c.parent_id, p.type AS parent_type
					 FROM {$table} c
					 JOIN {$table} p ON p.id = c.parent_id
					 WHERE c.type = %s
					   AND p.type <> %s",
					$child_type,
					$expected_parent_type
				)
			);
			$issues = array_merge( $issues, $rows );
		}

		return $issues;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a WHERE string from simple args (type, is_active).
	 *
	 * @param array $args
	 * @return string
	 */
	private function build_where( array $args ) {
		global $wpdb;
		$conditions = array();
		if ( ! empty( $args['type'] ) ) {
			$conditions[] = $wpdb->prepare( 'type = %s', $args['type'] );
		}
		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$conditions[] = $wpdb->prepare( 'is_active = %d', (int) $args['is_active'] );
		}
		return $conditions ? ' WHERE ' . implode( ' AND ', $conditions ) : '';
	}
}
