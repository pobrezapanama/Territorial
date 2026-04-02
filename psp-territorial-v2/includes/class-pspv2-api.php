<?php
/**
 * REST API – PSP Territorial V2
 *
 * Namespace: psp-territorial/v2
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_Api
 */
class PSPV2_Api {

	/** REST namespace. */
	const NAMESPACE = 'psp-territorial/v2';

	/** @var PSPV2_Database */
	private $db;

	/**
	 * Constructor.
	 *
	 * @param PSPV2_Database $db
	 */
	public function __construct( PSPV2_Database $db ) {
		$this->db = $db;
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes() {
		// GET /psp-territorial/v2/provincias
		register_rest_route( self::NAMESPACE, '/provincias', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_provincias' ),
			'permission_callback' => '__return_true',
		) );

		// GET /psp-territorial/v2/distritos?parent_id=
		register_rest_route( self::NAMESPACE, '/distritos', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_distritos' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'parent_id' => array(
					'sanitize_callback' => 'absint',
					'default'           => 0,
				),
			),
		) );

		// GET /psp-territorial/v2/corregimientos?parent_id=
		register_rest_route( self::NAMESPACE, '/corregimientos', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_corregimientos' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'parent_id' => array(
					'sanitize_callback' => 'absint',
					'default'           => 0,
				),
			),
		) );

		// GET /psp-territorial/v2/comunidades?parent_id=
		register_rest_route( self::NAMESPACE, '/comunidades', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_comunidades' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'parent_id' => array(
					'sanitize_callback' => 'absint',
					'default'           => 0,
				),
			),
		) );

		// GET /psp-territorial/v2/search?q=&type=
		register_rest_route( self::NAMESPACE, '/search', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'search' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'q'     => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'type'  => array(
					'sanitize_callback' => 'sanitize_text_field',
					'default'           => '',
				),
				'limit' => array(
					'sanitize_callback' => 'absint',
					'default'           => 20,
				),
			),
		) );

		// GET /psp-territorial/v2/path/{id}
		register_rest_route( self::NAMESPACE, '/path/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_path' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $p ) { return is_numeric( $p ); },
					'sanitize_callback' => 'absint',
				),
			),
		) );

		// GET /psp-territorial/v2/item/{id}
		register_rest_route( self::NAMESPACE, '/item/(?P<id>\d+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_item' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'id' => array(
					'validate_callback' => function( $p ) { return is_numeric( $p ); },
					'sanitize_callback' => 'absint',
				),
			),
		) );
	}

	// -------------------------------------------------------------------------
	// Handlers
	// -------------------------------------------------------------------------

	/**
	 * GET /provincias
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_provincias( WP_REST_Request $request ) {
		$items = $this->db->get_items( array( 'type' => 'province', 'limit' => 100 ) );
		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $items ) ) );
	}

	/**
	 * GET /distritos?parent_id=
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_distritos( WP_REST_Request $request ) {
		$args = array( 'type' => 'district', 'limit' => 500 );
		$parent_id = (int) $request->get_param( 'parent_id' );
		if ( $parent_id > 0 ) {
			$args['parent_id'] = $parent_id;
		}
		$items = $this->db->get_items( $args );
		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $items ) ) );
	}

	/**
	 * GET /corregimientos?parent_id=
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_corregimientos( WP_REST_Request $request ) {
		$args = array( 'type' => 'corregimiento', 'limit' => 1000 );
		$parent_id = (int) $request->get_param( 'parent_id' );
		if ( $parent_id > 0 ) {
			$args['parent_id'] = $parent_id;
		}
		$items = $this->db->get_items( $args );
		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $items ) ) );
	}

	/**
	 * GET /comunidades?parent_id=
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_comunidades( WP_REST_Request $request ) {
		$args = array( 'type' => 'community', 'limit' => 500 );
		$parent_id = (int) $request->get_param( 'parent_id' );
		if ( $parent_id > 0 ) {
			$args['parent_id'] = $parent_id;
		}
		$items = $this->db->get_items( $args );
		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $items ) ) );
	}

	/**
	 * GET /search?q=&type=&limit=
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function search( WP_REST_Request $request ) {
		$q     = $request->get_param( 'q' );
		$type  = $request->get_param( 'type' );
		$limit = max( 1, min( 200, (int) $request->get_param( 'limit' ) ) );

		$args = array( 'search' => $q, 'limit' => $limit );
		if ( ! empty( $type ) ) {
			$args['type'] = $type;
		}

		$items = $this->db->get_items( $args );
		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $items ) ) );
	}

	/**
	 * GET /path/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_path( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$path = $this->db->get_path( $id );

		if ( empty( $path ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Item not found.' ), 404 );
		}

		return rest_ensure_response( $this->success_response( array_map( array( $this, 'format_item' ), $path ) ) );
	}

	/**
	 * GET /item/{id}
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ) {
		$id   = (int) $request->get_param( 'id' );
		$item = $this->db->get_by_id( $id );

		if ( ! $item ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => 'Item not found.' ), 404 );
		}

		return rest_ensure_response( $this->success_response( $this->format_item( $item ) ) );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Format a DB row for API output.
	 *
	 * @param object $item
	 * @return array
	 */
	private function format_item( $item ) {
		return array(
			'id'        => (int) $item->id,
			'name'      => $item->name,
			'slug'      => $item->slug,
			'code'      => $item->code,
			'type'      => $item->type,
			'parent_id' => '' !== $item->parent_id && null !== $item->parent_id ? (int) $item->parent_id : null,
			'level'     => (int) $item->level,
			'is_active' => (bool) $item->is_active,
		);
	}

	/**
	 * Wrap data in a success envelope.
	 *
	 * @param mixed $data
	 * @return array
	 */
	private function success_response( $data ) {
		$count = is_array( $data ) ? count( $data ) : 1;
		return array(
			'success' => true,
			'count'   => $count,
			'data'    => $data,
		);
	}
}
