#!/usr/bin/env bash
#
# Build the distributable plugin zip: the same zip the release workflow
# attaches to a GitHub release, so a test build installs with
#
#   wp plugin install <zip> --force
#
# Usage: bin/build-zip.sh [--no-build] [--label <label>] <output-dir>
#
#   --no-build      Skip `npm ci` and `npm run build` and zip the build/
#                   folder as it is. The release workflow builds in its own
#                   step and passes this.
#   --label <label> Zip file name suffix: hk-funeral-suite-<label>.zip.
#                   Defaults to the plugin header version and the short
#                   commit hash, plus -dirty for uncommitted changes.
#
# The zip has one top-level folder, hk-funeral-suite/. Its files come from
# the working tree: everything git tracks or would track (untracked files
# that are not ignored), plus the compiled build/ folder, minus the
# development files in EXCLUDES. On a clean checkout that is exactly what
# the release workflow ships.

set -euo pipefail

# Any path with one of these names as a component is left out of the zip.
EXCLUDES=(
	.git
	.github
	.gitignore
	.editorconfig
	.nova
	.claude
	.playwright-cli
	.wordpress-org
	.DS_Store
	dist
	bin
	tests
	node_modules
	src
	screenshots
	CLAUDE.md
	MODERNISATION-PLAN.md
	phpcs.xml.dist
	webpack.scripts.config.js
	package.json
	package-lock.json
)

usage() {
	echo "Usage: bin/build-zip.sh [--no-build] [--label <label>] <output-dir>" >&2
	exit 2
}

build=1
label=''
out=''

while [ $# -gt 0 ]; do
	case "$1" in
		--no-build) build=0 ;;
		--label)
			[ $# -ge 2 ] || usage
			label="$2"
			shift
			;;
		-h | --help) usage ;;
		-*) usage ;;
		*)
			[ -z "$out" ] || usage
			out="$1"
			;;
	esac
	shift
done

[ -n "$out" ] || usage

mkdir -p "$out"
out="$(cd "$out" && pwd)"
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

[ "$out" != "$root" ] || { echo "Choose an output folder other than the plugin root" >&2; exit 2; }

if [ "$build" -eq 1 ]; then
	# Start from an empty build/, as the release workflow does, so no stale
	# compiled files ride along.
	rm -rf build
	npm ci
	npm run build
fi

# Same check as the release workflow's "Verify build output exists" step.
test -f build/settings/index.js || { echo "Settings bundle missing: run npm run build" >&2; exit 1; }
test -d build/blocks || { echo "Blocks build missing: run npm run build" >&2; exit 1; }

if [ -z "$label" ]; then
	version="$(sed -n 's/^ \* Version: *//p' hk-funeral-suite.php | head -n 1 | tr -d '[:space:]')"
	label="${version}-$(git rev-parse --short HEAD)"
	[ -z "$(git status --porcelain)" ] || label="${label}-dirty"
fi

zip_path="${out}/hk-funeral-suite-${label}.zip"

# Never zip a previous zip when the output folder is inside the repo.
out_rel=''
case "$out/" in
	"$root/"*) out_rel="${out#"$root"/}" ;;
esac

is_excluded() {
	local part ex parts
	IFS=/ read -r -a parts <<< "$1"
	for part in "${parts[@]}"; do
		for ex in "${EXCLUDES[@]}"; do
			[ "$part" != "$ex" ] || return 0
		done
	done
	[ -n "$out_rel" ] && case "$1" in "$out_rel"/*) return 0 ;; esac
	return 1
}

stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT
dest="${stage}/hk-funeral-suite"
mkdir -p "$dest"

count=0
while IFS= read -r -d '' path; do
	# Skip tracked files deleted from the working tree.
	[ -f "$path" ] || continue
	is_excluded "$path" && continue
	mkdir -p "${dest}/$(dirname "$path")"
	cp -p "$path" "${dest}/${path}"
	count=$((count + 1))
done < <(
	git ls-files -z --cached --others --exclude-standard
	find build -type f -print0
)

untracked="$(git ls-files --others --exclude-standard)"
if [ -n "$untracked" ]; then
	echo "Note: untracked files in the working tree (included unless excluded):" >&2
	while IFS= read -r file; do
		echo "  ${file}" >&2
	done <<< "$untracked"
fi

rm -f "$zip_path"
(cd "$stage" && zip -r -q "$zip_path" hk-funeral-suite)

echo "Built ${zip_path} (${count} files)"
