# PSP Territorial – Scripts

This directory contains helper scripts for the PSP Territorial WordPress plugin.

---

## clean_csv_to_json.py

**Language:** Python 3.8+

Reads the raw Panama territorial CSV (`panama_provinces_districts_coerregimientos_communities.csv`)
and generates a clean, hierarchically-structured JSON file at
`assets/data/panama_clean.json`.

### Usage

```bash
# From the plugin root (default paths):
python3 scripts/clean_csv_to_json.py

# Custom paths:
python3 scripts/clean_csv_to_json.py \
  --csv /path/to/source.csv \
  --out /path/to/output.json
```

### Output

- `assets/data/panama_clean.json` — flat list of 12 972 entities with explicit
  IDs and correct `parent_id` references.

---

## repair_existing_data.php

**Language:** PHP (requires WordPress)

Repairs an already-imported dataset by:
- Detecting and fixing broken `parent_id` references (orphans).
- Removing duplicate entities (keeps lowest ID).
- Reporting child-count statistics.

### Usage

```bash
# Via WP-CLI (recommended):
wp eval-file wp-content/plugins/psp-territorial/scripts/repair_existing_data.php

# Or via the CLI command:
wp psp territorial repair
```

The script prints a JSON report to stdout.

---

## WP-CLI Commands

After activating the plugin, the following WP-CLI commands are available:

| Command | Description |
|---------|-------------|
| `wp psp territorial clean-json` | Generate `panama_clean.json` from the CSV |
| `wp psp territorial import-clean` | Import the clean JSON (repairs existing data first) |
| `wp psp territorial import-clean --truncate` | Truncate and re-import all data |
| `wp psp territorial verify` | Verify data integrity |
| `wp psp territorial repair` | Repair orphans and duplicates |
| `wp psp territorial stats` | Show import statistics |
