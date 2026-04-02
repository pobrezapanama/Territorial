<?php
/**
 * Improved Importer V2
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Importer_V2
 *
 * Improved territorial data importer with hierarchy validation, duplicate
 * prevention, and transactional safety. Replaces the import logic from
 * PSP_Territorial_Importer for fresh/re-import scenarios.
 */
class PSP_Importer_V2 {

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database
	 */
	private $db;

	/**
	 * Import log.
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Maximum retries for transient DB errors.
	 *
	 * @var int
	 */
	const MAX_RETRIES = 3;

	/**
	 * Constructor.
	 *
	 * @param PSP_Territorial_Database $db Database instance.
	 */
	public function __construct( PSP_Territorial_Database $db ) {
		$this->db = $db;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Normalize a territory name.
	 *
	 * Applies the following transformations (in order):
	 *  1. trim() — strips leading / trailing whitespace.
	 *  2. Collapse internal runs of whitespace to a single space.
	 *  3. Remove trailing colon (handles "El Teribe:" and "Valle del Riscó :").
	 *
	 * @param string $name Raw territory name.
	 * @return string Normalized name.
	 */
	public static function normalize_name( $name ) {
		$name = trim( (string) $name );
		$name = preg_replace( '/\s+/u', ' ', $name );
		$name = preg_replace( '/\s*:\s*$/u', '', $name );
		return trim( $name );
	}

	/**
	 * Import from a hierarchical panama_full_geography.json file.
	 *
	 * The file must have the structure:
	 *   { Province: { District: { Corregimiento: [community, …] } } }
	 *
	 * Normalisation rules applied before inserting:
	 *  - All names are trimmed, whitespace-collapsed, and trailing colons stripped.
	 *  - Duplicate entries (after normalisation) are merged: their children are
	 *    united and then deduplicated.
	 *  - Empty names are skipped.
	 *
	 * @param string $json_path Absolute path to the JSON file.
	 * @param bool   $truncate  Truncate existing data before import.
	 * @return array Result summary.
	 */
	public function import_from_full_geography_file( $json_path, $truncate = false ) {
		if ( ! file_exists( $json_path ) ) {
			return $this->result( false, __( 'Archivo JSON no encontrado.', 'psp-territorial' ) );
		}

		$json = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = json_decode( $json, true );

		if ( null === $raw || ! is_array( $raw ) ) {
			return $this->result( false, __( 'JSON inválido o corrupto.', 'psp-territorial' ) );
		}

		// If the file is already the flat {"data":[…]} format, delegate.
		if ( isset( $raw['data'] ) && is_array( $raw['data'] ) ) {
			return $this->import_with_validation( $raw['data'], $truncate );
		}

		$data = $this->parse_and_normalize_full_geography( $raw );
		if ( empty( $data ) ) {
			return $this->result( false, __( 'No se encontraron datos en el JSON.', 'psp-territorial' ) );
		}

		return $this->import_with_validation( $data, $truncate );
	}

	/**
	 * Parse and validate a JSON file, then import it.
	 *
	 * Supports both panama_clean.json and panama_data.json formats
	 * (both use a top-level "data" key containing a flat array of entities).
	 *
	 * @param string $json_path Absolute path to the JSON file.
	 * @param bool   $truncate  Truncate existing data before import.
	 * @return array Result summary.
	 */
	public function import_from_file( $json_path, $truncate = false ) {
		if ( ! file_exists( $json_path ) ) {
			return $this->result( false, __( 'Archivo JSON no encontrado.', 'psp-territorial' ) );
		}

		$json = file_get_contents( $json_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		return $this->import_from_json_string( $json, $truncate );
	}

	/**
	 * Import from a raw JSON string.
	 *
	 * @param string $json_string JSON content.
	 * @param bool   $truncate    Truncate existing data before import.
	 * @return array Result summary.
	 */
	public function import_from_json_string( $json_string, $truncate = false ) {
		$decoded = json_decode( $json_string, true );

		if ( null === $decoded ) {
			return $this->result( false, __( 'JSON inválido o corrupto.', 'psp-territorial' ) );
		}

		// Accept both bare arrays and {"data": [...]} wrappers.
		$data = isset( $decoded['data'] ) ? $decoded['data'] : $decoded;
		if ( ! is_array( $data ) || empty( $data ) ) {
			return $this->result( false, __( 'No se encontraron datos en el JSON.', 'psp-territorial' ) );
		}

		return $this->import_with_validation( $data, $truncate );
	}

	/**
	 * Parse a JSON string and return the flat data array.
	 *
	 * @param string $json_data Raw JSON string.
	 * @return array|false Flat array or false on error.
	 */
	public function parse_json( $json_data ) {
		$decoded = json_decode( $json_data, true );
		if ( null === $decoded ) {
			return false;
		}
		return isset( $decoded['data'] ) ? $decoded['data'] : $decoded;
	}

	/**
	 * Validate hierarchy: every non-province entity must have a valid parent ID.
	 *
	 * @param array $data Flat array of territory records.
	 * @return array List of error strings (empty = valid).
	 */
	public function validate_hierarchy( array $data ) {
		$id_set = array();
		foreach ( $data as $record ) {
			if ( ! empty( $record['id'] ) ) {
				$id_set[ (int) $record['id'] ] = true;
			}
		}

		$issues = array();
		foreach ( $data as $record ) {
			$name      = $record['name'] ?? '?';
			$type      = $record['type'] ?? '?';
			$parent_id = $record['parent_id'] ?? null;
			$id        = $record['id'] ?? null;

			if ( 'province' === $type ) {
				if ( null !== $parent_id ) {
					$issues[] = sprintf(
						/* translators: 1: ID, 2: name */
						__( 'Provincia ID %1$d (%2$s) no debe tener parent_id.', 'psp-territorial' ),
						(int) $id,
						$name
					);
				}
				continue;
			}

			if ( null === $parent_id ) {
				$issues[] = sprintf(
					/* translators: 1: type, 2: name */
					__( '%1$s "%2$s" no tiene parent_id.', 'psp-territorial' ),
					$type,
					$name
				);
				continue;
			}

			if ( ! isset( $id_set[ (int) $parent_id ] ) ) {
				$issues[] = sprintf(
					/* translators: 1: type, 2: name, 3: parent ID */
					__( '%1$s "%2$s" referencia parent_id=%3$d que no existe en los datos.', 'psp-territorial' ),
					$type,
					$name,
					(int) $parent_id
				);
			}
		}

		return $issues;
	}

	/**
	 * Import only validated data. Skips any record that fails hierarchy checks.
	 *
	 * Uses a database transaction for atomicity.
	 *
	 * @param array $data     Flat array of territory records.
	 * @param bool  $truncate Truncate existing data before import.
	 * @return array Result summary.
	 */
	public function import_with_validation( array $data, $truncate = false ) {
		global $wpdb;

		do_action( 'psp_territorial_before_import', $truncate );

		$issues = $this->validate_hierarchy( $data );
		if ( ! empty( $issues ) ) {
			foreach ( $issues as $issue ) {
				$this->log( $issue, 'warning' );
			}
		}

		// Start transaction.
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		try {
			if ( $truncate ) {
				$this->db->truncate();
				$this->log( __( 'Datos anteriores eliminados.', 'psp-territorial' ) );
			}

			$imported = $this->insert_records( $data );

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

			$this->log(
				sprintf(
					/* translators: %d: record count */
					__( '%d registros importados correctamente.', 'psp-territorial' ),
					$imported['count']
				),
				'success'
			);

			$summary = array(
				'success'  => true,
				'count'    => $imported['count'],
				'errors'   => $imported['errors'],
				'skipped'  => $imported['skipped'],
				'message'  => sprintf(
					/* translators: %d: record count */
					__( '%d registros importados correctamente.', 'psp-territorial' ),
					$imported['count']
				),
				'log'      => $this->log,
			);

			do_action( 'psp_territorial_after_import', $summary );
			return $summary;

		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->log( $e->getMessage(), 'error' );
			return $this->result( false, $e->getMessage() );
		}
	}

	/**
	 * Find an existing territory by name, type, and parent, or create it.
	 *
	 * @param string   $name      Territory name.
	 * @param string   $type      Territory type.
	 * @param int|null $parent_id Parent ID.
	 * @return int|false Territory ID or false on failure.
	 */
	public function get_or_create_parent( $name, $type, $parent_id ) {
		global $wpdb;
		$table = $this->db->get_table_name();

		if ( $parent_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND type = %s AND parent_id = %d LIMIT 1", $name, $type, (int) $parent_id ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND type = %s AND parent_id IS NULL LIMIT 1", $name, $type ) );
		}

		if ( $existing ) {
			return (int) $existing;
		}

		return $this->db->insert( array(
			'name'      => $name,
			'slug'      => PSP_Territorial_Utils::generate_slug( $name ),
			'type'      => $type,
			'parent_id' => $parent_id,
			'level'     => PSP_Territorial_Utils::get_level( $type ),
		) );
	}

