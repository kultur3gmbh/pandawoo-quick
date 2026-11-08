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
cp "$zip_file" "dist/pandawoo-quick-${version}-wordpress-org.zip"
build_dir="$(mktemp -d)"
trap 'rm -rf "$build_dir"' EXIT
unzip -q "$zip_file" -d "$build_dir"
cp tools/github/class-panda-public-updater.php "$build_dir/pandawoo-quick/includes/"
cp tools/github/bootstrap.php "$build_dir/pandawoo-quick/includes/class-panda-public-bootstrap.php"
( cd "$build_dir" && zip -qr "$project_root/$zip_file" pandawoo-quick )
unzip -tq "$zip_file" >/dev/null
sha256="$(shasum -a 256 "$zip_file" | awk '{print $1}')"
requires="$(awk -F': ' '/^ \* Requires at least:/{print $2; exit}' pandawoo-quick.php)"
requires_php="$(awk -F': ' '/^ \* Requires PHP:/{print $2; exit}' pandawoo-quick.php)"
printf '{"slug":"pandawoo-quick","version":"%s","requires":"%s","requires_php":"%s","tested":"%s","sha256":"%s"}\n' "$version" "$requires" "$requires_php" "$requires" "$sha256" > dist/pandawoo-quick-update.json
echo "Created $zip_file"
