<?php
/**
 * REST API controller for PSP Territorial.
 *
 * @package PSPTerritorial
 */

namespace PSPTerritorial\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PSPTerritorial\Database;
use PSPTerritorial\Helpers;

/**
 * Registers and handles all REST API endpoints.
 */
class LocationController {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'psp-territorial/v1';

	/**
	 * Register all routes.
	 */
	public static function register_routes() {
		// Only expose API if enabled in settings.
		if ( ! get_option( 'psp_territorial_enable_api', '1' ) ) {
			return;
		}

		$class = __CLASS__;

		// Provinces.
		register_rest_route(
			self::NAMESPACE,
			'/provinces',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $class, 'get_provinces' ),
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $class, 'create_item' ),
					'permission_callback' => array( $class, 'manage_permissions' ),
					'args'                => self::item_args( 'province' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/provinces/(?P<id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $class, 'get_province' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array( 'validate_callback' => 'is_numeric' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( $class, 'update_item' ),
					'permission_callback' => array( $class, 'manage_permissions' ),
					'args'                => self::item_args( 'province', false ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $class, 'delete_item' ),
					'permission_callback' => array( $class, 'manage_permissions' ),
				),
			)
		);

		// Generic items (district / corregimiento / community).
		foreach ( array( 'districts', 'corregimientos', 'communities' ) as $plural ) {
			$type = rtrim( $plural, 's' );
			if ( 'communitie' === $type ) {
				$type = 'community';
			}

			register_rest_route(
				self::NAMESPACE,
				"/{$plural}",
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $class, 'get_items_by_type' ),
						'permission_callback' => '__return_true',
						'args'                => array(
							'parent_id' => array(
								'required'          => false,
								'validate_callback' => 'is_numeric',
							),
						),
					),
					array(
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => array( $class, 'create_item' ),
						'permission_callback' => array( $class, 'manage_permissions' ),
						'args'                => self::item_args( $type ),
					),
				)
			);

			register_rest_route(
				self::NAMESPACE,
				"/{$plural}/(?P<id>\d+)",
				array(
					array(
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => array( $class, 'get_item_by_id' ),
						'permission_callback' => '__return_true',
					),
					array(
						'methods'             => \WP_REST_Server::EDITABLE,
						'callback'            => array( $class, 'update_item' ),
						'permission_callback' => array( $class, 'manage_permissions' ),
						'args'                => self::item_args( $type, false ),
					),
					array(
						'methods'             => \WP_REST_Server::DELETABLE,
						'callback'            => array( $class, 'delete_item' ),
						'permission_callback' => array( $class, 'manage_permissions' ),
					),
				)
			);
		}

		// Search.
		register_rest_route(
			self::NAMESPACE,
			'/search',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $class, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'    => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $value ) {
							return strlen( $value ) >= 2;
						},
					),
					'type' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		// Full hierarchy.
		register_rest_route(
			self::NAMESPACE,
			'/hierarchy',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $class, 'get_hierarchy' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /provinces
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_provinces() {
		$items = Database::get_items( 'province' );
		return rest_ensure_response( $items );
	}

	/**
	 * GET /provinces/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_province( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = Database::get_item( $id );

		if ( ! $item || 'province' !== $item['type'] ) {
			return new \WP_Error( 'not_found', __( 'Province not found.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$item['districts'] = Database::get_items( 'district', $id );
		foreach ( $item['districts'] as &$district ) {
			$district['corregimientos'] = Database::get_items( 'corregimiento', $district['id'] );
			foreach ( $district['corregimientos'] as &$corregimiento ) {
				$corregimiento['communities'] = Database::get_items( 'community', $corregimiento['id'] );
			}
		}

		return rest_ensure_response( $item );
	}

	/**
	 * GET /districts|corregimientos|communities[?parent_id=x]
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_items_by_type( $request ) {
		$route = $request->get_route();
		// Infer type from route segment.
		$map = array(
			'districts'      => 'district',
			'corregimientos' => 'corregimiento',
			'communities'    => 'community',
		);

		$type = null;
		foreach ( $map as $segment => $t ) {
			if ( false !== strpos( $route, $segment ) ) {
				$type = $t;
				break;
			}
		}

		if ( ! $type ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid type.', 'psp-territorial' ), array( 'status' => 400 ) );
		}

		$parent_id = $request->get_param( 'parent_id' );
		$items     = Database::get_items( $type, $parent_id ? (int) $parent_id : null );

		return rest_ensure_response( $items );
	}

	/**
	 * GET /{type}/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function get_item_by_id( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = Database::get_item( $id );

		if ( ! $item ) {
			return new \WP_Error( 'not_found', __( 'Item not found.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $item );
	}

	/**
	 * POST create an item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function create_item( $request ) {
		$data = array(
			'name'        => $request->get_param( 'name' ),
			'type'        => $request->get_param( 'type' ),
			'parent_id'   => $request->get_param( 'parent_id' ),
			'description' => $request->get_param( 'description' ),
		);

		$type = Helpers::validate_type( $data['type'] );
		if ( ! $type ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid type.', 'psp-territorial' ), array( 'status' => 400 ) );
		}

		$id = Database::insert_item( $data );

		if ( ! $id ) {
			return new \WP_Error( 'insert_failed', __( 'Could not create item.', 'psp-territorial' ), array( 'status' => 500 ) );
		}

		$item = Database::get_item( $id );
		return rest_ensure_response( $item );
	}

	/**
	 * PUT/PATCH update an item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function update_item( $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = Database::get_item( $id );

		if ( ! $item ) {
			return new \WP_Error( 'not_found', __( 'Item not found.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$data = array_filter(
			array(
				'name'        => $request->get_param( 'name' ),
				'description' => $request->get_param( 'description' ),
				'parent_id'   => $request->get_param( 'parent_id' ),
			),
			function( $v ) { return null !== $v; }
		);

		Database::update_item( $id, $data );

		return rest_ensure_response( Database::get_item( $id ) );
	}

	/**
	 * DELETE an item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function delete_item( $request ) {
		$id     = (int) $request->get_param( 'id' );
		$item   = Database::get_item( $id );

		if ( ! $item ) {
			return new \WP_Error( 'not_found', __( 'Item not found.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$result = Database::delete_item( $id );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Could not delete item.', 'psp-territorial' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	/**
	 * GET /search?q=...
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function search( $request ) {
		$query = $request->get_param( 'q' );
		$type  = $request->get_param( 'type' );

		if ( $type ) {
			$type = Helpers::validate_type( $type );
		}

		$results = Database::search_items( $query, $type ?: null );
		return rest_ensure_response( $results );
	}

	/**
	 * GET /hierarchy
	 *
	 * @return \WP_REST_Response
	 */
	public static function get_hierarchy() {
		return rest_ensure_response( Database::get_hierarchy() );
	}

	// -------------------------------------------------------------------------
	// Permissions & args
	// -------------------------------------------------------------------------

	/**
	 * Check that the current user can manage territorial data.
	 *
	 * @return bool|\WP_Error
	 */
	public static function manage_permissions() {
		$cap = get_option( 'psp_territorial_manage_cap', 'manage_options' );
		if ( current_user_can( $cap ) ) {
			return true;
		}
		return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to perform this action.', 'psp-territorial' ), array( 'status' => 403 ) );
	}

	/**
	 * Build REST endpoint arg definitions for item creation/editing.
	 *
	 * @param string $type     Territorial type.
	 * @param bool   $required Whether name/type are required.
	 * @return array
	 */
	private static function item_args( $type, $required = true ) {
		return array(
			'name'        => array(
				'required'          => $required,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'type'        => array(
				'required'          => $required,
				'default'           => $type,
				'sanitize_callback' => 'sanitize_key',
			),
			'parent_id'   => array(
				'required'          => false,
				'validate_callback' => 'is_numeric',
			),
			'description' => array(
				'required'          => false,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
		);
	}
}