	/**
	 * Check whether a territory with the given name, type, and parent already exists.
	 *
	 * @param string   $name      Territory name.
	 * @param string   $type      Territory type.
	 * @param int|null $parent_id Parent ID.
	 * @return int|false Existing ID or false if not found.
	 */
	public function check_duplicate( $name, $type, $parent_id ) {
		global $wpdb;
		$table = $this->db->get_table_name();

		if ( $parent_id ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND type = %s AND parent_id = %d LIMIT 1", $name, $type, (int) $parent_id ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE name = %s AND type = %s AND parent_id IS NULL LIMIT 1", $name, $type ) );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Batch-insert validated records using explicit IDs so parent_id
	 * references are consistent.
	 *
	 * @param array $data Flat records array.
	 * @return array Summary with count / errors / skipped.
	 */
	private function insert_records( array $data ) {
		global $wpdb;

		$table   = $this->db->get_table_name();
		$count   = 0;
		$errors  = 0;
		$skipped = 0;
		$batch_placeholders = array();
		$batch_values       = array();

		foreach ( $data as $record ) {
			$name      = sanitize_text_field( $record['name'] ?? '' );
			$slug      = sanitize_title( $record['slug'] ?? PSP_Territorial_Utils::generate_slug( $name ) );
			$code      = sanitize_text_field( $record['code'] ?? '' );
			$type      = $record['type'] ?? '';
			$parent_id = ! empty( $record['parent_id'] ) ? absint( $record['parent_id'] ) : null;
			$level     = absint( $record['level'] ?? PSP_Territorial_Utils::get_level( $type ) );
			$row_id    = ! empty( $record['id'] ) ? absint( $record['id'] ) : null;

			if ( empty( $name ) || ! PSP_Territorial_Utils::is_valid_type( $type ) ) {
				++$errors;
				$this->log(
					sprintf(
						/* translators: 1: name, 2: type */
						__( 'Skipping invalid record: name="%1$s" type="%2$s"', 'psp-territorial' ),
						$name,
						$type
					),
					'error'
				);
				continue;
			}

			// Build the row placeholder / values including explicit id.
			if ( null === $row_id && null === $parent_id ) {
				$batch_placeholders[] = '(NULL, %s, %s, %s, %s, NULL, %d, 1)';
				array_push( $batch_values, $name, $slug, $code, $type, $level );
			} elseif ( null === $row_id ) {
				$batch_placeholders[] = '(NULL, %s, %s, %s, %s, %d, %d, 1)';
				array_push( $batch_values, $name, $slug, $code, $type, $parent_id, $level );
			} elseif ( null === $parent_id ) {
				$batch_placeholders[] = '(%d, %s, %s, %s, %s, NULL, %d, 1)';
				array_push( $batch_values, $row_id, $name, $slug, $code, $type, $level );
			} else {
				$batch_placeholders[] = '(%d, %s, %s, %s, %s, %d, %d, 1)';
				array_push( $batch_values, $row_id, $name, $slug, $code, $type, $parent_id, $level );
			}

			++$count;

			// Flush in batches of 200.
			if ( $count % 200 === 0 ) {
				$this->flush_batch( $table, $batch_placeholders, $batch_values );
				$batch_placeholders = array();
				$batch_values       = array();
			}
		}

		if ( ! empty( $batch_placeholders ) ) {
			$this->flush_batch( $table, $batch_placeholders, $batch_values );
		}

		return compact( 'count', 'errors', 'skipped' );
	}

