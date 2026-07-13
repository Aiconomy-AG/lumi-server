#!/usr/bin/env python3
"""Check relative Markdown links and images in documentation sources."""

from __future__ import annotations

import re
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[2]
DOCS = ROOT / "docs"
PATTERN = re.compile(r"!?\[[^]]*]\(([^)]+)\)")
errors: list[str] = []

for document in DOCS.rglob("*.md"):
    content = document.read_text(encoding="utf-8")
    for target in PATTERN.findall(content):
        target = target.strip().split()[0].strip("<>")
        if not target or target.startswith(("http://", "https://", "mailto:", "#")):
            continue
        path_text = unquote(target.split("#", 1)[0])
        if not path_text:
            continue
        resolved = (document.parent / path_text).resolve()
        if not resolved.exists():
            errors.append(f"{document.relative_to(ROOT)} -> {target}")

if errors:
    print("Broken relative links:")
    print("\n".join(f"  {error}" for error in errors))
    raise SystemExit(1)

print("Documentation relative links are valid")

