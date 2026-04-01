<?php
/**
 * CSV/JSON Importer Class
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_Importer
 *
 * Handles importing territorial data from CSV and JSON sources.
 */
class PSP_Territorial_Importer {

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database
	 */
	private $db;

	/**
	 * Import log entries.
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Constructor.
	 *
	 * @param PSP_Territorial_Database $db Database instance.
	 */
	public function __construct( PSP_Territorial_Database $db ) {
		$this->db = $db;
	}

	/**
	 * Import data from the pre-generated JSON file.
	 *
	 * @param bool $truncate Whether to truncate existing data first.
	 * @return array Import result summary.
	 */
	public function import_from_json( $truncate = false ) {
		$json_path = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_data.json';

		if ( ! file_exists( $json_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Archivo JSON no encontrado.', 'psp-territorial' ),
			);
		}

		do_action( 'psp_territorial_before_import' );

		if ( $truncate ) {
			$this->db->truncate();
			$this->log( __( 'Datos anteriores eliminados.', 'psp-territorial' ) );
		}

		$json    = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$decoded = json_decode( $json, true );

		if ( null === $decoded || ! isset( $decoded['data'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'JSON inválido o corrupto.', 'psp-territorial' ),
			);
		}

		$result = $this->insert_flat_data( $decoded['data'] );

		do_action( 'psp_territorial_after_import', $result['count'] );

		return $result;
	}

	/**
	 * Import data from the raw CSV file.
	 *
	 * @param string $csv_path Path to the CSV file.
	 * @param bool   $truncate Whether to truncate existing data first.
	 * @return array Import result summary.
	 */
	public function import_from_csv( $csv_path = '', $truncate = false ) {
		if ( empty( $csv_path ) ) {
			$csv_path = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_raw.csv';
		}

		if ( ! file_exists( $csv_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Archivo CSV no encontrado.', 'psp-territorial' ),
			);
		}

		do_action( 'psp_territorial_before_import' );

		if ( $truncate ) {
			$this->db->truncate();
			$this->log( __( 'Datos anteriores eliminados.', 'psp-territorial' ) );
		}

		$rows = $this->parse_csv( $csv_path );

		if ( empty( $rows ) ) {
			return array(
				'success' => false,
				'message' => __( 'No se encontraron datos en el CSV.', 'psp-territorial' ),
				'log'     => $this->log,
			);
		}

		$result = $this->insert_flat_data( $rows );

		do_action( 'psp_territorial_after_import', $result['count'] );

		return $result;
	}