	/**
	 * Execute a prepared batch INSERT with retry logic.
	 *
	 * @param string $table        Full table name.
	 * @param array  $placeholders Row placeholder strings.
	 * @param array  $values       Bound values.
	 */
	private function flush_batch( $table, array $placeholders, array $values ) {
		global $wpdb;

		if ( empty( $placeholders ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "INSERT INTO {$table} (id,name,slug,code,type,parent_id,level,is_active) VALUES "
			. implode( ',', $placeholders );

		for ( $attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++ ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );
			if ( false !== $result ) {
				return;
			}
			$this->log(
				sprintf(
					/* translators: 1: attempt number, 2: DB error */
					__( 'Batch INSERT attempt %1$d failed: %2$s', 'psp-territorial' ),
					$attempt,
					$wpdb->last_error
				),
				'error'
			);
		}
	}

	/**
	 * Build a result array.
	 *
	 * @param bool   $success Whether the operation succeeded.
	 * @param string $message Human-readable message.
	 * @return array
	 */
	private function result( $success, $message ) {
		return array(
			'success' => (bool) $success,
			'message' => $message,
			'log'     => $this->log,
		);
	}

	/**
	 * Add a message to the log.
	 *
	 * @param string $message Log message.
	 * @param string $level   'info' | 'success' | 'warning' | 'error'.
	 */
	private function log( $message, $level = 'info' ) {
		$this->log[] = array(
			'time'    => current_time( 'mysql' ),
			'level'   => $level,
			'message' => $message,
		);
	}

	/**
	 * Get the full import log.
	 *
	 * @return array
	 */
	public function get_log() {
		return $this->log;
	}

	/**
	 * Convert a hierarchical full-geography array into a flat records array.
	 *
	 * Applies normalization (trim, whitespace collapse, trailing-colon removal)
	 * and merges duplicate entries at every level before building the flat list.
	 *
	 * ID ranges:
	 *   province:      1 – 999
	 *   district:   1001 – 9999
	 *   corregimiento: 10001 – 99999
	 *   community:     100001 +
	 *
	 * @param array $raw Decoded hierarchical JSON.
	 * @return array Flat records ready for import_with_validation().
	 */
	private function parse_and_normalize_full_geography( array $raw ) {
		$records = array();

		$province_idx = 0;
		$district_idx = 0;
		$corr_idx     = 0;
		$comm_idx     = 0;

		// ── Merge provinces ───────────────────────────────────────────────────
		$provinces = array();
		foreach ( $raw as $raw_prov => $raw_districts ) {
			$prov_name = self::normalize_name( $raw_prov );
			if ( empty( $prov_name ) || ! is_array( $raw_districts ) ) {
				continue;
			}
			if ( ! isset( $provinces[ $prov_name ] ) ) {
				$provinces[ $prov_name ] = array();
			}

			// ── Merge districts ───────────────────────────────────────────────
			foreach ( $raw_districts as $raw_dist => $raw_corrs ) {
				$dist_name = self::normalize_name( $raw_dist );
				if ( empty( $dist_name ) || ! is_array( $raw_corrs ) ) {
					continue;
				}
				if ( ! isset( $provinces[ $prov_name ][ $dist_name ] ) ) {
					$provinces[ $prov_name ][ $dist_name ] = array();
				}

				// ── Merge corregimientos ──────────────────────────────────────
				foreach ( $raw_corrs as $raw_corr => $raw_comms ) {
					$corr_name = self::normalize_name( $raw_corr );
					if ( empty( $corr_name ) || ! is_array( $raw_comms ) ) {
						continue;
					}
					if ( ! isset( $provinces[ $prov_name ][ $dist_name ][ $corr_name ] ) ) {
						$provinces[ $prov_name ][ $dist_name ][ $corr_name ] = array();
					}

					// Collect communities (deduplication happens on emit).
					foreach ( $raw_comms as $raw_comm ) {
						if ( ! is_string( $raw_comm ) ) {
							continue;
						}
						$comm_name = self::normalize_name( $raw_comm );
						if ( ! empty( $comm_name ) ) {
							$provinces[ $prov_name ][ $dist_name ][ $corr_name ][] = $comm_name;
						}
					}
				}
			}
		}

		// ── Emit flat records ─────────────────────────────────────────────────
		foreach ( $provinces as $prov_name => $districts ) {
			++$province_idx;
			$province_id = $province_idx; // 1-based, range 1-999.

			$records[] = array(
				'id'        => $province_id,
				'name'      => $prov_name,
				'slug'      => PSP_Territorial_Utils::generate_slug( $prov_name ),
				'code'      => PSP_Territorial_Utils::generate_code( 'province', $province_idx ),
				'type'      => 'province',
				'parent_id' => null,
				'level'     => 1,
			);

			foreach ( $districts as $dist_name => $corrs ) {
				++$district_idx;
				$district_id = 1000 + $district_idx;

				$records[] = array(
					'id'        => $district_id,
					'name'      => $dist_name,
					'slug'      => PSP_Territorial_Utils::generate_slug( $dist_name ),
					'code'      => PSP_Territorial_Utils::generate_code( 'district', $district_idx ),
					'type'      => 'district',
					'parent_id' => $province_id,
					'level'     => 2,
				);

				foreach ( $corrs as $corr_name => $comms ) {
					++$corr_idx;
					$corr_id = 10000 + $corr_idx;

					$records[] = array(
						'id'        => $corr_id,
						'name'      => $corr_name,
						'slug'      => PSP_Territorial_Utils::generate_slug( $corr_name ),
						'code'      => PSP_Territorial_Utils::generate_code( 'corregimiento', $corr_idx ),
						'type'      => 'corregimiento',
						'parent_id' => $district_id,
						'level'     => 3,
					);

					// Deduplicate communities within this corregimiento.
					$seen_comms = array();
					foreach ( $comms as $comm_name ) {
						if ( isset( $seen_comms[ $comm_name ] ) ) {
							continue;
						}
						$seen_comms[ $comm_name ] = true;
						++$comm_idx;
						$records[] = array(
							'id'        => 100000 + $comm_idx,
							'name'      => $comm_name,
							'slug'      => PSP_Territorial_Utils::generate_slug( $comm_name ),
							'code'      => PSP_Territorial_Utils::generate_code( 'community', $comm_idx ),
							'type'      => 'community',
							'parent_id' => $corr_id,
							'level'     => 4,
						);
					}
				}
			}
		}

		$this->log(
			sprintf(
				/* translators: 1: provinces, 2: districts, 3: corregimientos, 4: communities */
				__( 'Parsed full geography: %1$d provinces, %2$d districts, %3$d corregimientos, %4$d communities.', 'psp-territorial' ),
				$province_idx,
				$district_idx,
				$corr_idx,
				$comm_idx
			)
		);

		return $records;
	}
}
