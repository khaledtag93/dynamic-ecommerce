#!/usr/bin/env python3
"""Convert Laravel route:list --json output into a stable, reviewable CSV.

Usage: python3 scripts/export-route-manifest.py routes.json route_manifest.csv
The JSON must come from the same application revision recorded with the CSV.
"""

import argparse
import csv
import json
from pathlib import Path


FIELDS = ("domain", "method", "uri", "name", "action", "middleware")


def route_value(route, field):
    value = route.get(field)
    if value is None:
        return ""
    if field == "middleware" and isinstance(value, list):
        return " | ".join(str(item) for item in value)
    if isinstance(value, str):
        return value
    raise ValueError(f"Unexpected {field} value in route: {value!r}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("input", type=Path, help="route:list --json output")
    parser.add_argument("output", type=Path, help="CSV to write")
    args = parser.parse_args()

    routes = json.loads(args.input.read_text(encoding="utf-8"))
    if not isinstance(routes, list) or not routes:
        raise ValueError("Expected a nonempty JSON array of routes")

    rows = []
    for index, route in enumerate(routes, start=1):
        if not isinstance(route, dict) or not all(route.get(key) for key in ("method", "uri", "action")):
            raise ValueError(f"Invalid route at entry {index}")
        rows.append({field: route_value(route, field) for field in FIELDS})

    rows.sort(key=lambda row: tuple(row[field] for field in ("domain", "uri", "method", "name", "action")))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)

    get_routes = sum("GET" in row["method"].split("|") for row in rows)
    print(f"Exported {len(rows)} route entries ({get_routes} include GET) to {args.output}")


if __name__ == "__main__":
    main()
