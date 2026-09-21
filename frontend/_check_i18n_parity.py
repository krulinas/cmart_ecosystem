# -*- coding: utf-8 -*-
"""Compare leaf keys between en.js and ms.js locale catalogs."""
from pathlib import Path
import re

ROOT = Path(r"d:\Program Files\xampp\htdocs\cmart_ecosystem\frontend\src\i18n\locales")


def extract_keys(text: str) -> set[str]:
    """Walk brace structure and collect dotted leaf keys (approximate JS object walker)."""
    keys = set()
    stack = []  # (key, depth)
    # Tokenize on keys and braces
    # Match unquoted keys at object property position: key:
    i = 0
    n = len(text)
    # Find export default {
    start = text.find("export default")
    if start < 0:
        raise SystemExit("no export default")
    # Find first { after export default
    brace = text.find("{", start)
    depth = 0
    path = []
    # Simple state machine
    j = brace
    while j < n:
        c = text[j]
        if c in "\"'`":
            quote = c
            j += 1
            while j < n:
                if text[j] == "\\":
                    j += 2
                    continue
                if text[j] == quote:
                    j += 1
                    break
                j += 1
            continue
        if c == "{":
            depth += 1
            j += 1
            continue
        if c == "}":
            depth -= 1
            if path and depth < len(path) + 1:  # leaving an object that had a key
                # pop keys until depth matches
                while path and len(path) >= depth:
                    path.pop()
            j += 1
            continue
        # try match key:
        m = re.match(r"([A-Za-z_][A-Za-z0-9_]*)\s*:", text[j:])
        if m and depth >= 1:
            key = m.group(1)
            # adjust path to current depth-1
            while len(path) >= depth:
                path.pop()
            path.append(key)
            # look ahead for value type
            after = j + m.end()
            while after < n and text[after] in " \t\n\r":
                after += 1
            if after < n and text[after] != "{":
                keys.add(".".join(path))
                # leaf — will be popped when next sibling key at same depth arrives
            j = after
            continue
        j += 1
    return keys


en = extract_keys((ROOT / "en.js").read_text(encoding="utf-8"))
ms = extract_keys((ROOT / "ms.js").read_text(encoding="utf-8"))
only_en = sorted(en - ms)
only_ms = sorted(ms - en)
print(f"en leaves: {len(en)}")
print(f"ms leaves: {len(ms)}")
print(f"only in en: {len(only_en)}")
print(f"only in ms: {len(only_ms)}")
if only_en[:30]:
    print("EN sample:", only_en[:30])
if only_ms[:30]:
    print("MS sample:", only_ms[:30])
