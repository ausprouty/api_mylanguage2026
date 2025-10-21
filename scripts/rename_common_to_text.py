#!/usr/bin/env python3
# rename_common_to_text.py
# python rename_common_to_text.py "C:\ampp82\htdocs\api_mylanguage\Resources\templates\app"
# python rename_common_to_text.py "D:\path\to\root" --apply
# Recursively rename commonInterface.json / commonContent.json -> text.json

import os
import argparse
from typing import Optional

CANDIDATES = ["commonInterface.json", "commonContent.json"]
TARGET = "text.json"


def pick_source(dirpath: str) -> Optional[str]:
    """Return the first matching candidate found in dirpath, or None."""
    found = [f for f in CANDIDATES
             if os.path.isfile(os.path.join(dirpath, f))]
    if len(found) > 1:
        print(f"[SKIP] {dirpath} has both {found}. Resolve manually.")
        return None
    return found[0] if found else None


def rename_file(dirpath: str, src_name: str, apply: bool, force: bool) -> None:
    src = os.path.join(dirpath, src_name)
    dst = os.path.join(dirpath, TARGET)

    if not os.path.isfile(src):
        return

    if os.path.abspath(src) == os.path.abspath(dst):
        # Already named text.json
        return

    if os.path.exists(dst):
        if not force:
            print(f"[SKIP] {dst} exists. Use --force to overwrite.")
            return
        else:
            action = "OVERWRITE"
    else:
        action = "RENAME"

    if apply:
        try:
            # If force and dst exists, remove before renaming to avoid errors
            if os.path.exists(dst):
                os.remove(dst)
            os.rename(src, dst)
            print(f"[{action}] {src} -> {dst}")
        except OSError as e:
            print(f"[ERROR] {src} -> {dst}: {e}")
    else:
        print(f"[DRY-RUN {action}] {src} -> {dst}")


def main() -> None:
    parser = argparse.ArgumentParser(
        description=("Recursively rename commonInterface.json or "
                     "commonContent.json to text.json.")
    )
    parser.add_argument("root", help="Root folder to scan")
    parser.add_argument("--apply", action="store_true",
                        help="Actually perform changes")
    parser.add_argument("--force", action="store_true",
                        help="Overwrite existing text.json if present")
    args = parser.parse_args()

    root = os.path.abspath(args.root)
    if not os.path.isdir(root):
        print(f"[ERROR] Not a directory: {root}")
        return

    print(f"Scanning: {root}")
    print(f"Mode: {'APPLY' if args.apply else 'DRY-RUN'}"
          f"{' (force overwrite)' if args.force else ''}")

    for dirpath, _, _ in os.walk(root):
        src = pick_source(dirpath)
        if src:
            rename_file(dirpath, src, args.apply, args.force)


if __name__ == "__main__":
    main()
