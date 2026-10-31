#!/usr/bin/env bash
set -euo pipefail

project_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$project_root"

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
	echo "Release packages must be built from a Git repository." >&2
	exit 1
fi

version="$(awk -F': ' '/^ \* Version:/{print $2; exit}' pandawoo-quick.php)"
mkdir -p dist
zip_file="dist/pandawoo-quick-${version}.zip"
git archive --format=zip --prefix=pandawoo-quick/ HEAD -- LICENSE README.md assets includes languages pandawoo-quick.php readme.txt > "$zip_file"
unzip -tq "$zip_file" >/dev/null
echo "Created $zip_file"