	/**
	 * Parse the Panama CSV into a flat array of territory records.
	 *
	 * @param string $csv_path Path to the CSV file.
	 * @return array Flat array of territory data.
	 */
	public function parse_csv( $csv_path ) {
		$handle = fopen( $csv_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		if ( ! $handle ) {
			return array();
		}

		$rows               = array();
		$province_index     = 0;
		$district_index     = 0;
		$corregimiento_index = 0;
		$community_index    = 0;
		$current_province_id     = null;
		$current_district_id     = null;
		$current_corregimiento_id = null;

		// Skip header line.
		fgetcsv( $handle, 0, ';' );

		while ( ( $line = fgetcsv( $handle, 0, ';' ) ) !== false ) {
			// Normalize: pad to 4 columns.
			while ( count( $line ) < 4 ) {
				$line[] = '';
			}

			$prov = $this->clean_field( $line[0] );
			$dist = $this->clean_field( $line[1] );
			$corr = $this->clean_field( $line[2] );
			$comm = $this->clean_field( $line[3] );

			if ( ! $prov && ! $dist && ! $corr && ! $comm ) {
				continue;
			}

			if ( $prov ) {
				++$province_index;
				$id          = $province_index;
				$slug        = PSP_Territorial_Utils::generate_slug( $prov );
				$code        = PSP_Territorial_Utils::generate_code( 'province', $province_index );
				$rows[]      = array(
					'id'        => $id,
					'name'      => $prov,
					'slug'      => $slug,
					'code'      => $code,
					'type'      => 'province',
					'parent_id' => null,
					'level'     => 1,
				);
				$current_province_id      = $id;
				$current_district_id      = null;
				$current_corregimiento_id = null;
			} elseif ( $dist ) {
				++$district_index;
				$id     = 1000 + $district_index;
				$slug   = PSP_Territorial_Utils::generate_slug( $dist );
				$code   = PSP_Territorial_Utils::generate_code( 'district', $district_index );
				$rows[] = array(
					'id'        => $id,
					'name'      => $dist,
					'slug'      => $slug,
					'code'      => $code,
					'type'      => 'district',
					'parent_id' => $current_province_id,
					'level'     => 2,
				);
				$current_district_id      = $id;
				$current_corregimiento_id = null;
			} elseif ( $corr ) {
				++$corregimiento_index;
				$id     = 10000 + $corregimiento_index;
				$slug   = PSP_Territorial_Utils::generate_slug( $corr );
				$code   = PSP_Territorial_Utils::generate_code( 'corregimiento', $corregimiento_index );
				$rows[] = array(
					'id'        => $id,
					'name'      => $corr,
					'slug'      => $slug,
					'code'      => $code,
					'type'      => 'corregimiento',
					'parent_id' => $current_district_id,
					'level'     => 3,
				);
				$current_corregimiento_id = $id;
			} elseif ( $comm ) {
				++$community_index;
				$id     = 100000 + $community_index;
				$slug   = PSP_Territorial_Utils::generate_slug( $comm );
				$code   = PSP_Territorial_Utils::generate_code( 'community', $community_index );
				$rows[] = array(
					'id'        => $id,
					'name'      => $comm,
					'slug'      => $slug,
					'code'      => $code,
					'type'      => 'community',
					'parent_id' => $current_corregimiento_id,
					'level'     => 4,
				);
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		return $rows;
	}

	/**
	 * Insert flat data array into the database.
	 *
	 * @param array $rows Flat array of territory records.
	 * @return array Summary of the import result.
	 */
	private function insert_flat_data( array $rows ) {
		global $wpdb;

		$table   = $this->db->get_table_name();
		$count   = 0;
		$errors  = 0;
		$batch_values       = array();
		$batch_placeholders = array();

		foreach ( $rows as $data ) {
			$name      = sanitize_text_field( $data['name'] ?? '' );
			$slug      = sanitize_title( $data['slug'] ?? '' );
			$code      = sanitize_text_field( $data['code'] ?? '' );
			$type      = $data['type'] ?? 'province';
			$parent_id = ! empty( $data['parent_id'] ) ? absint( $data['parent_id'] ) : null;
			$level     = absint( $data['level'] ?? 1 );

			if ( empty( $name ) || ! PSP_Territorial_Utils::is_valid_type( $type ) ) {
				++$errors;
				continue;
			}

			if ( null === $parent_id ) {
				$batch_placeholders[] = "(%s, %s, %s, %s, NULL, %d, 1)";
				array_push( $batch_values, $name, $slug, $code, $type, $level );
			} else {
				$batch_placeholders[] = "(%s, %s, %s, %s, %d, %d, 1)";
				array_push( $batch_values, $name, $slug, $code, $type, $parent_id, $level );
			}

			++$count;

			// Flush in batches of 200 for memory efficiency.
			if ( $count % 200 === 0 ) {
				$this->flush_batch( $table, $batch_placeholders, $batch_values );
				$batch_placeholders = array();
				$batch_values       = array();
			}
		}

		// Flush remaining rows.
		if ( ! empty( $batch_placeholders ) ) {
			$this->flush_batch( $table, $batch_placeholders, $batch_values );
		}

		$summary = array(
			'success' => true,
			'count'   => $count,
			'errors'  => $errors,
			'message' => sprintf(
				/* translators: 1: Number of records imported. */
				__( '%d registros importados correctamente.', 'psp-territorial' ),
				$count
			),
			'log'     => $this->log,
		);

		$this->log( $summary['message'] );

		return $summary;
	}

	/**
	 * Flush a batch of prepared insert rows.
	 *
	 * @param string $table        Full table name.
	 * @param array  $placeholders Array of row placeholder strings.
	 * @param array  $values       Array of values matching placeholders.
	 */
	private function flush_batch( $table, array $placeholders, array $values ) {
		global $wpdb;
		if ( empty( $placeholders ) ) {
			return;
		}
		$sql = "INSERT INTO {$table} (name,slug,code,type,parent_id,level,is_active) VALUES " // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			. implode( ',', $placeholders );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( $wpdb->prepare( $sql, $values ) );
	}

	/**
	 * Clean a CSV field value.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function clean_field( $value ) {
		$value = trim( $value );
		$value = mb_convert_encoding( $value, 'UTF-8', 'ISO-8859-1' );
		return $value;
	}

	/**
	 * Add a message to the import log.
	 *
	 * @param string $message Log message.
	 */
	private function log( $message ) {
		$this->log[] = array(
			'time'    => current_time( 'mysql' ),
			'message' => $message,
		);
	}

	/**
	 * Get the import log.
	 *
	 * @return array
	 */
	public function get_log() {
		return $this->log;
	}
}
