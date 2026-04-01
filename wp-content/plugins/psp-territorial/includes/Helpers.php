<?php
/**
 * Helper functions for PSP Territorial.
 *
 * @package PSPTerritorial
 */

namespace PSPTerritorial;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static utility methods used across the plugin.
 */
class Helpers {

	/**
	 * Generate a URL-safe slug from a string.
	 *
	 * @param string $text Input text.
	 * @return string
	 */
	public static function slugify( $text ) {
		// Use WordPress sanitize_title if available (always is inside WP).
		if ( function_exists( 'sanitize_title' ) ) {
			return sanitize_title( $text );
		}

		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9\s-]/', '', $text );
		$text = preg_replace( '/[\s-]+/', '-', $text );
		return trim( $text, '-' );
	}

	/**
	 * Map a type string to a human-readable label.
	 *
	 * @param string $type 'province'|'district'|'corregimiento'|'community'.
	 * @return string
	 */
	public static function type_label( $type ) {
		$labels = array(
			'province'      => __( 'Province', 'psp-territorial' ),
			'district'      => __( 'District', 'psp-territorial' ),
			'corregimiento' => __( 'Corregimiento', 'psp-territorial' ),
			'community'     => __( 'Community', 'psp-territorial' ),
		);
		return isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
	}

	/**
	 * Return the child type for a given parent type.
	 *
	 * @param string $type Parent type.
	 * @return string|null
	 */
	public static function child_type( $type ) {
		$map = array(
			'province'      => 'district',
			'district'      => 'corregimiento',
			'corregimiento' => 'community',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : null;
	}

	/**
	 * Return the parent type for a given type.
	 *
	 * @param string $type Child type.
	 * @return string|null
	 */
	public static function parent_type( $type ) {
		$map = array(
			'district'      => 'province',
			'corregimiento' => 'district',
			'community'     => 'corregimiento',
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : null;
	}

	/**
	 * Sanitize and validate an item type.
	 *
	 * @param string $type Raw type string.
	 * @return string|false Validated type or false.
	 */
	public static function validate_type( $type ) {
		$allowed = array( 'province', 'district', 'corregimiento', 'community' );
		$type    = sanitize_key( $type );
		return in_array( $type, $allowed, true ) ? $type : false;
	}

	/**
	 * Convert a flat list of DB rows into a JSON-export-friendly structure.
	 *
	 * @param array $items DB rows.
	 * @return array
	 */
	public static function rows_to_export( array $items ) {
		$out = array();
		foreach ( $items as $item ) {
			$out[] = array(
				'id'          => (int) $item['id'],
				'name'        => $item['name'],
				'slug'        => $item['slug'],
				'type'        => $item['type'],
				'parent_id'   => $item['parent_id'] ? (int) $item['parent_id'] : null,
				'description' => $item['description'],
			);
		}
		return $out;
	}
}
