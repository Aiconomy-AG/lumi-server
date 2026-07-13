#!/usr/bin/env python3
from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parents[2]
SPEC = ROOT / "docs" / "api" / "openapi.yaml"


def normalize(path: str) -> str:
    return re.sub(r"\{[^}]+}", "{}", path.rstrip("/") or "/")


result = subprocess.run(
    ["php", "artisan", "route:list", "--json"], cwd=ROOT, check=True, capture_output=True, text=True
)
routes = json.loads(result.stdout)
expected = set()
for route in routes:
    if not route["uri"].startswith("api/"):
        continue
    path = "/" + route["uri"].split("/", 2)[2]
    for method in route["method"].split("|"):
        if method != "HEAD":
            expected.add((method.lower(), normalize(path)))

document = yaml.safe_load(SPEC.read_text(encoding="utf-8"))
actual = {
    (method.lower(), normalize(path))
    for path, path_item in document.get("paths", {}).items()
    for method in path_item
    if method.lower() in {"get", "post", "put", "patch", "delete", "options"}
}

missing = sorted(expected - actual)
extra = sorted(actual - expected)
if missing or extra:
    if missing:
        print("Missing OpenAPI operations:")
        for method, path in missing:
            print(f"  {method.upper()} {path}")
    if extra:
        print("OpenAPI operations without Laravel routes:")
        for method, path in extra:
            print(f"  {method.upper()} {path}")
    raise SystemExit(1)

print(f"OpenAPI coverage matches {len(expected)} registered API operations")
