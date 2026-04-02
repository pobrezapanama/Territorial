#!/usr/bin/env python3
"""
clean_full_geography.py
=======================
Reads ``panama_full_geography.json`` from the repository root, applies the
normalisation rules agreed with the project owner, and writes a clean,
flat-list JSON file ready for the PSP Territorial importer.

Normalisation rules (applied to *all* hierarchy levels)
--------------------------------------------------------
1. ``trim()`` — remove leading / trailing whitespace.
2. Collapse internal runs of whitespace to a single space.
3. Strip trailing colon suffix: remove any pattern matching ``\\s*:\\s*$``
   (handles both ``"El Teribe:"`` and ``"Valle del Riscó :"``).
4. Drop empty strings that result from the above.
5. Merge duplicates: after normalisation, if two entries at the same level
   share the same name they are merged (children are unioned, then
   deduplicated).

Output
------
  psp-territorial/assets/data/panama_full_geography.clean.json

Usage
-----
  python3 psp-territorial/scripts/clean_full_geography.py
  python3 psp-territorial/scripts/clean_full_geography.py \\
          --src path/to/panama_full_geography.json \\
          --out path/to/output.json
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
import unicodedata
from datetime import datetime, timezone

# ---------------------------------------------------------------------------
# Constants
# ---------------------------------------------------------------------------

ID_OFFSET: dict[str, int] = {
    "province":      0,        # IDs: 1, 2, 3 …
    "district":      1_000,    # IDs: 1001, 1002 …
    "corregimiento": 10_000,   # IDs: 10001, 10002 …
    "community":     100_000,  # IDs: 100001, 100002 …
}

CODE_DIGITS: dict[str, int] = {
    "province":      4,
    "district":      4,
    "corregimiento": 4,
    "community":     4,
}

CODE_PREFIX: dict[str, str] = {
    "province":      "PRV",
    "district":      "DIS",
    "corregimiento": "COR",
    "community":     "COM",
}

_TRAILING_COLON = re.compile(r"\s*:\s*$")
_MULTI_SPACE    = re.compile(r"\s+")

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------


def normalize_name(name: str) -> str:
    """Return a cleaned version of *name* according to the project rules."""
    name = name.strip()
    name = _MULTI_SPACE.sub(" ", name)
    name = _TRAILING_COLON.sub("", name)
    return name.strip()


def slugify(text: str) -> str:
    """Return a URL-friendly, ASCII slug."""
    text = unicodedata.normalize("NFKD", text)
    text = text.encode("ascii", "ignore").decode("ascii")
    text = re.sub(r"[^\w\s-]", "", text).strip().lower()
    return re.sub(r"[-\s]+", "-", text)


def make_code(entity_type: str, index: int) -> str:
    prefix = CODE_PREFIX.get(entity_type, "ENT")
    digits = CODE_DIGITS.get(entity_type, 4)
    return f"{prefix}-{index:0{digits}d}"


# ---------------------------------------------------------------------------
# Core normalisation / merge logic
# ---------------------------------------------------------------------------


def normalize_and_merge(raw: dict) -> list[dict]:
    """
    Convert the raw hierarchical dict into a flat list of territory records.

    The conversion applies normalisation and duplicate merging at every level.
    Returns a flat list ordered province → district → corregimiento → community.
    """
    records: list[dict] = []

    province_idx = 0
    district_idx = 0
    corr_idx     = 0
    comm_idx     = 0

    merge_report: dict[str, int] = {
        "province_merges":      0,
        "district_merges":      0,
        "corregimiento_merges": 0,
        "community_dedups":     0,
    }

    # ── Level 0: provinces ────────────────────────────────────────────────
    provinces_merged: dict[str, dict] = {}  # name → {district_name → {corr_name → [comms]}}

    for raw_prov_name, raw_districts in raw.items():
        prov_name = normalize_name(raw_prov_name)
        if not prov_name or not isinstance(raw_districts, dict):
            continue

        if prov_name not in provinces_merged:
            provinces_merged[prov_name] = {}
        else:
            merge_report["province_merges"] += 1

        # ── Level 1: districts ────────────────────────────────────────────
        for raw_dist_name, raw_corrs in raw_districts.items():
            dist_name = normalize_name(raw_dist_name)
            if not dist_name or not isinstance(raw_corrs, dict):
                continue

            if dist_name not in provinces_merged[prov_name]:
                provinces_merged[prov_name][dist_name] = {}
            else:
                merge_report["district_merges"] += 1

            # ── Level 2: corregimientos ───────────────────────────────────
            for raw_corr_name, raw_comms in raw_corrs.items():
                corr_name = normalize_name(raw_corr_name)
                if not corr_name or not isinstance(raw_comms, list):
                    continue

                if corr_name not in provinces_merged[prov_name][dist_name]:
                    provinces_merged[prov_name][dist_name][corr_name] = []
                else:
                    merge_report["corregimiento_merges"] += 1

                # ── Level 3: communities ──────────────────────────────────
                for raw_comm in raw_comms:
                    if not isinstance(raw_comm, str):
                        continue
                    comm_name = normalize_name(raw_comm)
                    if comm_name:
                        provinces_merged[prov_name][dist_name][corr_name].append(comm_name)

    # ── Emit flat records ─────────────────────────────────────────────────
    for prov_name, districts in provinces_merged.items():
        province_idx += 1
        province_id = ID_OFFSET["province"] + province_idx

        records.append(
            {
                "id":        province_id,
                "name":      prov_name,
                "slug":      slugify(prov_name),
                "code":      make_code("province", province_idx),
                "type":      "province",
                "parent_id": None,
                "level":     1,
            }
        )

        for dist_name, corrs in districts.items():
            district_idx += 1
            district_id = ID_OFFSET["district"] + district_idx

            records.append(
                {
                    "id":        district_id,
                    "name":      dist_name,
                    "slug":      slugify(dist_name),
                    "code":      make_code("district", district_idx),
                    "type":      "district",
                    "parent_id": province_id,
                    "level":     2,
                }
            )

            for corr_name, comms in corrs.items():
                corr_idx += 1
                corr_id = ID_OFFSET["corregimiento"] + corr_idx

                records.append(
                    {
                        "id":        corr_id,
                        "name":      corr_name,
                        "slug":      slugify(corr_name),
                        "code":      make_code("corregimiento", corr_idx),
                        "type":      "corregimiento",
                        "parent_id": district_id,
                        "level":     3,
                    }
                )

                # Deduplicate communities while preserving insertion order.
                seen: set[str] = set()
                for comm_name in comms:
                    if comm_name in seen:
                        merge_report["community_dedups"] += 1
                        continue
                    seen.add(comm_name)
                    comm_idx += 1
                    records.append(
                        {
                            "id":        ID_OFFSET["community"] + comm_idx,
                            "name":      comm_name,
                            "slug":      slugify(comm_name),
                            "code":      make_code("community", comm_idx),
                            "type":      "community",
                            "parent_id": corr_id,
                            "level":     4,
                        }
                    )

    merge_report["_counts"] = {
        "provinces":      province_idx,
        "districts":      district_idx,
        "corregimientos": corr_idx,
        "communities":    comm_idx,
        "total":          len(records),
    }
    return records, merge_report


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------


def validate_hierarchy(records: list[dict]) -> list[str]:
    id_set = {r["id"] for r in records}
    issues = []
    for rec in records:
        if rec["parent_id"] is not None and rec["parent_id"] not in id_set:
            issues.append(
                f"ID {rec['id']} ({rec['type']} '{rec['name']}') "
                f"references unknown parent_id {rec['parent_id']}"
            )
        name = rec.get("name", "")
        if not name:
            issues.append(f"ID {rec['id']} has an empty name")
        elif _TRAILING_COLON.search(name):
            issues.append(
                f"ID {rec['id']} ({rec['type']} '{rec['name']}') still ends with ':'"
            )
    return issues


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------


def main() -> None:
    script_dir = os.path.dirname(os.path.abspath(__file__))
    plugin_dir = os.path.dirname(script_dir)
    repo_root  = os.path.dirname(plugin_dir)

    default_src = os.path.join(repo_root, "panama_full_geography.json")
    default_out = os.path.join(plugin_dir, "assets", "data", "panama_full_geography.clean.json")

    parser = argparse.ArgumentParser(
        description="Normalize panama_full_geography.json → flat clean JSON"
    )
    parser.add_argument("--src", default=default_src, help="Source hierarchical JSON")
    parser.add_argument("--out", default=default_out, help="Output flat JSON path")
    args = parser.parse_args()

    if not os.path.isfile(args.src):
        print(f"ERROR: source file not found: {args.src}", file=sys.stderr)
        sys.exit(1)

    print(f"Reading: {args.src}")
    with open(args.src, encoding="utf-8") as fh:
        raw = json.load(fh)

    if not isinstance(raw, dict):
        print("ERROR: expected top-level JSON object (province → district → …)", file=sys.stderr)
        sys.exit(1)

    print("Normalising and merging …")
    records, merge_report = normalize_and_merge(raw)

    issues = validate_hierarchy(records)
    if issues:
        print(f"\n⚠  {len(issues)} validation issue(s):")
        for issue in issues:
            print(f"   ✗ {issue}")
        has_warnings = True
    else:
        print("✓  Hierarchy validation passed — no colon-suffixed names remain.")
        has_warnings = False

    counts = merge_report["_counts"]
    print("\nStatistics:")
    print(f"  Provinces:              {counts['provinces']:>6}")
    print(f"  Districts:              {counts['districts']:>6}")
    print(f"  Corregimientos:         {counts['corregimientos']:>6}")
    print(f"  Communities:            {counts['communities']:>6}")
    print(f"  ─────────────────────────────────────")
    print(f"  TOTAL:                  {counts['total']:>6}")
    print()
    print(f"  Province merges:        {merge_report['province_merges']:>6}")
    print(f"  District merges:        {merge_report['district_merges']:>6}")
    print(f"  Corregimiento merges:   {merge_report['corregimiento_merges']:>6}")
    print(f"  Community deduplicated: {merge_report['community_dedups']:>6}")

    output = {
        "meta": {
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "source":       os.path.basename(args.src),
            "total":        counts["total"],
            "counts":       {
                "province":      counts["provinces"],
                "district":      counts["districts"],
                "corregimiento": counts["corregimientos"],
                "community":     counts["communities"],
            },
            "merges": {
                "province_merges":      merge_report["province_merges"],
                "district_merges":      merge_report["district_merges"],
                "corregimiento_merges": merge_report["corregimiento_merges"],
                "community_dedups":     merge_report["community_dedups"],
            },
        },
        "data": records,
    }

    os.makedirs(os.path.dirname(args.out), exist_ok=True)
    with open(args.out, "w", encoding="utf-8") as fh:
        json.dump(output, fh, ensure_ascii=False, indent=2)

    print(f"\n✓  Output written to: {args.out}")

    if has_warnings:
        sys.exit(2)


if __name__ == "__main__":
    main()
