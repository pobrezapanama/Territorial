<?php
/**
 * Importer – PSP Territorial V2
 *
 * Reads panama_full_geography.clean.json and inserts into
 * {prefix}psp_territorial_v2 using the explicit ids from the JSON.
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_Importer
 */
class PSPV2_Importer {

	/** @var PSPV2_Database */
	private $db;

	/** @var array Import log. */
	private $log = array();

	/** @var int Counters. */
	private $inserted  = 0;
	private $skipped   = 0;
	private $errors    = 0;

	/**
	 * Constructor.
	 *
	 * @param PSPV2_Database $db
	 */
	public function __construct( PSPV2_Database $db ) {
		$this->db = $db;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Import from the clean JSON file.
	 *
	 * @param string $json_path Absolute path to file.
	 * @param bool   $truncate  Truncate table before import.
	 * @return array Result summary.
	 */
	public function import_from_file( $json_path, $truncate = false ) {
		if ( ! file_exists( $json_path ) ) {
			return $this->result( false, sprintf( 'JSON file not found: %s', $json_path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $json_path );
		if ( false === $json ) {
			return $this->result( false, 'Could not read JSON file.' );
		}

		$decoded = json_decode( $json, true );
		if ( null === $decoded || ! is_array( $decoded ) ) {
			return $this->result( false, 'Invalid or corrupt JSON.' );
		}

		// Accept flat array or {"data": [...]} wrapper.
		$data = isset( $decoded['data'] ) ? $decoded['data'] : $decoded;
		if ( ! is_array( $data ) || empty( $data ) ) {
			return $this->result( false, 'No data found in JSON.' );
		}

		return $this->run_import( $data, $truncate );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Execute the import process.
	 *
	 * @param array $data     Flat array of territory records.
	 * @param bool  $truncate Truncate before import.
	 * @return array
	 */
	private function run_import( array $data, $truncate ) {
		global $wpdb;

		// Ensure table exists.
		if ( ! $this->db->table_exists() ) {
			$this->db->create_tables();
		}

		if ( $truncate ) {
			$this->db->truncate();
			$this->log( 'info', 'Table truncated.' );
		}

		// Sort by level to insert parents before children.
		usort( $data, function( $a, $b ) {
			return (int) $a['level'] - (int) $b['level'];
		} );

		// Build an id→type map for quick parent validation.
		$id_type_map = array();
		foreach ( $data as $record ) {
			if ( isset( $record['id'], $record['type'] ) ) {
				$id_type_map[ (int) $record['id'] ] = $record['type'];
			}
		}

		$this->inserted = 0;
		$this->skipped  = 0;
		$this->errors   = 0;

		// Disable autocommit for batch performance.
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		foreach ( $data as $record ) {
			$this->import_record( $record, $id_type_map );
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		$message = sprintf(
			'Import complete. Inserted: %d, Skipped: %d, Errors: %d',
			$this->inserted,
			$this->skipped,
			$this->errors
		);
		$this->log( 'success', $message );

		return $this->result( true, $message );
	}

	/**
	 * Validate and insert a single record.
	 *
	 * @param array $record    Raw record from JSON.
	 * @param array $id_type_map id => type for validation.
	 */
	private function import_record( array $record, array $id_type_map ) {
		// Required fields.
		if ( empty( $record['id'] ) || empty( $record['name'] ) || empty( $record['type'] ) ) {
			$this->log( 'warning', sprintf( 'Skipping record with missing required fields: %s', wp_json_encode( $record ) ) );
			$this->skipped++;
			return;
		}

		$id        = (int) $record['id'];
		$name      = sanitize_text_field( $record['name'] );
		$slug      = sanitize_title( isset( $record['slug'] ) ? $record['slug'] : $name );
		$code      = sanitize_text_field( isset( $record['code'] ) ? $record['code'] : '' );
		$type      = sanitize_text_field( $record['type'] );
		$parent_id = isset( $record['parent_id'] ) && '' !== $record['parent_id'] && null !== $record['parent_id']
			? (int) $record['parent_id']
			: null;
		$level     = isset( $record['level'] ) ? (int) $record['level'] : $this->level_for_type( $type );

		// Validate type.
		if ( ! in_array( $type, PSPV2_Database::TYPES, true ) ) {
			$this->log( 'warning', "Unknown type '{$type}' for id={$id}, skipping." );
			$this->skipped++;
			return;
		}

		// Strict parent validation.
		$expected_parent_type = PSPV2_Database::PARENT_TYPE[ $type ] ?? null;

		// Provinces MUST have no parent.
		if ( 'province' === $type ) {
			if ( null !== $parent_id ) {
				$this->log( 'error', "id={$id} (province) must have parent_id=NULL — skipping." );
				$this->errors++;
				return;
			}
		} else {
			// Non-provinces MUST have a parent.
			if ( null === $parent_id ) {
				$this->log( 'error', "id={$id} ({$type}) must have a parent_id — skipping." );
				$this->errors++;
				return;
			}

			// Parent must exist in the dataset.
			if ( ! isset( $id_type_map[ $parent_id ] ) ) {
				$this->log( 'error', "id={$id} ({$type}) has parent_id={$parent_id} not found in data — skipping." );
				$this->errors++;
				return;
			}

			// Parent must be of the expected type.
			if ( $expected_parent_type && $id_type_map[ $parent_id ] !== $expected_parent_type ) {
				$this->log(
					'error',
					sprintf(
						"id=%d (%s) has parent id=%d of type '%s' (expected '%s') — skipping.",
						$id,
						$type,
						$parent_id,
						$id_type_map[ $parent_id ],
						$expected_parent_type
					)
				);
				$this->errors++;
				return;
			}
		}

		$row = array(
			'id'        => $id,
			'name'      => $name,
			'slug'      => $slug,
			'code'      => $code,
			'type'      => $type,
			'parent_id' => $parent_id,
			'level'     => $level,
			'is_active' => 1,
		);

		$result = $this->db->insert( $row );

		if ( false === $result ) {
			global $wpdb;
			$this->log( 'error', "Failed to insert id={$id}: " . $wpdb->last_error );
			$this->errors++;
		} elseif ( 0 === $result ) {
			// INSERT IGNORE: row already existed.
			$this->skipped++;
		} else {
			$this->inserted++;
		}
	}

	/**
	 * Default level for a type.
	 *
	 * @param string $type
	 * @return int
	 */
	private function level_for_type( $type ) {
		$map = array(
			'province'      => 1,
			'district'      => 2,
			'corregimiento' => 3,
			'community'     => 4,
		);
		return $map[ $type ] ?? 1;
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $level   info|success|warning|error
	 * @param string $message
	 */
	private function log( $level, $message ) {
		$this->log[] = array(
			'level'   => $level,
			'time'    => current_time( 'H:i:s' ),
			'message' => $message,
		);
	}

	/**
	 * Build a standardised result array.
	 *
	 * @param bool   $success
	 * @param string $message
	 * @return array
	 */
	private function result( $success, $message ) {
		return array(
			'success'  => $success,
			'message'  => $message,
			'inserted' => $this->inserted,
			'skipped'  => $this->skipped,
			'errors'   => $this->errors,
			'log'      => $this->log,
		);
	}
}
