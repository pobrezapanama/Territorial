<?php
/**
 * Query Helper Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Query
 *
 * Public query interface for other plugins to consume territorial data.
 */
class PSP_Territorial_Query {

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database|null
	 */
	private static $db = null;

	/**
	 * Get (or create) the shared database instance.
	 *
	 * @return PSP_Territorial_Database
	 */
	private static function db() {
		if ( null === self::$db ) {
			self::$db = new PSP_Territorial_Database();
		}
		return self::$db;
	}

	// -------------------------------------------------------------------------
	// Province helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all active provinces, ordered by name.
	 *
	 * @return array Array of province objects.
	 */
	public static function get_provinces() {
		return self::db()->get_by_type( 'province', array( 'limit' => 500 ) );
	}

	// -------------------------------------------------------------------------
	// Generic query by type
	// -------------------------------------------------------------------------

	/**
	 * Get territories by type.
	 *
	 * @param string $type Territory type ('province'|'district'|'corregimiento'|'community').
	 * @param array  $args Optional query args (limit, offset, search, order_by, order).
	 * @return array
	 */
	public static function get_by_type( $type, $args = array() ) {
		return self::db()->get_by_type( $type, $args );
	}

	// -------------------------------------------------------------------------
	// Hierarchical helpers
	// -------------------------------------------------------------------------

	/**
	 * Get direct children of a territory.
	 *
	 * @param string $parent_type Type of the parent (unused – kept for API compatibility).
	 * @param int    $parent_id   Parent territory ID.
	 * @param array  $args        Optional query args.
	 * @return array
	 */
	public static function get_children( $parent_type, $parent_id, $args = array() ) {
		return self::db()->get_by_parent( $parent_id, $args );
	}

	/**
	 * Get the full ancestry path from a territory up to its province.
	 *
	 * @param int $entity_id Territory ID.
	 * @return array Array of territory objects from root (province) to the given entity.
	 */
	public static function get_full_path( $entity_id ) {
		return self::db()->get_path( $entity_id );
	}

	// -------------------------------------------------------------------------
	// Single-entity lookups
	// -------------------------------------------------------------------------

	/**
	 * Get a territory by its ID.
	 *
	 * @param int $id Territory ID.
	 * @return object|null
	 */
	public static function get_by_id( $id ) {
		return self::db()->get_by_id( $id );
	}

	/**
	 * Get a territory by its slug.
	 *
	 * @param string $slug Territory slug.
	 * @return object|null
	 */
	public static function get_by_slug( $slug ) {
		return self::db()->get_by_slug( $slug );
	}

	// -------------------------------------------------------------------------
	// Existence checks
	// -------------------------------------------------------------------------

	/**
	 * Check whether a territory exists by type and slug.
	 *
	 * @param string $type Territory type.
	 * @param string $slug Territory slug.
	 * @return bool
	 */
	public static function exists( $type, $slug ) {
		global $wpdb;
		$db    = self::db();
		$table = $db->get_table_name();
		$count = (int) $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s AND slug = %s AND is_active = 1", $type, sanitize_title( $slug ) )
		);
		return $count > 0;
	}

	// -------------------------------------------------------------------------
	// Meta helpers
	// -------------------------------------------------------------------------

	/**
	 * Get a meta value for a territory.
	 *
	 * @param int    $territory_id Territory ID.
	 * @param string $meta_key     Meta key.
	 * @param bool   $single       Single value or array.
	 * @return mixed
	 */
	public static function get_meta( $territory_id, $meta_key = '', $single = true ) {
		return self::db()->get_meta( $territory_id, $meta_key, $single );
	}

	/**
	 * Update a meta value for a territory.
	 *
	 * @param int    $territory_id Territory ID.
	 * @param string $meta_key     Meta key.
	 * @param mixed  $meta_value   Meta value.
	 * @return bool
	 */
	public static function update_meta( $territory_id, $meta_key, $meta_value ) {
		return self::db()->update_meta( $territory_id, $meta_key, $meta_value );
	}
}
