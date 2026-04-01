<?php
/**
 * Database Handler Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Database
 *
 * Handles all database operations for the territorial data.
 */
class PSP_Territorial_Database {

	/**
	 * Main territories table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_TERRITORIES = 'psp_territories';

	/**
	 * Territory meta table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_META = 'psp_territory_meta';

	/**
	 * Get territories table name with WordPress prefix.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_TERRITORIES;
	}

	/**
	 * Get territory meta table name with WordPress prefix.
	 *
	 * @return string
	 */
	public function get_meta_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_META;
	}

	/**
	 * Create database tables.
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table           = $this->get_table_name();
		$meta_table      = $this->get_meta_table_name();

		$sql = "CREATE TABLE {$table} (
			id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			slug VARCHAR(255) NOT NULL,
			code VARCHAR(50) NOT NULL DEFAULT '',
			type ENUM('province','district','corregimiento','community') NOT NULL,
			parent_id INT UNSIGNED NULL DEFAULT NULL,
			level TINYINT UNSIGNED NOT NULL DEFAULT 1,
			metadata JSON NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_parent_id (parent_id),
			KEY idx_type (type),
			KEY idx_slug (slug(191)),
			KEY idx_level (level),
			KEY idx_is_active (is_active)
		) {$charset_collate};";

		$sql_meta = "CREATE TABLE {$meta_table} (
			meta_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
			territory_id INT UNSIGNED NOT NULL,
			meta_key VARCHAR(255) NOT NULL,
			meta_value LONGTEXT NULL,
			PRIMARY KEY (meta_id),
			KEY idx_territory_id (territory_id),
			KEY idx_meta_key (meta_key(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $sql_meta );

		update_option( 'psp_territorial_db_version', PSP_TERRITORIAL_VERSION );
	}

	/**
	 * Drop database tables.
	 */
	public function drop_tables() {
		global $wpdb;
		$table      = $this->get_table_name();
		$meta_table = $this->get_meta_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$meta_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	/**
	 * Check if tables exist and are up to date.
	 *
	 * @return bool
	 */
	public function tables_exist() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" );
		return $result === $table;
	}

	/**
	 * Insert a territory record.
	 *
	 * @param array $data Territory data.
	 * @return int|false Inserted ID or false on failure.
	 */
	public function insert( array $data ) {
		global $wpdb;

		$data = apply_filters( 'psp_territorial_entity_data', $data );

		$defaults = array(
			'name'      => '',
			'slug'      => '',
			'code'      => '',
			'type'      => 'province',
			'parent_id' => null,
			'level'     => 1,
			'metadata'  => null,
			'is_active' => 1,
		);

		$data = wp_parse_args( $data, $defaults );

		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$result = $wpdb->insert(
			$this->get_table_name(),
			array(
				'name'      => sanitize_text_field( $data['name'] ),
				'slug'      => sanitize_title( $data['slug'] ),
				'code'      => sanitize_text_field( $data['code'] ),
				'type'      => $data['type'],
				'parent_id' => $data['parent_id'] ? absint( $data['parent_id'] ) : null,
				'level'     => absint( $data['level'] ),
				'metadata'  => $data['metadata'],
				'is_active' => (int) $data['is_active'],
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		$id = $wpdb->insert_id;
		do_action( 'psp_territorial_entity_created', $this->get_by_id( $id ) );
		return $id;
	}

	/**
	 * Update a territory record.
	 *
	 * @param int   $id   Territory ID.
	 * @param array $data Territory data.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$old = $this->get_by_id( $id );
		if ( ! $old ) {
			return false;
		}

		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$update = array();
		$format = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}
		if ( isset( $data['slug'] ) ) {
			$update['slug'] = sanitize_title( $data['slug'] );
			$format[]       = '%s';
		}
		if ( isset( $data['code'] ) ) {
			$update['code'] = sanitize_text_field( $data['code'] );
			$format[]       = '%s';
		}
		if ( isset( $data['type'] ) ) {
			$update['type'] = $data['type'];
			$format[]       = '%s';
		}
		if ( array_key_exists( 'parent_id', $data ) ) {
			$update['parent_id'] = $data['parent_id'] ? absint( $data['parent_id'] ) : null;
			$format[]            = '%d';
		}
		if ( isset( $data['level'] ) ) {
			$update['level'] = absint( $data['level'] );
			$format[]        = '%d';
		}
		if ( isset( $data['metadata'] ) ) {
			$update['metadata'] = $data['metadata'];
			$format[]           = '%s';
		}
		if ( isset( $data['is_active'] ) ) {
			$update['is_active'] = (int) $data['is_active'];
			$format[]            = '%d';
		}

		if ( empty( $update ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			$update,
			array( 'id' => absint( $id ) ),
			$format,
			array( '%d' )
		);

		$new = $this->get_by_id( $id );
		do_action( 'psp_territorial_entity_updated', $old, $new );

		return false !== $result;
	}

	/**
	 * Delete a territory record (hard delete).
	 *
	 * @param int  $id           Territory ID.
	 * @param bool $cascade      Whether to delete children.
	 * @return bool
	 */
	public function delete( $id, $cascade = false ) {
		global $wpdb;

		$entity = $this->get_by_id( $id );
		if ( ! $entity ) {
			return false;
		}

		if ( $cascade ) {
			$this->delete_children( $id );
		}

		// Delete meta.
		$wpdb->delete( $this->get_meta_table_name(), array( 'territory_id' => absint( $id ) ), array( '%d' ) );

		$result = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => absint( $id ) ),
			array( '%d' )
		);

		if ( false !== $result ) {
			do_action( 'psp_territorial_entity_deleted', $entity );
			return true;
		}

		return false;
	}

	/**
	 * Recursively delete all children of a territory.
	 *
	 * @param int $parent_id Parent territory ID.
	 */
	private function delete_children( $parent_id ) {
		$children = $this->get_by_parent( $parent_id );
		foreach ( $children as $child ) {
			$this->delete( $child->id, true );
		}
	}

	/**
	 * Get a territory by ID.
	 *
	 * @param int $id Territory ID.
	 * @return object|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Get a territory by slug.
	 *
	 * @param string $slug Territory slug.
	 * @return object|null
	 */
	public function get_by_slug( $slug ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s AND is_active = 1 LIMIT 1", sanitize_title( $slug ) ) );
	}

	/**
	 * Get territories by type.
	 *
	 * @param string $type     Territory type.
	 * @param array  $args     Query arguments.
	 * @return array
	 */
	public function get_by_type( $type, $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'limit'      => 500,
			'offset'     => 0,
			'search'     => '',
			'is_active'  => 1,
			'order_by'   => 'name',
			'order'      => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'type = %s', $type ) );
		if ( null !== $args['is_active'] ) {
			$where[] = $wpdb->prepare( 'is_active = %d', (int) $args['is_active'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'name ASC';
		$limit        = absint( $args['limit'] );
		$offset       = absint( $args['offset'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT {$limit} OFFSET {$offset}" );
	}

	/**
	 * Get territories by parent ID.
	 *
	 * @param int   $parent_id Parent ID.
	 * @param array $args      Query arguments.
	 * @return array
	 */
	public function get_by_parent( $parent_id, $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'limit'    => 500,
			'offset'   => 0,
			'search'   => '',
			'order_by' => 'name',
			'order'    => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where = array( $wpdb->prepare( 'parent_id = %d', absint( $parent_id ) ) );

		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'name ASC';
		$limit        = absint( $args['limit'] );
		$offset       = absint( $args['offset'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT {$limit} OFFSET {$offset}" );
	}

	/**
	 * Get all territories with flexible filtering.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public function get_territories( $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'type'      => '',
			'parent_id' => '',
			'search'    => '',
			'limit'     => 50,
			'offset'    => 0,
			'is_active' => 1,
			'order_by'  => 'name',
			'order'     => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'psp_territorial_api_query', $args );

		$where = array( '1=1' );

		if ( ! empty( $args['type'] ) ) {
			$where[] = $wpdb->prepare( 'type = %s', $args['type'] );
		}
		if ( '' !== $args['parent_id'] && null !== $args['parent_id'] ) {
			$where[] = $wpdb->prepare( 'parent_id = %d', absint( $args['parent_id'] ) );
		}
		if ( null !== $args['is_active'] ) {
			$where[] = $wpdb->prepare( 'is_active = %d', (int) $args['is_active'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_clause = implode( ' AND ', $where );
		$order_by     = sanitize_sql_orderby( $args['order_by'] . ' ' . $args['order'] ) ?: 'name ASC';
		$limit        = absint( $args['limit'] );
		$offset       = absint( $args['offset'] );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$order_by} LIMIT {$limit} OFFSET {$offset}" );
	}

	/**
	 * Count territories with filtering.
	 *
	 * @param array $args Query arguments.
	 * @return int
	 */
	public function count_territories( $args = array() ) {
		global $wpdb;
		$table = $this->get_table_name();

		$defaults = array(
			'type'      => '',
			'parent_id' => '',
			'search'    => '',
			'is_active' => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );

		if ( ! empty( $args['type'] ) ) {
			$where[] = $wpdb->prepare( 'type = %s', $args['type'] );
		}
		if ( '' !== $args['parent_id'] && null !== $args['parent_id'] ) {
			$where[] = $wpdb->prepare( 'parent_id = %d', absint( $args['parent_id'] ) );
		}
		if ( null !== $args['is_active'] ) {
			$where[] = $wpdb->prepare( 'is_active = %d', (int) $args['is_active'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[] = $wpdb->prepare( 'name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		$where_clause = implode( ' AND ', $where );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
	}

	/**
	 * Get full ancestry path for a territory.
	 *
	 * @param int $id Territory ID.
	 * @return array Array of territory objects from root to leaf.
	 */
	public function get_path( $id ) {
		$path   = array();
		$entity = $this->get_by_id( $id );

		while ( $entity ) {
			array_unshift( $path, $entity );
			if ( ! $entity->parent_id ) {
				break;
			}
			$entity = $this->get_by_id( $entity->parent_id );
		}

		return $path;
	}

	/**
	 * Count direct children of a territory.
	 *
	 * @param int $parent_id Parent ID.
	 * @return int
	 */
	public function count_children( $parent_id ) {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE parent_id = %d", absint( $parent_id ) ) );
	}

	/**
	 * Check whether the imported data has orphaned records (parent_id references
	 * a row that does not exist). This is the hallmark of data imported with the
	 * old broken importer that omitted the id column from batch INSERT statements.
	 *
	 * @return bool True if orphaned records are found.
	 */
	public function has_orphaned_records() {
		global $wpdb;
		$table = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} t
			 WHERE t.parent_id IS NOT NULL
			   AND NOT EXISTS (
			       SELECT 1 FROM {$table} p WHERE p.id = t.parent_id
			   )"
		);
		return $count > 0;
	}

	/**
	 * Truncate all territory data.
	 */
	public function truncate() {
		global $wpdb;
		$table      = $this->get_table_name();
		$meta_table = $this->get_meta_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$meta_table}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	// -------------------------------------------------------------------------
	// Meta functions
	// -------------------------------------------------------------------------

	/**
	 * Get a territory meta value.
	 *
	 * @param int    $territory_id Territory ID.
	 * @param string $meta_key     Meta key.
	 * @param bool   $single       Whether to return a single value.
	 * @return mixed
	 */
	public function get_meta( $territory_id, $meta_key = '', $single = true ) {
		global $wpdb;
		$meta_table = $this->get_meta_table_name();

		if ( $meta_key ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value FROM {$meta_table} WHERE territory_id = %d AND meta_key = %s", absint( $territory_id ), $meta_key ) );
			if ( $single ) {
				return isset( $results[0] ) ? maybe_unserialize( $results[0]->meta_value ) : null;
			}
			return array_map( function( $r ) { return maybe_unserialize( $r->meta_value ); }, $results );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$meta_table} WHERE territory_id = %d", absint( $territory_id ) ) );
		$meta    = array();
		foreach ( $results as $row ) {
			$meta[ $row->meta_key ] = maybe_unserialize( $row->meta_value );
		}
		return $meta;
	}

	/**
	 * Update or add a territory meta value.
	 *
	 * @param int    $territory_id Territory ID.
	 * @param string $meta_key     Meta key.
	 * @param mixed  $meta_value   Meta value.
	 * @return bool
	 */
	public function update_meta( $territory_id, $meta_key, $meta_value ) {
		global $wpdb;
		$meta_table = $this->get_meta_table_name();

		$existing = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT meta_id FROM {$meta_table} WHERE territory_id = %d AND meta_key = %s LIMIT 1", absint( $territory_id ), $meta_key )
		);

		$serialized = maybe_serialize( $meta_value );

		if ( $existing ) {
			return false !== $wpdb->update(
				$meta_table,
				array( 'meta_value' => $serialized ),
				array( 'territory_id' => absint( $territory_id ), 'meta_key' => $meta_key ),
				array( '%s' ),
				array( '%d', '%s' )
			);
		}

		return false !== $wpdb->insert(
			$meta_table,
			array(
				'territory_id' => absint( $territory_id ),
				'meta_key'     => $meta_key,
				'meta_value'   => $serialized,
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * Delete a territory meta value.
	 *
	 * @param int    $territory_id Territory ID.
	 * @param string $meta_key     Meta key.
	 * @return bool
	 */
	public function delete_meta( $territory_id, $meta_key ) {
		global $wpdb;
		return false !== $wpdb->delete(
			$this->get_meta_table_name(),
			array(
				'territory_id' => absint( $territory_id ),
				'meta_key'     => $meta_key,
			),
			array( '%d', '%s' )
		);
	}

	/**
	 * Bulk insert territories for performance during import.
	 *
	 * @param array $rows Array of territory data arrays.
	 * @return int Number of rows inserted.
	 */
	public function bulk_insert( array $rows ) {
		global $wpdb;
		$table   = $this->get_table_name();
		$count   = 0;
		$values  = array();
		$placeholders = array();

		foreach ( $rows as $data ) {
			$name      = sanitize_text_field( $data['name'] ?? '' );
			$slug      = sanitize_title( $data['slug'] ?? '' );
			$code      = sanitize_text_field( $data['code'] ?? '' );
			$type      = $data['type'] ?? 'province';
			$parent_id = ! empty( $data['parent_id'] ) ? absint( $data['parent_id'] ) : null;
			$level     = absint( $data['level'] ?? 1 );
			$row_id    = ! empty( $data['id'] ) ? absint( $data['id'] ) : null;
			$is_active = 1;

			if ( null === $row_id && null === $parent_id ) {
				$placeholders[] = '(NULL, %s, %s, %s, %s, NULL, %d, %d)';
				array_push( $values, $name, $slug, $code, $type, $level, $is_active );
			} elseif ( null === $row_id ) {
				$placeholders[] = '(NULL, %s, %s, %s, %s, %d, %d, %d)';
				array_push( $values, $name, $slug, $code, $type, $parent_id, $level, $is_active );
			} elseif ( null === $parent_id ) {
				$placeholders[] = '(%d, %s, %s, %s, %s, NULL, %d, %d)';
				array_push( $values, $row_id, $name, $slug, $code, $type, $level, $is_active );
			} else {
				$placeholders[] = '(%d, %s, %s, %s, %s, %d, %d, %d)';
				array_push( $values, $row_id, $name, $slug, $code, $type, $parent_id, $level, $is_active );
			}

			++$count;

			// Insert in batches of 500.
			if ( $count % 500 === 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (id,name,slug,code,type,parent_id,level,is_active) VALUES " . implode( ',', $placeholders ), $values ) );
				$values       = array();
				$placeholders = array();
			}
		}

		// Insert remaining.
		if ( ! empty( $placeholders ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (id,name,slug,code,type,parent_id,level,is_active) VALUES " . implode( ',', $placeholders ), $values ) );
		}

		return $count;
	}
}
