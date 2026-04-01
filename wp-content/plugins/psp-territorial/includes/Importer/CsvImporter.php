<?php
/**
 * CSV/JSON importer for PSP Territorial.
 *
 * @package PSPTerritorial
 */

namespace PSPTerritorial\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PSPTerritorial\Database;

/**
 * Handles bulk import of territorial data from CSV or JSON.
 */
class CsvImporter {

	/**
	 * Import a structured JSON array (as produced by the data/panama_data.json file).
	 *
	 * The expected structure is:
	 * [
	 *   {
	 *     "name": "Province",
	 *     "districts": [
	 *       {
	 *         "name": "District",
	 *         "corregimientos": [
	 *           {
	 *             "name": "Corregimiento",
	 *             "communities": [ {"name": "Community"}, ... ]
	 *           }
	 *         ]
	 *       }
	 *     ]
	 *   }
	 * ]
	 *
	 * @param array $provinces Decoded JSON array.
	 * @return array Results summary: ['inserted'=>n, 'skipped'=>n, 'errors'=>[]].
	 */
	public function import_json( array $provinces ) {
		$results = array(
			'inserted' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		foreach ( $provinces as $prov_data ) {
			$prov_id = $this->upsert_item( $prov_data['name'], 'province', null, $results );
			if ( ! $prov_id ) {
				continue;
			}

			if ( empty( $prov_data['districts'] ) ) {
				continue;
			}

			foreach ( $prov_data['districts'] as $dist_data ) {
				$dist_id = $this->upsert_item( $dist_data['name'], 'district', $prov_id, $results );
				if ( ! $dist_id ) {
					continue;
				}

				if ( empty( $dist_data['corregimientos'] ) ) {
					continue;
				}

				foreach ( $dist_data['corregimientos'] as $corr_data ) {
					$corr_id = $this->upsert_item( $corr_data['name'], 'corregimiento', $dist_id, $results );
					if ( ! $corr_id ) {
						continue;
					}

					if ( empty( $corr_data['communities'] ) ) {
						continue;
					}

					foreach ( $corr_data['communities'] as $comm_data ) {
						$this->upsert_item( $comm_data['name'], 'community', $corr_id, $results );
					}
				}
			}
		}

		return $results;
	}

	/**
	 * Parse a raw CSV file (semicolon-separated) and import it.
	 *
	 * Expected columns: Provincia;Distrito;Corregimiento;Comunidad
	 *
	 * @param string $file_path Absolute path to the CSV file.
	 * @return array Results summary.
	 */
	public function import_csv( $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'inserted' => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'File not found or not readable.', 'psp-territorial' ) ),
			);
		}

		$results = array(
			'inserted' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			$results['errors'][] = __( 'Could not open file.', 'psp-territorial' );
			return $results;
		}

		// Skip header row.
		fgetcsv( $handle, 0, ';' );

		$current_province      = null;
		$current_province_id   = null;
		$current_district      = null;
		$current_district_id   = null;
		$current_corregimiento = null;
		$current_corr_id       = null;

		$id_cache = array(); // "type:parent_id:name" => id

		while ( ( $row = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			if ( count( $row ) < 4 ) {
				continue;
			}

			list( $prov, $dist, $corr, $comm ) = array_map( 'trim', array_slice( $row, 0, 4 ) );

			if ( $prov ) {
				if ( $prov !== $current_province ) {
					$current_province    = $prov;
					$current_province_id = $this->upsert_item_cached( $prov, 'province', null, $id_cache, $results );
					$current_district    = null;
					$current_district_id = null;
					$current_corregimiento = null;
					$current_corr_id     = null;
				}
			}

			if ( $dist ) {
				if ( $dist !== $current_district || $current_district_id === null ) {
					$current_district      = $dist;
					$current_district_id   = $this->upsert_item_cached( $dist, 'district', $current_province_id, $id_cache, $results );
					$current_corregimiento = null;
					$current_corr_id       = null;
				}
			}

			if ( $corr ) {
				if ( $corr !== $current_corregimiento || $current_corr_id === null ) {
					$current_corregimiento = $corr;
					$current_corr_id       = $this->upsert_item_cached( $corr, 'corregimiento', $current_district_id, $id_cache, $results );
				}
			}

			if ( $comm && $current_corr_id ) {
				$this->upsert_item_cached( $comm, 'community', $current_corr_id, $id_cache, $results );
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $results;
	}

	/**
	 * Insert an item if it does not already exist (by name + type + parent_id).
	 *
	 * @param string   $name      Item name.
	 * @param string   $type      Item type.
	 * @param int|null $parent_id Parent ID.
	 * @param array    $results   Results reference.
	 * @return int|null Inserted or existing ID.
	 */
	private function upsert_item( $name, $type, $parent_id, array &$results ) {
		if ( '' === trim( $name ) ) {
			return null;
		}

		global $wpdb;
		$table = Database::table();

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE type = %s AND name = %s AND parent_id <=> %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$type,
				$name,
				$parent_id
			)
		);

		if ( $existing_id ) {
			++$results['skipped'];
			return (int) $existing_id;
		}

		$id = Database::insert_item( array(
			'name'      => $name,
			'type'      => $type,
			'parent_id' => $parent_id,
		) );

		if ( $id ) {
			++$results['inserted'];
			return $id;
		}

		$results['errors'][] = sprintf(
			/* translators: 1: item type, 2: item name */
			__( 'Failed to insert %1$s: %2$s', 'psp-territorial' ),
			$type,
			$name
		);
		return null;
	}

	/**
	 * Same as upsert_item but uses an in-memory cache to avoid redundant DB reads.
	 *
	 * @param string   $name      Item name.
	 * @param string   $type      Item type.
	 * @param int|null $parent_id Parent ID.
	 * @param array    $id_cache  In-memory cache reference.
	 * @param array    $results   Results reference.
	 * @return int|null
	 */
	private function upsert_item_cached( $name, $type, $parent_id, array &$id_cache, array &$results ) {
		$cache_key = "{$type}:{$parent_id}:{$name}";
		if ( isset( $id_cache[ $cache_key ] ) ) {
			++$results['skipped'];
			return $id_cache[ $cache_key ];
		}

		$id = $this->upsert_item( $name, $type, $parent_id, $results );
		if ( $id ) {
			$id_cache[ $cache_key ] = $id;
		}
		return $id;
	}
}
