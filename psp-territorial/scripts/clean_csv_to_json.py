#!/usr/bin/env python3
"""
clean_csv_to_json.py
====================
Reads the Panama territorial CSV (ISO-8859-1, semicolon-delimited) and
generates a clean, hierarchically-structured JSON file for the PSP Territorial
WordPress plugin.

The CSV uses an implicit-hierarchy format:
  - A non-empty first column marks a new Province.
  - A non-empty second column marks a new District under the current Province.
  - A non-empty third column marks a new Corregimiento under the current District.
  - A non-empty fourth column is a Community under the current Corregimiento.

Outputs
-------
  ../assets/data/panama_clean.json   — flat list with correct parent IDs
  (stats printed to stdout)

Usage
-----
  python3 scripts/clean_csv_to_json.py
  python3 scripts/clean_csv_to_json.py --csv path/to/file.csv --out path/to/output.json
"""

import csv
import json
import os
import sys
import argparse
import unicodedata
import re
from datetime import datetime, timezone

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def slugify(text: str) -> str:
    """Return a URL-friendly slug."""
    text = unicodedata.normalize("NFKD", text)
    text = text.encode("ascii", "ignore").decode("ascii")
    text = re.sub(r"[^\w\s-]", "", text).strip().lower()
    return re.sub(r"[-\s]+", "-", text)


def generate_code(entity_type: str, index: int) -> str:
    prefixes = {
        "province": "PRV",
        "district": "DIS",
        "corregimiento": "COR",
        "community": "COM",
    }
    prefix = prefixes.get(entity_type, "ENT")
    return f"{prefix}-{index:04d}"


# ---------------------------------------------------------------------------
# ID ranges (must match the existing panama_data.json schema)
# ---------------------------------------------------------------------------
ID_OFFSET = {
    "province": 0,        # IDs: 1, 2, 3 …
    "district": 1000,     # IDs: 1001, 1002 …
    "corregimiento": 10000,  # IDs: 10001, 10002 …
    "community": 100000,  # IDs: 100001, 100002 …
}


# ---------------------------------------------------------------------------
# CSV parser
# ---------------------------------------------------------------------------

def parse_csv(csv_path: str) -> list[dict]:
    """Parse the CSV and return a flat list of territory dicts."""
    records: list[dict] = []

    province_index = 0
    district_index = 0
    corregimiento_index = 0
    community_index = 0

    current_province_id: int | None = None
    current_district_id: int | None = None
    current_corregimiento_id: int | None = None

    errors: list[str] = []

    with open(csv_path, encoding="iso-8859-1", newline="") as fh:
        reader = csv.reader(fh, delimiter=";")
        next(reader, None)  # skip header

        for lineno, row in enumerate(reader, start=2):
            # Pad to 4 columns.
            while len(row) < 4:
                row.append("")

            prov = row[0].strip()
            dist = row[1].strip()
            corr = row[2].strip()
            comm = row[3].strip()

            # Skip blank lines.
            if not prov and not dist and not corr and not comm:
                continue

            if prov:
                province_index += 1
                eid = ID_OFFSET["province"] + province_index
                records.append({
                    "id": eid,
                    "name": prov,
                    "slug": slugify(prov),
                    "code": generate_code("province", province_index),
                    "type": "province",
                    "parent_id": None,
                    "level": 1,
                })
                current_province_id = eid
                current_district_id = None
                current_corregimiento_id = None

            elif dist:
                if current_province_id is None:
                    errors.append(f"Line {lineno}: district '{dist}' has no province parent")
                    continue
                district_index += 1
                eid = ID_OFFSET["district"] + district_index
                records.append({
                    "id": eid,
                    "name": dist,
                    "slug": slugify(dist),
                    "code": generate_code("district", district_index),
                    "type": "district",
                    "parent_id": current_province_id,
                    "level": 2,
                })
                current_district_id = eid
                current_corregimiento_id = None

            elif corr:
                if current_district_id is None:
                    errors.append(f"Line {lineno}: corregimiento '{corr}' has no district parent")
                    continue
                corregimiento_index += 1
                eid = ID_OFFSET["corregimiento"] + corregimiento_index
                records.append({
                    "id": eid,
                    "name": corr,
                    "slug": slugify(corr),
                    "code": generate_code("corregimiento", corregimiento_index),
                    "type": "corregimiento",
                    "parent_id": current_district_id,
                    "level": 3,
                })
                current_corregimiento_id = eid

            elif comm:
                if current_corregimiento_id is None:
                    errors.append(f"Line {lineno}: community '{comm}' has no corregimiento parent")
                    continue
                community_index += 1
                eid = ID_OFFSET["community"] + community_index
                records.append({
                    "id": eid,
                    "name": comm,
                    "slug": slugify(comm),
                    "code": generate_code("community", community_index),
                    "type": "community",
                    "parent_id": current_corregimiento_id,
                    "level": 4,
                })

    return records, errors


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

