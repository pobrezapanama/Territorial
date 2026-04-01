<?php
/**
 * Public API Functions
 *
 * These functions are the public-facing API for other plugins to integrate with
 * PSP Territorial without instantiating classes directly.
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check whether PSP Territorial is available.
 *
 * @return bool
 */
function psp_territorial_available() {
	return class_exists( 'PSP_Territorial_Query' );
}

/**
 * Get all active provinces.
 *
 * @return array Array of province objects.
 */
function psp_get_provinces() {
	return PSP_Territorial_Query::get_provinces();
}

/**
 * Get territories by type.
 *
 * @param string $type Territory type ('province'|'district'|'corregimiento'|'community').
 * @param array  $args Optional query args.
 * @return array
 */
function psp_get_territories( $type, $args = array() ) {
	return PSP_Territorial_Query::get_by_type( $type, $args );
}

/**
 * Get child territories (districts of a province, corregimientos of a district, etc.).
 *
 * @param string $parent_type Type of the parent entity.
 * @param int    $parent_id   ID of the parent entity.
 * @param array  $args        Optional query args.
 * @return array
 */
function psp_get_children( $parent_type, $parent_id, $args = array() ) {
	return PSP_Territorial_Query::get_children( $parent_type, $parent_id, $args );
}

/**
 * Get a territory by its ID.
 *
 * @param int $id Territory ID.
 * @return object|null
 */
function psp_get_territory( $id ) {
	return PSP_Territorial_Query::get_by_id( $id );
}

/**
 * Get a territory by its slug.
 *
 * @param string $slug Territory slug.
 * @return object|null
 */
function psp_get_territory_by_slug( $slug ) {
	return PSP_Territorial_Query::get_by_slug( $slug );
}

/**
 * Get the full ancestry path for a territory.
 *
 * @param int $entity_id Territory ID.
 * @return array Array of territory objects from root to the given entity.
 */
function psp_get_territory_path( $entity_id ) {
	return PSP_Territorial_Query::get_full_path( $entity_id );
}

/**
 * Check whether a territory entity exists.
 *
 * @param string $type Territory type.
 * @param string $slug Territory slug.
 * @return bool
 */
function psp_territory_exists( $type, $slug ) {
	return PSP_Territorial_Query::exists( $type, $slug );
}

/**
 * Get a territory meta value.
 *
 * @param int    $territory_id Territory ID.
 * @param string $meta_key     Meta key. If empty, returns all meta.
 * @param bool   $single       Whether to return a single value.
 * @return mixed
 */
function psp_get_territory_meta( $territory_id, $meta_key = '', $single = true ) {
	return PSP_Territorial_Query::get_meta( $territory_id, $meta_key, $single );
}

/**
 * Update a territory meta value.
 *
 * @param int    $territory_id Territory ID.
 * @param string $meta_key     Meta key.
 * @param mixed  $meta_value   Meta value.
 * @return bool
 */
function psp_update_territory_meta( $territory_id, $meta_key, $meta_value ) {
	return PSP_Territorial_Query::update_meta( $territory_id, $meta_key, $meta_value );
}

/**
 * Generate an HTML <select> element populated with territories of a given type.
 *
 * @param string $type     Territory type.
 * @param array  $args     Optional args:
 *                         - name        : field name (default: "territory_{type}")
 *                         - id          : field id
 *                         - class       : CSS class
 *                         - selected    : currently selected value (ID)
 *                         - placeholder : placeholder option text
 *                         - data        : associative array of data-* attributes
 *                         - parent_id   : filter by parent ID
 * @return string HTML markup.
 */
function psp_get_select_html( $type, $args = array() ) {
	$defaults = array(
		'name'        => 'territory_' . $type,
		'id'          => 'territory_' . $type,
		'class'       => 'psp-territory-select',
		'selected'    => 0,
		'placeholder' => '',
		'data'        => array(),
		'parent_id'   => '',
	);
	$args = wp_parse_args( $args, $defaults );

	$query_args = array( 'limit' => 1000 );
	if ( '' !== $args['parent_id'] ) {
		$query_args['parent_id'] = $args['parent_id'];
	}

	$territories = psp_get_territories( $type, $query_args );

	// Build data attributes.
	$data_attrs = '';
	if ( ! empty( $args['data'] ) && is_array( $args['data'] ) ) {
		foreach ( $args['data'] as $key => $value ) {
			$data_attrs .= ' data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
	}

	$html  = '<select ';
	$html .= 'name="' . esc_attr( $args['name'] ) . '" ';
	$html .= 'id="' . esc_attr( $args['id'] ) . '" ';
	$html .= 'class="' . esc_attr( $args['class'] ) . '"';
	$html .= $data_attrs;
	$html .= '>';

	if ( ! empty( $args['placeholder'] ) ) {
		$html .= '<option value="">' . esc_html( $args['placeholder'] ) . '</option>';
	}

	foreach ( $territories as $territory ) {
		$selected = selected( (int) $args['selected'], (int) $territory->id, false );
		$html    .= '<option value="' . esc_attr( $territory->id ) . '"' . $selected . '>' . esc_html( $territory->name ) . '</option>';
	}

	$html .= '</select>';

	return $html;
}

/**
 * Output an HTML <select> element for territories.
 *
 * @param string $type Territory type.
 * @param array  $args Optional args (same as psp_get_select_html).
 */
function psp_select_html( $type, $args = array() ) {
	echo psp_get_select_html( $type, $args ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
