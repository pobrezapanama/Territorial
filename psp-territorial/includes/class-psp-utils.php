<?php
/**
 * Utility Functions Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Utils
 *
 * Provides static utility methods for the plugin.
 */
class PSP_Territorial_Utils {

	/**
	 * Valid territory types.
	 *
	 * @var array
	 */
	public static $types = array( 'province', 'district', 'corregimiento', 'community' );

	/**
	 * Level map: type => level number.
	 *
	 * @var array
	 */
	public static $levels = array(
		'province'      => 1,
		'district'      => 2,
		'corregimiento' => 3,
		'community'     => 4,
	);

	/**
	 * Generate a URL-friendly slug from a string.
	 *
	 * @param string $text The input text.
	 * @return string
	 */
	public static function generate_slug( $text ) {
		return sanitize_title( $text );
	}

	/**
	 * Generate a unique code for a territory.
	 *
	 * @param string $type  Territory type.
	 * @param int    $index Sequential index.
	 * @return string
	 */
	public static function generate_code( $type, $index ) {
		$prefixes = array(
			'province'      => 'PRV',
			'district'      => 'DIS',
			'corregimiento' => 'COR',
			'community'     => 'COM',
		);
		$prefix = isset( $prefixes[ $type ] ) ? $prefixes[ $type ] : 'ENT';
		return $prefix . '-' . str_pad( $index, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Get the level number for a type.
	 *
	 * @param string $type Territory type.
	 * @return int
	 */
	public static function get_level( $type ) {
		return isset( self::$levels[ $type ] ) ? self::$levels[ $type ] : 1;
	}

	/**
	 * Validate a territory type.
	 *
	 * @param string $type Territory type to validate.
	 * @return bool
	 */
	public static function is_valid_type( $type ) {
		return in_array( $type, self::$types, true );
	}

	/**
	 * Get the expected parent type for a given child type.
	 *
	 * @param string $type Child type.
	 * @return string|null Parent type or null if top-level.
	 */
	public static function get_parent_type( $type ) {
		$parents = array(
			'district'      => 'province',
			'corregimiento' => 'district',
			'community'     => 'corregimiento',
		);
		return isset( $parents[ $type ] ) ? $parents[ $type ] : null;
	}

	/**
	 * Get child type of a given type.
	 *
	 * @param string $type Parent type.
	 * @return string|null Child type or null if leaf.
	 */
	public static function get_child_type( $type ) {
		$children = array(
			'province'      => 'district',
			'district'      => 'corregimiento',
			'corregimiento' => 'community',
		);
		return isset( $children[ $type ] ) ? $children[ $type ] : null;
	}

	/**
	 * Get human-readable type label.
	 *
	 * @param string $type Territory type.
	 * @return string
	 */
	public static function get_type_label( $type ) {
		$labels = array(
			'province'      => __( 'Provincia', 'psp-territorial' ),
			'district'      => __( 'Distrito', 'psp-territorial' ),
			'corregimiento' => __( 'Corregimiento', 'psp-territorial' ),
			'community'     => __( 'Comunidad', 'psp-territorial' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	/**
	 * Format a territory object into an API-ready array.
	 *
	 * @param object $entity     Territory DB row.
	 * @param bool   $with_meta  Include metadata.
	 * @return array
	 */
	public static function format_entity( $entity, $with_meta = false ) {
		$data = array(
			'id'         => (int) $entity->id,
			'name'       => $entity->name,
			'slug'       => $entity->slug,
			'code'       => $entity->code,
			'type'       => $entity->type,
			'type_label' => self::get_type_label( $entity->type ),
			'parent_id'  => $entity->parent_id ? (int) $entity->parent_id : null,
			'level'      => (int) $entity->level,
			'is_active'  => (bool) $entity->is_active,
		);

		if ( ! empty( $entity->metadata ) ) {
			$decoded = json_decode( $entity->metadata, true );
			$data['metadata'] = is_array( $decoded ) ? $decoded : array();
		} else {
			$data['metadata'] = array();
		}

		return apply_filters( 'psp_territorial_response_format', $data );
	}

	/**
	 * Sanitize and validate territory input data.
	 *
	 * @param array $data Raw input data.
	 * @return array|\WP_Error Cleaned data or error.
	 */
	public static function validate_input( $data ) {
		$errors = new WP_Error();

		$name = isset( $data['name'] ) ? trim( sanitize_text_field( $data['name'] ) ) : '';
		if ( empty( $name ) ) {
			$errors->add( 'missing_name', __( 'El nombre es requerido.', 'psp-territorial' ) );
		}

		$type = isset( $data['type'] ) ? $data['type'] : '';
		if ( ! self::is_valid_type( $type ) ) {
			$errors->add( 'invalid_type', __( 'Tipo de territorio inválido.', 'psp-territorial' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		$slug = ! empty( $data['slug'] ) ? sanitize_title( $data['slug'] ) : sanitize_title( $name );
		$code = ! empty( $data['code'] ) ? sanitize_text_field( $data['code'] ) : '';

		return array(
			'name'      => $name,
			'slug'      => $slug,
			'code'      => $code,
			'type'      => $type,
			'parent_id' => ! empty( $data['parent_id'] ) ? absint( $data['parent_id'] ) : null,
			'level'     => self::get_level( $type ),
			'metadata'  => isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : null,
			'is_active' => isset( $data['is_active'] ) ? (int) (bool) $data['is_active'] : 1,
		);
	}

	/**
	 * Get a cached value or compute it.
	 *
	 * @param string   $key      Cache key.
	 * @param callable $callback Callback to compute the value.
	 * @param int      $ttl      Cache TTL in seconds.
	 * @return mixed
	 */
	public static function get_cached( $key, $callback, $ttl = 3600 ) {
		$cache_key = 'psp_territorial_' . md5( $key );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$value = call_user_func( $callback );
		set_transient( $cache_key, $value, $ttl );
		return $value;
	}

	/**
	 * Clear all PSP territorial transients.
	 */
	public static function clear_cache() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_psp_territorial_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_psp_territorial_%'" );
	}
}
