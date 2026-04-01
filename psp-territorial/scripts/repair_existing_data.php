<?php
/**
 * Repair Existing Data Script
 *
 * Run from the WordPress root:
 *   wp eval-file wp-content/plugins/psp-territorial/scripts/repair_existing_data.php
 *
 * Or load manually after bootstrapping WordPress:
 *   php -r "define('ABSPATH','/path/to/wordpress/'); ... require 'repair_existing_data.php';"
 *
 * @package PSP_Territorial
 */

// Require WordPress environment (WP-CLI provides this automatically).
if ( ! defined( 'ABSPATH' ) ) {
	// Attempt to locate wp-load.php by traversing upward from the plugin dir.
	$dir = __DIR__;
	for ( $i = 0; $i < 8; $i++ ) {
		$dir = dirname( $dir );
		if ( file_exists( $dir . '/wp-load.php' ) ) {
			require_once $dir . '/wp-load.php';
			break;
		}
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	die( "ERROR: WordPress not found. Run via WP-CLI: wp eval-file scripts/repair_existing_data.php\n" );
}

// Bootstrap plugin classes if not already loaded.
$plugin_dir = dirname( __DIR__ );
if ( ! class_exists( 'PSP_Territorial_Database' ) ) {
	require_once $plugin_dir . '/includes/class-psp-database.php';
	require_once $plugin_dir . '/includes/class-psp-utils.php';
}

global $wpdb;
$db    = new PSP_Territorial_Database();
$table = $db->get_table_name();

$report = array(
	'started_at'              => current_time( 'mysql' ),
	'districts_reparented'    => 0,
	'orphans_removed'         => 0,
	'broken_parents_fixed'    => 0,
	'duplicates_removed'      => 0,
	'children_counts'         => array(),
	'errors'                  => array(),
	'finished_at'             => null,
);

// ─────────────────────────────────────────────────────────────────────────────
// 0. Fix district parent assignments using the canonical JSON data
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Fixes districts whose parent is not a province.
 *
 * Loads the bundled panama_clean.json to build the authoritative
 * district → province mapping, then updates every district whose current
 * parent_id is missing or points to a non-province entity.
 *
 * @return int Number of districts whose parent_id was corrected.
 */
function psp_repair_fix_district_parents() {
	global $wpdb, $table;

	$json_file = dirname( __DIR__ ) . '/assets/data/panama_clean.json';
	if ( ! file_exists( $json_file ) ) {
		return 0;
	}

	$json_data = json_decode( file_get_contents( $json_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( null === $json_data || empty( $json_data ) ) {
		return 0;
	}
	// Accept both bare arrays and {"data": [...]} wrappers.
	$json_data = isset( $json_data['data'] ) ? $json_data['data'] : $json_data;
	if ( ! is_array( $json_data ) || empty( $json_data ) ) {
		return 0;
	}

	// Build province id (JSON) → province name map.
	$json_provinces = array();
	foreach ( $json_data as $entry ) {
		if ( 'province' === $entry['type'] ) {
			$json_provinces[ $entry['id'] ] = $entry['name'];
		}
	}

	// Build district name/slug → province name map from the JSON.
	$district_to_province = array();
	foreach ( $json_data as $entry ) {
		if ( 'district' === $entry['type'] && ! empty( $entry['parent_id'] ) ) {
			$province_name = isset( $json_provinces[ $entry['parent_id'] ] ) ? $json_provinces[ $entry['parent_id'] ] : null;
			if ( $province_name ) {
				$district_to_province[ $entry['name'] ] = $province_name;
				$district_to_province[ $entry['slug'] ] = $province_name;
			}
		}
	}

	if ( empty( $district_to_province ) ) {
		return 0;
	}

	// Build province name/slug → actual DB id map.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$db_provinces        = $wpdb->get_results( "SELECT id, name, slug FROM {$table} WHERE type = 'province'" );
	$province_name_to_id = array();
	foreach ( $db_provinces as $p ) {
		$province_name_to_id[ $p->name ] = (int) $p->id;
		$province_name_to_id[ $p->slug ] = (int) $p->id;
	}

	// Find districts whose current parent is absent or is not a province.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$bad_districts = $wpdb->get_results(
		"SELECT d.id, d.name, d.slug
		 FROM {$table} d
		 LEFT JOIN {$table} p ON p.id = d.parent_id
		 WHERE d.type = 'district'
		   AND ( p.id IS NULL OR p.type <> 'province' )"
	);

	$fixed = 0;
	foreach ( $bad_districts as $district ) {
		if ( isset( $district_to_province[ $district->name ] ) ) {
			$province_name = $district_to_province[ $district->name ];
		} elseif ( isset( $district_to_province[ $district->slug ] ) ) {
			$province_name = $district_to_province[ $district->slug ];
		} else {
			continue;
		}

		$province_id = isset( $province_name_to_id[ $province_name ] ) ? $province_name_to_id[ $province_name ] : null;

		if ( ! $province_id ) {
			continue;
		}

		$rows = $wpdb->update(
			$table,
			array( 'parent_id' => $province_id ),
			array( 'id' => (int) $district->id ),
			array( '%d' ),
			array( '%d' )
		);
		if ( false !== $rows && $rows > 0 ) {
			++$fixed;
		}
	}

	return $fixed;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Verify hierarchy — detect entities whose parent_id does not exist
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns all territories whose parent_id is set but does not reference an
 * existing row.
 *
 * @return array WP DB result objects.
 */
function psp_repair_orphan_detection() {
	global $wpdb, $table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results(
		"SELECT t.id, t.name, t.type, t.parent_id
		 FROM {$table} t
		 WHERE t.parent_id IS NOT NULL
		   AND NOT EXISTS (
		       SELECT 1 FROM {$table} p WHERE p.id = t.parent_id
		   )"
	);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Fix broken parents (set parent_id = NULL so orphans become roots)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Sets parent_id = NULL for each orphaned entity ID.
 *
 * @param array $orphans Array of objects with ->id property.
 * @return int Number of rows updated.
 */
function psp_repair_fix_broken_parents( array $orphans ) {
	global $wpdb, $table;

	$fixed = 0;
	foreach ( $orphans as $orphan ) {
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->update(
			$table,
			array( 'parent_id' => null ),
			array( 'id' => (int) $orphan->id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false !== $rows ) {
			$fixed += $rows;
		}
	}
	return $fixed;
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. Recalculate children counts (stored as a report, not a DB column)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Returns an array keyed by type with the number of direct children each
 * entity has.
 *
 * @return array Associative array: type => count.
 */
function psp_repair_recalculate_children_count() {
	global $wpdb, $table;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results(
		"SELECT p.type AS parent_type, COUNT(c.id) AS child_count
		 FROM {$table} p
		 JOIN {$table} c ON c.parent_id = p.id
		 GROUP BY p.type"
	);

	$counts = array();
	foreach ( $rows as $row ) {
		$counts[ $row->parent_type ] = (int) $row->child_count;
	}
	return $counts;
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Remove duplicates (same name + type + parent_id, keep lowest id)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Finds duplicate entities and removes the extra rows, keeping the one with
 * the lowest ID per (name, type, parent_id) combination.
 *
 * @return int Number of rows deleted.
 */
function psp_repair_remove_duplicates() {
	global $wpdb, $table;

	// Find duplicates.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$duplicates = $wpdb->get_results(
		"SELECT name, type, parent_id, MIN(id) AS keep_id, COUNT(*) AS cnt
		 FROM {$table}
		 GROUP BY name, type, parent_id
		 HAVING cnt > 1"
	);

	$deleted = 0;
	foreach ( $duplicates as $dup ) {
		$parent_clause = ( null === $dup->parent_id || '' === $dup->parent_id )
			? 'parent_id IS NULL'
			: $wpdb->prepare( 'parent_id = %d', (int) $dup->parent_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

	return $deleted;
}

// ─────────────────────────────────────────────────────────────────────────────
// Run all repairs
// ─────────────────────────────────────────────────────────────────────────────

if ( ! $db->tables_exist() ) {
	$report['errors'][] = 'Table does not exist — run plugin activation first.';
} else {
	// 0. Fix district parent assignments.
	$report['districts_reparented'] = psp_repair_fix_district_parents();

	// 1. Detect and fix orphans.
	$orphans                         = psp_repair_orphan_detection();
	$report['orphans_removed']       = count( $orphans );
	$report['broken_parents_fixed']  = psp_repair_fix_broken_parents( $orphans );

	// 2. Remove duplicates.
	$report['duplicates_removed']    = psp_repair_remove_duplicates();

	// 3. Recalculate counts.
	$report['children_counts']       = psp_repair_recalculate_children_count();
}

$report['finished_at'] = current_time( 'mysql' );

// Output JSON report.
$json = json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
echo $json . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
