<?php
/**
 * WP-CLI Commands for PSP Territorial
 *
 * @package PSP_Territorial
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PSP_Territorial_CLI
 *
 * Registers WP-CLI sub-commands under the "psp territorial" namespace.
 *
 * Available commands:
 *   wp psp territorial clean-json   — Generate panama_clean.json from the CSV
 *   wp psp territorial import-clean — Import the clean JSON (repairs first)
 *   wp psp territorial verify       — Verify data integrity
 *   wp psp territorial repair       — Repair broken / orphan data
 *   wp psp territorial stats        — Show import statistics
 */
class PSP_Territorial_CLI {

	/**
	 * Database instance.
	 *
	 * @var PSP_Territorial_Database
	 */
	private $db;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->db = new PSP_Territorial_Database();
	}

	// -------------------------------------------------------------------------
	// Commands
	// -------------------------------------------------------------------------

	/**
	 * Generate panama_clean.json from the raw CSV via the Python script.
	 *
	 * ## OPTIONS
	 *
	 * [--csv=<path>]
	 * : Path to the source CSV file.
	 * default: (repo root)/panama_provinces_districts_coerregimientos_communities.csv
	 *
	 * [--out=<path>]
	 * : Output JSON path.
	 * default: assets/data/panama_clean.json inside the plugin folder.
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial clean-json
	 *   wp psp territorial clean-json --csv=/tmp/my_data.csv
	 *
	 * @subcommand clean-json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clean_json( $args, $assoc_args ) {
		$script = PSP_TERRITORIAL_PLUGIN_DIR . 'scripts/clean_csv_to_json.py';

		if ( ! file_exists( $script ) ) {
			WP_CLI::error( 'Python script not found: ' . $script );
		}

		$cmd_parts = array( 'python3', escapeshellarg( $script ) );

		if ( ! empty( $assoc_args['csv'] ) ) {
			$cmd_parts[] = '--csv ' . escapeshellarg( $assoc_args['csv'] );
		}
		if ( ! empty( $assoc_args['out'] ) ) {
			$cmd_parts[] = '--out ' . escapeshellarg( $assoc_args['out'] );
		}

		$cmd    = implode( ' ', $cmd_parts );
		$output = array();
		$exit   = 0;
		exec( $cmd . ' 2>&1', $output, $exit ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec

		foreach ( $output as $line ) {
			if ( 0 === $exit || 2 === $exit ) {
				WP_CLI::line( $line );
			} else {
				WP_CLI::warning( $line );
			}
		}

		if ( $exit > 2 ) {
			WP_CLI::error( 'Python script exited with code ' . $exit );
		} elseif ( 2 === $exit ) {
			WP_CLI::warning( 'JSON generated with warnings — check output above.' );
		} else {
			WP_CLI::success( 'panama_clean.json generated successfully.' );
		}
	}

	/**
	 * Import clean JSON data (runs repair first on existing data).
	 *
	 * ## OPTIONS
	 *
	 * [--truncate]
	 * : Truncate existing data before import.
	 *
	 * [--json=<path>]
	 * : Custom JSON file path (defaults to panama_clean.json, then panama_data.json).
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial import-clean
	 *   wp psp territorial import-clean --truncate
	 *
	 * @subcommand import-clean
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import_clean( $args, $assoc_args ) {
		$truncate = WP_CLI\Utils\get_flag_value( $assoc_args, 'truncate', false );

		// Determine JSON path.
		if ( ! empty( $assoc_args['json'] ) ) {
			$json_path = $assoc_args['json'];
		} else {
			$clean_path  = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_clean.json';
			$legacy_path = PSP_TERRITORIAL_PLUGIN_DIR . 'assets/data/panama_data.json';
			$json_path   = file_exists( $clean_path ) ? $clean_path : $legacy_path;
		}

		if ( ! file_exists( $json_path ) ) {
			WP_CLI::error( 'JSON file not found: ' . $json_path );
		}

		WP_CLI::log( 'Using JSON: ' . $json_path );

		// Run repair on existing data first (unless truncating).
		if ( ! $truncate && $this->db->tables_exist() ) {
			WP_CLI::log( 'Running data repair before import…' );
			$this->run_repair( /* silent */ true );
		}

		// Import.
		require_once PSP_TERRITORIAL_PLUGIN_DIR . 'includes/class-psp-importer-v2.php';
		$importer = new PSP_Importer_V2( $this->db );
		$result   = $importer->import_from_file( $json_path, (bool) $truncate );

		if ( $result['success'] ) {
			WP_CLI::success( $result['message'] );
		} else {
			WP_CLI::error( $result['message'] );
		}

		// Show any warnings from the log.
		foreach ( $result['log'] as $entry ) {
			if ( 'error' === $entry['level'] || 'warning' === $entry['level'] ) {
				WP_CLI::warning( '[' . $entry['time'] . '] ' . $entry['message'] );
			}
		}
	}

	/**
	 * Verify data integrity and report any issues.
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial verify
	 *
	 * @subcommand verify
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function verify( $args, $assoc_args ) {
		global $wpdb;

		if ( ! $this->db->tables_exist() ) {
			WP_CLI::error( 'PSP Territorial table does not exist. Activate the plugin first.' );
		}

		$table = $this->db->get_table_name();

		// Counts per type.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$counts = $wpdb->get_results( "SELECT type, COUNT(*) AS cnt FROM {$table} GROUP BY type" );
		WP_CLI::log( '── Entity counts ─────────────────────────' );
		foreach ( $counts as $row ) {
			WP_CLI::log( sprintf( '  %-20s %6d', $row->type, (int) $row->cnt ) );
		}

		// Orphans.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$orphans = $wpdb->get_results(
			"SELECT id, name, type, parent_id
			 FROM {$table}
			 WHERE parent_id IS NOT NULL
			   AND NOT EXISTS (SELECT 1 FROM {$table} p WHERE p.id = {$table}.parent_id)"
		);

		WP_CLI::log( '' );
		WP_CLI::log( '── Orphaned entities (broken parent_id) ──' );
		if ( empty( $orphans ) ) {
			WP_CLI::success( '  None — hierarchy is intact.' );
		} else {
			WP_CLI::warning( '  Found ' . count( $orphans ) . ' orphaned entities:' );
			foreach ( $orphans as $o ) {
				WP_CLI::log( "  ID={$o->id} type={$o->type} name=\"{$o->name}\" parent_id={$o->parent_id}" );
			}
		}

		// Duplicates.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dups = $wpdb->get_results(
			"SELECT name, type, parent_id, COUNT(*) AS cnt
			 FROM {$table}
			 GROUP BY name, type, parent_id
			 HAVING cnt > 1"
		);

		WP_CLI::log( '' );
		WP_CLI::log( '── Duplicate entities ────────────────────' );
		if ( empty( $dups ) ) {
			WP_CLI::success( '  None — no duplicates.' );
		} else {
			WP_CLI::warning( '  Found ' . count( $dups ) . ' duplicate group(s):' );
			foreach ( $dups as $d ) {
				WP_CLI::log( "  type={$d->type} name=\"{$d->name}\" parent_id={$d->parent_id} count={$d->cnt}" );
			}
		}
	}

	/**
	 * Repair broken data (orphans, duplicates).
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial repair
	 *
	 * @subcommand repair
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function repair( $args, $assoc_args ) {
		$this->run_repair( /* silent */ false );
	}

	/**
	 * Show import statistics.
	 *
	 * ## EXAMPLES
	 *
	 *   wp psp territorial stats
	 *
	 * @subcommand stats
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function stats( $args, $assoc_args ) {
		global $wpdb;

		if ( ! $this->db->tables_exist() ) {
			WP_CLI::error( 'PSP Territorial table does not exist. Activate the plugin first.' );
		}

		$table = $this->db->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT type, COUNT(*) AS cnt FROM {$table} WHERE is_active=1 GROUP BY type ORDER BY FIELD(type,'province','district','corregimiento','community')" );

		WP_CLI::log( '── PSP Territorial Statistics ─────────────' );
		$total = 0;
		foreach ( $rows as $row ) {
			$label = PSP_Territorial_Utils::get_type_label( $row->type );
			WP_CLI::log( sprintf( '  %-20s %6d', $label, (int) $row->cnt ) );
			$total += (int) $row->cnt;
		}
		WP_CLI::log( '  ─────────────────────────────────────────' );
		WP_CLI::log( sprintf( '  %-20s %6d', 'TOTAL', $total ) );

		// Children consistency check.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$provinces_with_kids = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT parent_id) FROM {$table} WHERE type='district'"
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_provinces = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE type='province'"
		);

		WP_CLI::log( '' );
		WP_CLI::log( "── Provinces with districts: {$provinces_with_kids}/{$total_provinces}" );

		if ( $provinces_with_kids === $total_provinces ) {
			WP_CLI::success( 'All provinces have at least one district.' );
		} else {
			WP_CLI::warning( ( $total_provinces - $provinces_with_kids ) . ' province(s) have NO districts — run: wp psp territorial repair' );
		}
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Run all data-repair steps (shared by repair command and import-clean).
	 *
	 * @param bool $silent Suppress success messages when true.
	 */
	private function run_repair( $silent = false ) {
		global $wpdb;

		if ( ! $this->db->tables_exist() ) {
			WP_CLI::warning( 'PSP Territorial table does not exist — skipping repair.' );
			return;
		}

		$table = $this->db->get_table_name();

		// Fix orphans.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$orphans = $wpdb->get_results(
			"SELECT id FROM {$table}
			 WHERE parent_id IS NOT NULL
			   AND NOT EXISTS (SELECT 1 FROM {$table} p WHERE p.id = {$table}.parent_id)"
		);

		$fixed = 0;
		foreach ( $orphans as $orphan ) {
			$wpdb->update( $table, array( 'parent_id' => null ), array( 'id' => (int) $orphan->id ), array( '%s' ), array( '%d' ) );
			$fixed++;
		}

		if ( ! $silent || $fixed > 0 ) {
			if ( $fixed > 0 ) {
				WP_CLI::log( "  Fixed {$fixed} orphaned entity/entities." );
			} else {
				WP_CLI::log( '  No orphaned entities.' );
			}
		}

		// Remove duplicates.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dups = $wpdb->get_results(
			"SELECT name, type, parent_id, MIN(id) AS keep_id, COUNT(*) AS cnt
			 FROM {$table}
			 GROUP BY name, type, parent_id
			 HAVING cnt > 1"
		);

		$deleted = 0;
		foreach ( $dups as $dup ) {
			$parent_clause = ( null === $dup->parent_id || '' === $dup->parent_id )
				? 'parent_id IS NULL'
				: $wpdb->prepare( 'parent_id = %d', (int) $dup->parent_id );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->query(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					"DELETE FROM {$table} WHERE name = %s AND type = %s AND {$parent_clause} AND id <> %d",
					$dup->name,
					$dup->type,
					(int) $dup->keep_id
				)
			);
			if ( false !== $rows ) {
				$deleted += $rows;
			}
		}

		if ( ! $silent || $deleted > 0 ) {
			if ( $deleted > 0 ) {
				WP_CLI::log( "  Removed {$deleted} duplicate row(s)." );
			} else {
				WP_CLI::log( '  No duplicates found.' );
			}
		}

		if ( ! $silent ) {
			WP_CLI::success( 'Repair complete.' );
		}
	}
}

// Register WP-CLI commands when WP-CLI is available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'psp territorial', 'PSP_Territorial_CLI' );
}
