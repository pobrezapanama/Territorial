<?php
/**
 * REST API Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Api
 *
 * Registers and handles all REST API endpoints.
 */
class PSP_Territorial_Api {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'psp/v1';

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database
	 */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param PSP_Territorial_Database $db Database instance.
	 */
	public function __construct( PSP_Territorial_Database $db ) {
		$this->db = $db;
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes() {
		// GET /psp/v1/territories
		register_rest_route( self::NAMESPACE, '/territories', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_territories' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_territories_args(),
			),
		) );

		// GET /psp/v1/territories/{id}
		register_rest_route( self::NAMESPACE, '/territories/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_territory' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $p ) { return is_numeric( $p ); },
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// GET /psp/v1/hierarchy/provinces
		register_rest_route( self::NAMESPACE, '/hierarchy/provinces', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_provinces' ),
				'permission_callback' => '__return_true',
			),
		) );

		// GET /psp/v1/hierarchy/provinces/{province_id}/districts
		register_rest_route( self::NAMESPACE, '/hierarchy/provinces/(?P<province_id>\d+)/districts', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_districts' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'province_id' => array(
						'validate_callback' => function( $p ) { return is_numeric( $p ); },
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// GET /psp/v1/hierarchy/districts/{district_id}/corregimientos
		register_rest_route( self::NAMESPACE, '/hierarchy/districts/(?P<district_id>\d+)/corregimientos', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_corregimientos' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'district_id' => array(
						'validate_callback' => function( $p ) { return is_numeric( $p ); },
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// GET /psp/v1/hierarchy/corregimientos/{corregimiento_id}/communities
		register_rest_route( self::NAMESPACE, '/hierarchy/corregimientos/(?P<corregimiento_id>\d+)/communities', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_communities' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'corregimiento_id' => array(
						'validate_callback' => function( $p ) { return is_numeric( $p ); },
						'sanitize_callback' => 'absint',
					),
				),
			),
		) );

		// GET /psp/v1/search
		register_rest_route( self::NAMESPACE, '/search', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q'         => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'      => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'parent_id' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'limit'     => array(
						'sanitize_callback' => 'absint',
						'default'           => 20,
					),
				),
			),
		) );

		// GET /psp/v1/path/{type}/{id}
		register_rest_route( self::NAMESPACE, '/path/(?P<type>[a-z]+)/(?P<id>\d+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_path' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'validate_callback' => function( $p ) { return is_numeric( $p ); },
						'sanitize_callback' => 'absint',
					),
					'type' => array(
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Endpoint handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /territories
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_territories( WP_REST_Request $request ) {
		$args = array(
			'type'      => $request->get_param( 'type' ) ?? '',
			'parent_id' => $request->get_param( 'parent_id' ) ?? '',
			'search'    => $request->get_param( 'search' ) ?? '',
			'limit'     => $request->get_param( 'limit' ) ?? 50,
			'offset'    => $request->get_param( 'offset' ) ?? 0,
		);

		$territories = $this->db->get_territories( $args );
		$total       = $this->db->count_territories( $args );

		$data = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $territories );
		$data = apply_filters( 'psp_territorial_api_response', $data );

		$response = rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
			'meta'    => array(
				'total'  => $total,
				'limit'  => (int) $args['limit'],
				'offset' => (int) $args['offset'],
			),
		) );

		return $response;
	}

	/**
	 * GET /territories/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_territory( WP_REST_Request $request ) {
		$id     = $request->get_param( 'id' );
		$entity = $this->db->get_by_id( $id );

		if ( ! $entity ) {
			return new WP_Error( 'not_found', __( 'Territorio no encontrado.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$data = PSP_Territorial_Utils::format_entity( $entity );
		$data = apply_filters( 'psp_territorial_api_response', $data );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	/**
	 * GET /hierarchy/provinces
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_provinces( WP_REST_Request $request ) {
		$provinces = $this->db->get_by_type( 'province', array( 'limit' => 500 ) );
		$data      = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $provinces );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	/**
	 * GET /hierarchy/provinces/{province_id}/districts
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_districts( WP_REST_Request $request ) {
		$province_id = $request->get_param( 'province_id' );
		$province    = $this->db->get_by_id( $province_id );

		if ( ! $province || 'province' !== $province->type ) {
			return new WP_Error( 'not_found', __( 'Provincia no encontrada.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$districts = $this->db->get_by_parent( $province_id );
		$data      = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $districts );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	/**
	 * GET /hierarchy/districts/{district_id}/corregimientos
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_corregimientos( WP_REST_Request $request ) {
		$district_id = $request->get_param( 'district_id' );
		$district    = $this->db->get_by_id( $district_id );

		if ( ! $district || 'district' !== $district->type ) {
			return new WP_Error( 'not_found', __( 'Distrito no encontrado.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$corregimientos = $this->db->get_by_parent( $district_id );
		$data           = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $corregimientos );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	/**
	 * GET /hierarchy/corregimientos/{corregimiento_id}/communities
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_communities( WP_REST_Request $request ) {
		$corregimiento_id = $request->get_param( 'corregimiento_id' );
		$corregimiento    = $this->db->get_by_id( $corregimiento_id );

		if ( ! $corregimiento || 'corregimiento' !== $corregimiento->type ) {
			return new WP_Error( 'not_found', __( 'Corregimiento no encontrado.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$communities = $this->db->get_by_parent( $corregimiento_id );
		$data        = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $communities );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	/**
	 * GET /search
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function search( WP_REST_Request $request ) {
		$q         = $request->get_param( 'q' );
		$type      = $request->get_param( 'type' );
		$parent_id = absint( $request->get_param( 'parent_id' ) );
		$limit     = min( absint( $request->get_param( 'limit' ) ), 100 );

		$args = array(
			'search' => $q,
			'limit'  => $limit,
		);

		if ( ! empty( $type ) && PSP_Territorial_Utils::is_valid_type( $type ) ) {
			$args['type'] = $type;
		}

		if ( $parent_id > 0 ) {
			$args['parent_id'] = $parent_id;
		}

		$territories = $this->db->get_territories( $args );
		$data        = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $territories );

		return rest_ensure_response( array(
			'success' => true,
			'query'   => $q,
			'data'    => $data,
		) );
	}

	/**
	 * GET /path/{type}/{id}
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function get_path( WP_REST_Request $request ) {
		$id   = $request->get_param( 'id' );
		$path = $this->db->get_path( $id );

		if ( empty( $path ) ) {
			return new WP_Error( 'not_found', __( 'Territorio no encontrado.', 'psp-territorial' ), array( 'status' => 404 ) );
		}

		$data = array_map( array( 'PSP_Territorial_Utils', 'format_entity' ), $path );

		return rest_ensure_response( array(
			'success' => true,
			'data'    => $data,
		) );
	}

	// -------------------------------------------------------------------------
	// Arguments schema
	// -------------------------------------------------------------------------

	/**
	 * Get argument schema for GET /territories.
	 *
	 * @return array
	 */
	private function get_territories_args() {
		return array(
			'type'      => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'parent_id' => array(
				'sanitize_callback' => 'absint',
				'default'           => '',
			),
			'search'    => array(
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			),
			'limit'     => array(
				'sanitize_callback' => 'absint',
				'default'           => 50,
			),
			'offset'    => array(
				'sanitize_callback' => 'absint',
				'default'           => 0,
			),
		);
	}
}
