<?php
/**
 * REST API endpoints for PSP Territorial.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_API
 *
 * Registers and handles the REST API routes for the plugin.
 */
class PSP_API {

	/** @var string REST API namespace. */
	const NAMESPACE = 'psp-territorial/v1';

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {
		// Provincias.
		register_rest_route(
			self::NAMESPACE,
			'/provincias',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_provincias' ],
				'permission_callback' => '__return_true',
				'args'                => $this->pagination_args(),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/provincias/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_provincia' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Distritos.
		register_rest_route(
			self::NAMESPACE,
			'/distritos',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_distritos' ],
				'permission_callback' => '__return_true',
				'args'                => array_merge( $this->pagination_args(), $this->parent_arg() ),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/distritos/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_distrito' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Corregimientos.
		register_rest_route(
			self::NAMESPACE,
			'/corregimientos',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_corregimientos' ],
				'permission_callback' => '__return_true',
				'args'                => array_merge( $this->pagination_args(), $this->parent_arg() ),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/corregimientos/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_corregimiento' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Comunidades.
		register_rest_route(
			self::NAMESPACE,
			'/comunidades',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_comunidades' ],
				'permission_callback' => '__return_true',
				'args'                => array_merge( $this->pagination_args(), $this->parent_arg() ),
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/comunidades/(?P<id>\d+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_comunidad' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $v ) => is_numeric( $v ),
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Full hierarchy.
		register_rest_route(
			self::NAMESPACE,
			'/jerarquia',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_jerarquia' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	// -------------------------------------------------------------------------
	// Handlers – Provincias
	// -------------------------------------------------------------------------

	/**
	 * GET /provincias
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_provincias( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' ) ?: 100;
		$page     = (int) $request->get_param( 'page' ) ?: 1;

		$items = PSP_Database::get_by_type( 'provincia', null, $per_page, $page );
		$total = PSP_Database::count_by_type( 'provincia' );

		/** This filter is documented in class-psp-api.php */
		$items = apply_filters( 'psp_territorial_provinces_list', $items );

		$response = rest_ensure_response( array_map( [ $this, 'format_entity' ], $items ) );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * GET /provincias/{id}  – province with nested districts.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_provincia( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = PSP_Database::get_by_id( $id );

		if ( ! $entity || 'provincia' !== $entity->type ) {
			return new WP_Error( 'not_found', __( 'Provincia no encontrada.', 'psp-territorial' ), [ 'status' => 404 ] );
		}

		$data              = $this->format_entity( $entity );
		$data['districts'] = array_map( [ $this, 'format_entity' ], PSP_Database::get_children( $id, 'distrito' ) );

		return rest_ensure_response( $data );
	}

	// -------------------------------------------------------------------------
	// Handlers – Distritos
	// -------------------------------------------------------------------------

	/**
	 * GET /distritos
	 */
	public function get_distritos( WP_REST_Request $request ): WP_REST_Response {
		$parent   = $request->get_param( 'parent_id' ) ? (int) $request->get_param( 'parent_id' ) : null;
		$per_page = (int) $request->get_param( 'per_page' ) ?: 100;
		$page     = (int) $request->get_param( 'page' ) ?: 1;

		$items = PSP_Database::get_by_type( 'distrito', $parent, $per_page, $page );
		$total = PSP_Database::count_by_type( 'distrito', $parent );

		$response = rest_ensure_response( array_map( [ $this, 'format_entity' ], $items ) );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );
		return $response;
	}

	/**
	 * GET /distritos/{id}  – district with nested corregimientos.
	 */
	public function get_distrito( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = PSP_Database::get_by_id( $id );

		if ( ! $entity || 'distrito' !== $entity->type ) {
			return new WP_Error( 'not_found', __( 'Distrito no encontrado.', 'psp-territorial' ), [ 'status' => 404 ] );
		}

		$data                   = $this->format_entity( $entity );
		$data['corregimientos'] = array_map( [ $this, 'format_entity' ], PSP_Database::get_children( $id, 'corregimiento' ) );

		return rest_ensure_response( $data );
	}

	// -------------------------------------------------------------------------
	// Handlers – Corregimientos
	// -------------------------------------------------------------------------

	/** GET /corregimientos */
	public function get_corregimientos( WP_REST_Request $request ): WP_REST_Response {
		$parent   = $request->get_param( 'parent_id' ) ? (int) $request->get_param( 'parent_id' ) : null;
		$per_page = (int) $request->get_param( 'per_page' ) ?: 200;
		$page     = (int) $request->get_param( 'page' ) ?: 1;

		$items = PSP_Database::get_by_type( 'corregimiento', $parent, $per_page, $page );
		$total = PSP_Database::count_by_type( 'corregimiento', $parent );

		$response = rest_ensure_response( array_map( [ $this, 'format_entity' ], $items ) );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );
		return $response;
	}

	/** GET /corregimientos/{id}  – corregimiento with nested communities. */
	public function get_corregimiento( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = PSP_Database::get_by_id( $id );

		if ( ! $entity || 'corregimiento' !== $entity->type ) {
			return new WP_Error( 'not_found', __( 'Corregimiento no encontrado.', 'psp-territorial' ), [ 'status' => 404 ] );
		}

		$data                 = $this->format_entity( $entity );
		$data['communities']  = array_map( [ $this, 'format_entity' ], PSP_Database::get_children( $id, 'comunidad' ) );

		return rest_ensure_response( $data );
	}

	// -------------------------------------------------------------------------
	// Handlers – Comunidades
	// -------------------------------------------------------------------------

	/** GET /comunidades */
	public function get_comunidades( WP_REST_Request $request ): WP_REST_Response {
		$parent   = $request->get_param( 'parent_id' ) ? (int) $request->get_param( 'parent_id' ) : null;
		$per_page = (int) $request->get_param( 'per_page' ) ?: 200;
		$page     = (int) $request->get_param( 'page' ) ?: 1;

		$items = PSP_Database::get_by_type( 'comunidad', $parent, $per_page, $page );
		$total = PSP_Database::count_by_type( 'comunidad', $parent );

		$response = rest_ensure_response( array_map( [ $this, 'format_entity' ], $items ) );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', ceil( $total / $per_page ) );
		return $response;
	}

	/** GET /comunidades/{id} */
	public function get_comunidad( WP_REST_Request $request ) {
		$id     = (int) $request->get_param( 'id' );
		$entity = PSP_Database::get_by_id( $id );

		if ( ! $entity || 'comunidad' !== $entity->type ) {
			return new WP_Error( 'not_found', __( 'Comunidad no encontrada.', 'psp-territorial' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->format_entity( $entity ) );
	}

	// -------------------------------------------------------------------------
	// Handlers – Jerarquía completa
	// -------------------------------------------------------------------------

	/** GET /jerarquia */
	public function get_jerarquia( WP_REST_Request $request ): WP_REST_Response {
		$provinces = PSP_Database::get_by_type( 'provincia' );
		$output    = [];

		foreach ( $provinces as $province ) {
			$p              = $this->format_entity( $province );
			$p['distritos'] = [];

			$districts = PSP_Database::get_children( (int) $province->id, 'distrito' );
			foreach ( $districts as $district ) {
				$d                  = $this->format_entity( $district );
				$d['corregimientos'] = [];

				$corregimientos = PSP_Database::get_children( (int) $district->id, 'corregimiento' );
				foreach ( $corregimientos as $corr ) {
					$c               = $this->format_entity( $corr );
					$c['comunidades'] = array_map(
						[ $this, 'format_entity' ],
						PSP_Database::get_children( (int) $corr->id, 'comunidad' )
					);
					$d['corregimientos'][] = $c;
				}

				$p['distritos'][] = $d;
			}

			$output[] = $p;
		}

		return rest_ensure_response( $output );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Format a DB row as an API-ready array.
	 *
	 * @param object $entity
	 * @return array
	 */
	private function format_entity( object $entity ): array {
		return [
			'id'        => (int) $entity->id,
			'name'      => $entity->name,
			'slug'      => $entity->slug,
			'type'      => $entity->type,
			'parent_id' => $entity->parent_id ? (int) $entity->parent_id : null,
		];
	}

	/**
	 * Common pagination arguments.
	 *
	 * @return array
	 */
	private function pagination_args(): array {
		return [
			'page'     => [
				'default'           => 1,
				'sanitize_callback' => 'absint',
			],
			'per_page' => [
				'default'           => 100,
				'sanitize_callback' => 'absint',
			],
		];
	}

	/**
	 * Optional parent_id argument.
	 *
	 * @return array
	 */
	private function parent_arg(): array {
		return [
			'parent_id' => [
				'default'           => null,
				'sanitize_callback' => 'absint',
			],
		];
	}
}