def validate_hierarchy(records: list[dict]) -> list[str]:
    """Verify that every non-province entity has a valid parent."""
    id_set = {r["id"] for r in records}
    issues = []
    for rec in records:
        if rec["parent_id"] is not None and rec["parent_id"] not in id_set:
            issues.append(
                f"ID {rec['id']} ({rec['type']} '{rec['name']}') "
                f"has unknown parent_id {rec['parent_id']}"
            )
    return issues


# ---------------------------------------------------------------------------
# Statistics
# ---------------------------------------------------------------------------

def build_stats(records: list[dict]) -> dict:
    counts = {"province": 0, "district": 0, "corregimiento": 0, "community": 0}
    for r in records:
        counts[r["type"]] = counts.get(r["type"], 0) + 1
    return counts


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def main():
    script_dir = os.path.dirname(os.path.abspath(__file__))
    plugin_dir = os.path.dirname(script_dir)

    default_csv = os.path.join(
        os.path.dirname(plugin_dir),
        "panama_provinces_districts_coerregimientos_communities.csv",
    )
    default_out = os.path.join(plugin_dir, "assets", "data", "panama_clean.json")

    parser = argparse.ArgumentParser(description="Clean Panama CSV → structured JSON")
    parser.add_argument("--csv", default=default_csv, help="Path to the source CSV file")
    parser.add_argument("--out", default=default_out, help="Output JSON file path")
    args = parser.parse_args()

    if not os.path.isfile(args.csv):
        print(f"ERROR: CSV not found: {args.csv}", file=sys.stderr)
        sys.exit(1)

    print(f"Reading CSV: {args.csv}")
    records, parse_errors = parse_csv(args.csv)

    if parse_errors:
        print("\nParse warnings:")
        for e in parse_errors:
            print(f"  ⚠  {e}")

    hierarchy_issues = validate_hierarchy(records)
    if hierarchy_issues:
        print("\nHierarchy issues:")
        for issue in hierarchy_issues:
            print(f"  ✗  {issue}")
    else:
        print("✓  Hierarchy validation passed")

    stats = build_stats(records)

    output = {
        "meta": {
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "source": os.path.basename(args.csv),
            "total": len(records),
            "counts": stats,
        },
        "data": records,
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        json.dump(output, fh, ensure_ascii=False, indent=2)

    print(f"\n✓  Output written to: {args.out}")
    print("\nStatistics:")
    print(f"  Provinces:      {stats['province']:>6}")
    print(f"  Districts:      {stats['district']:>6}")
    print(f"  Corregimientos: {stats['corregimiento']:>6}")
    print(f"  Communities:    {stats['community']:>6}")
    print(f"  ─────────────────────")
    print(f"  TOTAL:          {len(records):>6}")

    if parse_errors or hierarchy_issues:
        print(f"\n⚠  Completed with {len(parse_errors) + len(hierarchy_issues)} warning(s).")
        sys.exit(2)
    else:
        print("\n✓  All done — no errors.")


if __name__ == "__main__":
    main()
