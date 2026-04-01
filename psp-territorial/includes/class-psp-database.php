<?php
/**
 * Database operations for PSP Territorial.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Database
 *
 * Handles all database interactions for the plugin.
 */
class PSP_Database {

	/** @var string Table name (without prefix). */
	const TABLE = 'psp_territories';

	/**
	 * Return the full (prefixed) table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	// -------------------------------------------------------------------------
	// Schema
	// -------------------------------------------------------------------------

	/**
	 * Create (or upgrade) the territories table.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$table      = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(255)        NOT NULL,
			slug       VARCHAR(255)        NOT NULL,
			type       VARCHAR(20)         NOT NULL,
			parent_id  BIGINT(20) UNSIGNED DEFAULT NULL,
			metadata   LONGTEXT            DEFAULT NULL,
			created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type      (type),
			KEY idx_parent_id (parent_id),
			KEY idx_slug      (slug(191))
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'psp_territorial_db_version', PSP_TERRITORIAL_VERSION );
	}

	/**
	 * Drop the territories table (used on uninstall).
	 */
	public static function drop_tables(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	// -------------------------------------------------------------------------
	// Read helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all entities of a given type, optionally filtered by parent.
	 *
	 * @param string        $type     'provincia'|'distrito'|'corregimiento'|'comunidad'.
	 * @param int|null      $parent_id Filter by parent_id.
	 * @param int           $per_page  0 = all.
	 * @param int           $page      1-based page number.
	 * @return array<object>
	 */
	public static function get_by_type( string $type, ?int $parent_id = null, int $per_page = 0, int $page = 1 ): array {
		global $wpdb;
		$table = self::table();

		if ( null !== $parent_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s AND parent_id = %d ORDER BY name ASC", $type, $parent_id );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s ORDER BY name ASC", $type );
		}

		if ( $per_page > 0 ) {
			$offset = ( $page - 1 ) * $per_page;
			$sql   .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql ) ?: [];
	}

	/**
	 * Get a single entity by ID.
	 *
	 * @param int $id
	 * @return object|null
	 */
	public static function get_by_id( int $id ): ?object {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null;
	}

	/**
	 * Count entities of a given type.
	 *
	 * @param string   $type
	 * @param int|null $parent_id
	 * @return int
	 */
	public static function count_by_type( string $type, ?int $parent_id = null ): int {
		global $wpdb;
		$table = self::table();

		if ( null !== $parent_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s AND parent_id = %d", $type, $parent_id ) );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s", $type ) );
	}

	/**
	 * Search entities by name (LIKE).
	 *
	 * @param string $query
	 * @param string $type  Empty = all types.
	 * @return array<object>
	 */
	public static function search( string $query, string $type = '' ): array {
		global $wpdb;
		$table = self::table();
		$like  = '%' . $wpdb->esc_like( $query ) . '%';

		if ( $type ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE name LIKE %s AND type = %s ORDER BY type, name LIMIT 200", $like, $type );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE name LIKE %s ORDER BY type, name LIMIT 200", $like );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql ) ?: [];
	}

	// -------------------------------------------------------------------------
	// Write helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a new entity.
	 *
	 * @param array $data Keys: name, type, parent_id (optional), metadata (optional).
	 * @return int|false Inserted ID or false on error.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$row = [
			'name'      => sanitize_text_field( $data['name'] ),
			'slug'      => self::generate_slug( $data['name'], (int) ( $data['parent_id'] ?? 0 ) ),
			'type'      => sanitize_text_field( $data['type'] ),
			'parent_id' => ! empty( $data['parent_id'] ) ? (int) $data['parent_id'] : null,
			'metadata'  => ! empty( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
		];

		$result = $wpdb->insert( self::table(), $row );
		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing entity.
	 *
	 * @param int   $id
	 * @param array $data
	 * @return bool
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;

		$row = [];

		if ( isset( $data['name'] ) ) {
			$row['name'] = sanitize_text_field( $data['name'] );
			$row['slug'] = self::generate_slug( $data['name'], (int) ( $data['parent_id'] ?? 0 ), $id );
		}

		if ( isset( $data['parent_id'] ) ) {
			$row['parent_id'] = $data['parent_id'] ? (int) $data['parent_id'] : null;
		}

		if ( isset( $data['metadata'] ) ) {
			$row['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$row['updated_at'] = current_time( 'mysql' );

		return (bool) $wpdb->update( self::table(), $row, [ 'id' => $id ] );
	}

	/**
	 * Delete an entity and all its descendants.
	 *
	 * @param int $id
	 * @return int Number of rows deleted.
	 */
	public static function delete( int $id ): int {
		global $wpdb;
		$table   = self::table();
		$deleted = 0;

		// Recursively delete children first.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE parent_id = %d", $id ) );
		foreach ( $children as $child_id ) {
			$deleted += self::delete( (int) $child_id );
		}

		$result = $wpdb->delete( $table, [ 'id' => $id ] );
		return $deleted + ( $result ?: 0 );
	}

	/**
	 * Count all entities.
	 *
	 * @return int
	 */
	public static function count_all(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/**
	 * Get children of a given entity (direct children only).
	 *
	 * @param int    $parent_id
	 * @param string $child_type
	 * @return array<object>
	 */
	public static function get_children( int $parent_id, string $child_type ): array {
		return self::get_by_type( $child_type, $parent_id );
	}

	// -------------------------------------------------------------------------
	// Utility
	// -------------------------------------------------------------------------

	/**
	 * Generate a unique slug for an entity.
	 *
	 * @param string $name
	 * @param int    $parent_id
	 * @param int    $exclude_id ID to exclude when checking uniqueness (for updates).
	 * @return string
	 */
	public static function generate_slug( string $name, int $parent_id = 0, int $exclude_id = 0 ): string {
		global $wpdb;
		$table    = self::table();
		$base     = sanitize_title( $name );
		$slug     = $base;
		$counter  = 1;

		while ( true ) {
			if ( $exclude_id > 0 ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND parent_id = %d AND id != %d LIMIT 1", $slug, $parent_id, $exclude_id ) );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND parent_id = %d LIMIT 1", $slug, $parent_id ) );
			}

			if ( ! $exists ) {
				break;
			}
			$slug = $base . '-' . $counter;
			++$counter;
		}

		return $slug;
	}

	/**
	 * Check if the territories table is empty.
	 *
	 * @return bool
	 */
	public static function is_empty(): bool {
		return self::count_all() === 0;
	}
}
