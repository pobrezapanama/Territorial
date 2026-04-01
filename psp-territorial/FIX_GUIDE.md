# PSP Territorial — Fix Guide

## The Problem

After the initial plugin activation some users reported:

1. **Only "Bocas del Toro" showed a child-district count** — all other provinces
   displayed zero children.
2. **Community searches returned wrong parents** — the displayed parent and
   child names did not match the actual hierarchy.

### Root Cause

The batch-`INSERT` statement in `class-psp-importer.php` did not include the
`id` column:

```sql
-- Before fix (wrong):
INSERT INTO wp_psp_territories (name,slug,code,type,parent_id,level,is_active)
VALUES …

-- After fix (correct):
INSERT INTO wp_psp_territories (id,name,slug,code,type,parent_id,level,is_active)
VALUES …
```

The pre-generated JSON (`panama_data.json`) correctly uses an ID scheme where:
- Provinces: IDs 1 – 13
- Districts: IDs 1001 – 1082
- Corregimientos: IDs 10001 – 10703
- Communities: IDs 100001 – 112174

Because the `id` column was omitted, MySQL auto-assigned sequential IDs
(1, 2, 3, …) to **all** records. Province "Bocas del Toro" happened to receive
auto-ID = 1, matching its pre-planned ID, so its districts (parent_id = 1) were
correct. Every other province received an auto-ID much higher than its
pre-planned value, breaking all of its children's `parent_id` references.

---

## Fix Summary

| File | Change |
|------|--------|
| `includes/class-psp-importer.php` | Include `id` in batch INSERT; prefer `panama_clean.json` |
| `includes/class-psp-database.php` | Include `id` in `bulk_insert()` |
| `includes/class-psp-importer-v2.php` | New: improved importer with validation & retry |
| `includes/class-psp-cli.php` | New: WP-CLI commands |
| `includes/activators/class-activator.php` | Use v2 importer on activation |
| `includes/class-psp-plugin.php` | Load new classes |
| `scripts/clean_csv_to_json.py` | New: Python script to regenerate JSON from CSV |
| `scripts/repair_existing_data.php` | New: PHP repair script |
| `assets/data/panama_clean.json` | New: regenerated clean JSON |

---

## How to Fix an Existing Installation

### Option A — Re-import with WP-CLI (Recommended)

```bash
# 1. (Optional) Regenerate the JSON from the source CSV:
wp psp territorial clean-json

# 2. Verify the current state of the database:
wp psp territorial verify

# 3a. Repair in-place (keeps existing IDs where possible):
wp psp territorial repair

# 3b. OR, for a full clean re-import:
wp psp territorial import-clean --truncate

# 4. Confirm the result:
wp psp territorial stats
```

### Option B — Re-activate the Plugin

1. Deactivate the plugin from **Plugins → Installed Plugins**.
2. Delete the database tables (optional, but ensures a clean slate):
   ```sql
   DROP TABLE IF EXISTS wp_psp_territories;
   DROP TABLE IF EXISTS wp_psp_territory_meta;
   ```
3. Re-activate the plugin — the activation hook will import fresh data using
   the corrected importer.

### Option C — Use the PHP Repair Script

```bash
wp eval-file wp-content/plugins/psp-territorial/scripts/repair_existing_data.php
```

---

## Expected Statistics After Fix

```
Provinces:           13
Districts:           82
Corregimientos:     703
Communities:     12 174
────────────────────────
TOTAL:           12 972
```

All 13 provinces should show a non-zero district count in the admin panel.

---

## Troubleshooting

### "Only Bocas del Toro shows children" after re-import

Ensure you ran the import **after** updating the plugin to the patched version.
The old importer left behind rows with incorrect IDs. Truncate and re-import:

```bash
wp psp territorial import-clean --truncate
```

### "JSON file not found" error

Run the Python script to generate it:

```bash
wp psp territorial clean-json
# or directly:
python3 wp-content/plugins/psp-territorial/scripts/clean_csv_to_json.py
```

### Python not available

The `panama_clean.json` file is bundled with the plugin in
`assets/data/panama_clean.json`. The Python script is only needed to regenerate
it from the source CSV (e.g. after the CSV is updated).
