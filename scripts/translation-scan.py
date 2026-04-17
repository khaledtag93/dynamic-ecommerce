#!/usr/bin/env python3
import json, os, re
from pathlib import Path

BASE = Path(__file__).resolve().parents[1]
AR = json.loads((BASE / 'lang' / 'ar.json').read_text(encoding='utf-8'))
keys = set(AR)
pat = re.compile(r"__\(\s*'([^']+)'\s*\)|__\(\s*\"([^\"]+)\"\s*\)")
missing = {}
for folder in [BASE / 'resources' / 'views', BASE / 'app']:
    for path in folder.rglob('*.php'):
        text = path.read_text(encoding='utf-8', errors='ignore')
        for match in pat.finditer(text):
            key = match.group(1) or match.group(2)
            if '$' in key or '\\' in key:
                continue
            if key not in keys:
                missing.setdefault(key, set()).add(str(path.relative_to(BASE)))

print(f'Missing translation keys: {len(missing)}')
for key in sorted(missing)[:300]:
    refs = ', '.join(sorted(missing[key])[:3])
    print(f'- {key} -> {refs}')
