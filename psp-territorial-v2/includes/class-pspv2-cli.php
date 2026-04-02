<?php
/**
 * WP-CLI Commands – PSP Territorial V2
 *
 * Usage:
 *   wp psp territorial-v2 import [--truncate] [--json=<path>]
 *   wp psp territorial-v2 verify
 *   wp psp territorial-v2 stats
 *
 * @package PSP_Territorial_V2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSPV2_CLI
 */
class PSPV2_CLI {

	/** @var PSPV2_Database */
	private $db;

	/** Constructor. */
	public function __construct() {
		$this->db = new PSPV2_Database();
	}

	// -------------------------------------------------------------------------
	// Commands
	// -------------------------------------------------------------------------

	/**
	 * Import territorial data (truncate + reimport or incremental).
	 *
	 * ## OPTIONS
	 *
	 * [--truncate]
	 * : Truncate the table before importing. Recommended for a clean reimport.
	 *
	 * [--json=<path>]
	 * : Custom path to the clean JSON file.
	 * : Default: <plugin>/assets/data/panama_full_geography.clean.json
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial-v2 import --truncate
	 *   wp psp territorial-v2 import --json=/tmp/my_data.json --truncate
	 *
	 * @subcommand import
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function import( $args, $assoc_args ) {
		$truncate  = WP_CLI\Utils\get_flag_value( $assoc_args, 'truncate', false );
		$json_path = WP_CLI\Utils\get_flag_value( $assoc_args, 'json', null );

		if ( ! $json_path ) {
			$json_path = PSPV2_PLUGIN_DIR . 'assets/data/panama_full_geography.clean.json';
		}

		if ( ! file_exists( $json_path ) ) {
			WP_CLI::error( 'JSON file not found: ' . $json_path );
		}

		WP_CLI::log( 'Source: ' . $json_path );

		// Ensure table exists.
		if ( ! $this->db->table_exists() ) {
			WP_CLI::log( 'Table not found — creating…' );
			$this->db->create_tables();
		}

		$importer = new PSPV2_Importer( $this->db );
		$result   = $importer->import_from_file( $json_path, (bool) $truncate );

		// Print log lines.
		foreach ( $result['log'] as $entry ) {
			if ( in_array( $entry['level'], array( 'warning', 'error' ), true ) ) {
				WP_CLI::warning( '[' . $entry['time'] . '] ' . $entry['message'] );
			} else {
				WP_CLI::log( $entry['message'] );
			}
		}

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}
	}

	/**
	 * Verify data integrity (orphans, invalid parent types).
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial-v2 verify
	 *
	 * @subcommand verify
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function verify( $args, $assoc_args ) {
		if ( ! $this->db->table_exists() ) {
			WP_CLI::error( 'Table does not exist. Activate the plugin first.' );
		}

		// Stats.
		$stats = $this->db->get_stats();
		WP_CLI::log( '── Entity counts ─────────────────────────' );
		foreach ( $stats as $type => $cnt ) {
			WP_CLI::log( sprintf( '  %-20s %6d', $type, $cnt ) );
		}

		// Orphans.
		$orphans = $this->db->get_orphans();
		WP_CLI::log( '' );
		WP_CLI::log( '── Orphaned entities ─────────────────────' );
		if ( empty( $orphans ) ) {
			WP_CLI::success( '  None — hierarchy is intact.' );
		} else {
			WP_CLI::warning( '  Found ' . count( $orphans ) . ' orphaned entities:' );
			foreach ( $orphans as $o ) {
				WP_CLI::log( sprintf( '  id=%-8d type=%-15s parent_id=%d  name="%s"', $o->id, $o->type, $o->parent_id, $o->name ) );
			}
		}

		// Invalid parent types.
		$invalid = $this->db->get_invalid_parents();
		WP_CLI::log( '' );
		WP_CLI::log( '── Invalid parent types ──────────────────' );
		if ( empty( $invalid ) ) {
			WP_CLI::success( '  None — all parent types are correct.' );
		} else {
			WP_CLI::warning( '  Found ' . count( $invalid ) . ' item(s) with wrong parent type:' );
			foreach ( $invalid as $i ) {
				WP_CLI::log( sprintf(
					'  id=%-8d type=%-15s parent_id=%-8d parent_type=%s  name="%s"',
					$i->id, $i->type, $i->parent_id, $i->parent_type, $i->name
				) );
			}
		}
	}

	/**
	 * Show import statistics.
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial-v2 stats
	 *
	 * @subcommand stats
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function stats( $args, $assoc_args ) {
		if ( ! $this->db->table_exists() ) {
			WP_CLI::error( 'Table does not exist. Activate the plugin first.' );
		}

		$stats = $this->db->get_stats();
		WP_CLI::log( '── PSP Territorial V2 Statistics ─────────' );
		$total = 0;
		$labels = array(
			'province'      => 'Provincias',
			'district'      => 'Distritos',
			'corregimiento' => 'Corregimientos',
			'community'     => 'Comunidades',
		);
		foreach ( $labels as $type => $label ) {
			$cnt    = $stats[ $type ] ?? 0;
			$total += $cnt;
			WP_CLI::log( sprintf( '  %-20s %6d', $label, $cnt ) );
		}
		WP_CLI::log( '  ─────────────────────────────────────────' );
		WP_CLI::log( sprintf( '  %-20s %6d', 'TOTAL', $total ) );

		// Quick hierarchy check.
		$orphans = $this->db->get_orphans();
		$invalid = $this->db->get_invalid_parents();
		WP_CLI::log( '' );
		if ( empty( $orphans ) && empty( $invalid ) ) {
			WP_CLI::success( 'Hierarchy is intact — no issues found.' );
		} else {
			WP_CLI::warning( sprintf( 'Issues: %d orphans, %d invalid parent types. Run: wp psp territorial-v2 verify', count( $orphans ), count( $invalid ) ) );
		}
	}
}

// Register commands when WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'psp territorial-v2', 'PSPV2_CLI' );
}
