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
}
