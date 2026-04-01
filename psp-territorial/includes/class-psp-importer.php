<?php
/**
 * CSV / JSON data importer for PSP Territorial.
 *
 * @package PSP_Territorial
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Importer
 *
 * Reads the bundled JSON data file and populates the territories table.
 */
class PSP_Importer {

	/** @var string Path to the JSON data file. */
	private string $data_file;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->data_file = PSP_TERRITORIAL_PATH . 'assets/data/panama_data.json';
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Run the full import process.
	 *
	 * @param bool $force Re-import even if data already exists.
	 * @return array{imported: int, skipped: int, errors: int}
	 */
	public function import( bool $force = false ): array {
		/**
		 * Fires before the territorial data import begins.
		 *
		 * @param bool $force Whether a forced re-import was requested.
		 */
		do_action( 'psp_territorial_before_import', $force );

		$stats = [ 'imported' => 0, 'skipped' => 0, 'errors' => 0 ];

		if ( ! $force && ! PSP_Database::is_empty() ) {
			$stats['skipped'] = PSP_Database::count_all();
			return $stats;
		}

		if ( ! file_exists( $this->data_file ) ) {
			$stats['errors']++;
			return $stats;
		}

		$json = file_get_contents( $this->data_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $json ) {
			$stats['errors']++;
			return $stats;
		}

		$data = json_decode( $json, true );
		if ( ! is_array( $data ) ) {
			$stats['errors']++;
			return $stats;
		}

		// Wipe existing data when forcing.
		if ( $force ) {
			global $wpdb;
			$table = PSP_Database::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		foreach ( $data as $province_data ) {
			$province_id = $this->insert_entity( $province_data['name'], 'provincia', null );
			if ( ! $province_id ) {
				$stats['errors']++;
				continue;
			}
			$stats['imported']++;

			foreach ( $province_data['districts'] ?? [] as $district_data ) {
				$district_id = $this->insert_entity( $district_data['name'], 'distrito', $province_id );
				if ( ! $district_id ) {
					$stats['errors']++;
					continue;
				}
				$stats['imported']++;

				foreach ( $district_data['corregimientos'] ?? [] as $corr_data ) {
					$corr_id = $this->insert_entity( $corr_data['name'], 'corregimiento', $district_id );
					if ( ! $corr_id ) {
						$stats['errors']++;
						continue;
					}
					$stats['imported']++;

					foreach ( $corr_data['communities'] ?? [] as $community_name ) {
						$comm_id = $this->insert_entity( $community_name, 'comunidad', $corr_id );
						if ( ! $comm_id ) {
							$stats['errors']++;
							continue;
						}
						$stats['imported']++;
					}
				}
			}
		}

		update_option( 'psp_territorial_imported', true );

		/**
		 * Fires after the territorial data import completes.
		 *
		 * @param array $stats Import statistics.
		 */
		do_action( 'psp_territorial_after_import', $stats );

		return $stats;
	}

	/**
	 * Export all territories to a JSON string.
	 *
	 * @return string JSON-encoded hierarchy.
	 */
	public function export_json(): string {
		$provinces = PSP_Database::get_by_type( 'provincia' );
		$output    = [];

		foreach ( $provinces as $province ) {
			$p = [
				'id'        => (int) $province->id,
				'name'      => $province->name,
				'slug'      => $province->slug,
				'districts' => [],
			];

			$districts = PSP_Database::get_children( (int) $province->id, 'distrito' );
			foreach ( $districts as $district ) {
				$d = [
					'id'             => (int) $district->id,
					'name'           => $district->name,
					'slug'           => $district->slug,
					'corregimientos' => [],
				];

				$corregimientos = PSP_Database::get_children( (int) $district->id, 'corregimiento' );
				foreach ( $corregimientos as $corr ) {
					$c = [
						'id'          => (int) $corr->id,
						'name'        => $corr->name,
						'slug'        => $corr->slug,
						'communities' => [],
					];

					$communities = PSP_Database::get_children( (int) $corr->id, 'comunidad' );
					foreach ( $communities as $comm ) {
						$c['communities'][] = [
							'id'   => (int) $comm->id,
							'name' => $comm->name,
							'slug' => $comm->slug,
						];
					}

					$d['corregimientos'][] = $c;
				}

				$p['districts'][] = $d;
			}

			$output[] = $p;
		}

		return wp_json_encode( $output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Insert a single entity and fire the creation hook.
	 *
	 * @param string   $name
	 * @param string   $type
	 * @param int|null $parent_id
	 * @return int|false
	 */
	private function insert_entity( string $name, string $type, ?int $parent_id ) {
		$id = PSP_Database::insert(
			[
				'name'      => $name,
				'type'      => $type,
				'parent_id' => $parent_id,
			]
		);

		if ( $id ) {
			/**
			 * Fires after a territorial entity is created during import.
			 *
			 * @param int    $id        New entity ID.
			 * @param string $name      Entity name.
			 * @param string $type      Entity type.
			 * @param int|null $parent_id Parent entity ID.
			 */
			do_action( 'psp_territorial_entity_created', $id, $name, $type, $parent_id );
		}

		return $id;
	}
}
