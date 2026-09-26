#!/usr/bin/env python3
"""Reconcile the literal GET seed with a same-revision Laravel route JSON export.

The result is a discovery register, not evidence that a page passed review.
Usage: python3 scripts/reconcile-route-inventory.py routes.json seed.csv output.csv
"""

import argparse
import csv
import json
from pathlib import Path


FIELDS = ("source_line", "method", "uri", "route_name", "surface", "inventory_kind", "review_status", "action", "middleware")
AUTH_PAGES = {"login", "register", "password/confirm", "password/reset", "password/reset/{token}"}


def extra_category(uri):
    if uri in AUTH_PAGES:
        return "Auth", "page candidate"
    if uri.startswith("api/"):
        return "API", "supporting flow"
    if uri.startswith("livewire-"):
        return "Livewire", "supporting flow"
    if uri.startswith("_ignition/"):
        return "Development tooling", "supporting flow"
    if uri == "payments/paymob/callback":
        return "Payment integration", "supporting flow"
    if uri == "sanctum/csrf-cookie":
        return "Auth infrastructure", "supporting flow"
    raise ValueError(f"Classify newly registered GET route before adding it to the inventory: {uri}")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("routes", type=Path)
    parser.add_argument("seed", type=Path)
    parser.add_argument("output", type=Path)
    args = parser.parse_args()

    routes = json.loads(args.routes.read_text(encoding="utf-8"))
    if not isinstance(routes, list):
        raise ValueError("Expected a JSON route array")
    get_routes = {}
    for route in routes:
        if "GET" not in route["method"].split("|"):
            continue
        uri = route["uri"]
        if uri in get_routes:
            raise ValueError(f"Duplicate registered GET URI: {uri}")
        get_routes[uri] = route

    with args.seed.open(encoding="utf-8", newline="") as handle:
        seed = list(csv.DictReader(handle))
    rows = []
    seen = set()
    for item in seed:
        uri = item["declared_path_with_admin_prefix"].lstrip("/") or "/"
        if uri in seen or uri not in get_routes:
            raise ValueError(f"Duplicate or unregistered seed GET URI: {uri}")
        seen.add(uri)
        route = get_routes[uri]
        rows.append((item["source_line"], route, item["surface"], item["inventory_kind"]))

    for uri, route in get_routes.items():
        if uri not in seen:
            surface, kind = extra_category(uri)
            rows.append(("", route, surface, kind))

    records = [{
        "source_line": line,
        "method": route["method"],
        "uri": route["uri"],
        "route_name": route.get("name") or "",
        "surface": surface,
        "inventory_kind": kind,
        "review_status": "OPEN",
        "action": route["action"],
        "middleware": " | ".join(route["middleware"]),
    } for line, route, surface, kind in rows]
    records.sort(key=lambda row: (row["surface"], row["uri"], row["method"]))
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with args.output.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, lineterminator="\n")
        writer.writeheader()
        writer.writerows(records)
    print(f"Reconciled {len(seed)} source GET declarations with {len(get_routes)} registered GET routes; "
          f"{len(get_routes) - len(seed)} additional routes. All review states are OPEN.")


if __name__ == "__main__":
    main()
