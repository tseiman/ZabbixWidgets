#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
PROJECT_DIR=$(CDPATH='' cd -- "$SCRIPT_DIR/.." && pwd -P)
REPOSITORY_ROOT=$(CDPATH='' cd -- "$PROJECT_DIR/.." && pwd -P)
MODULE_SOURCE="$PROJECT_DIR/source/modules/unifi_topology"
TEMPLATE_SOURCE="$PROJECT_DIR/source/templates/unifi_network_topology_zabbix74.yaml"
INSTALLER_SOURCE="$PROJECT_DIR/install.sh"
OUTPUT_DIR=${1:-"$PROJECT_DIR/dist"}
SUFFIX=${2:-}

for required in "$MODULE_SOURCE/manifest.json" "$MODULE_SOURCE/Widget.php" "$TEMPLATE_SOURCE" "$INSTALLER_SOURCE"; do
    if [[ ! -f "$required" ]]; then
        printf 'Error: required source is missing: %s\n' "$required" >&2
        exit 1
    fi
done

VERSION=$(python3 - "$MODULE_SOURCE/manifest.json" <<'PY'
import json
import sys
version = json.load(open(sys.argv[1], encoding="utf-8"))["version"]
if not isinstance(version, str) or not version or any(c not in "0123456789." for c in version):
    raise SystemExit("manifest version is not a dotted numeric version")
print(version)
PY
)

if [[ -n "$SUFFIX" && ! "$SUFFIX" =~ ^[A-Za-z0-9._-]+$ ]]; then
    printf 'Error: archive suffix contains unsupported characters: %s\n' "$SUFFIX" >&2
    exit 2
fi

PACKAGE_NAME="unifi_topology-$VERSION"
[[ -n "$SUFFIX" ]] && PACKAGE_NAME+="-$SUFFIX"
mkdir -p -- "$OUTPUT_DIR"
OUTPUT_DIR=$(CDPATH='' cd -- "$OUTPUT_DIR" && pwd -P)
ARCHIVE="$OUTPUT_DIR/$PACKAGE_NAME.tar.gz"
CHECKSUM="$ARCHIVE.sha256"

STAGING_ROOT=$(mktemp -d)
cleanup() {
    rm -rf -- "$STAGING_ROOT"
}
trap cleanup EXIT
trap 'exit 130' INT TERM HUP
PACKAGE_ROOT="$STAGING_ROOT/$PACKAGE_NAME"
MODULE_TARGET="$PACKAGE_ROOT/module"

install -d -m 0755 "$MODULE_TARGET" "$PACKAGE_ROOT/template"
install -m 0755 "$INSTALLER_SOURCE" "$PACKAGE_ROOT/install.sh"
install -m 0644 "$TEMPLATE_SOURCE" "$PACKAGE_ROOT/template/$(basename -- "$TEMPLATE_SOURCE")"

if find "$MODULE_SOURCE" -type l -print -quit | grep -q .; then
    printf 'Error: symbolic links are not allowed in the module source.\n' >&2
    exit 1
fi
while IFS= read -r -d '' source_dir; do
    relative=${source_dir#"$MODULE_SOURCE"/}
    [[ "$source_dir" == "$MODULE_SOURCE" ]] && continue
    install -d -m 0755 "$MODULE_TARGET/$relative"
done < <(find "$MODULE_SOURCE" -type d -print0)
while IFS= read -r -d '' source_file; do
    relative=${source_file#"$MODULE_SOURCE"/}
    case "$relative" in
        *.md|*.MD) continue ;;
    esac
    install -D -m 0644 "$source_file" "$MODULE_TARGET/$relative"
done < <(find "$MODULE_SOURCE" -type f -print0)

if find "$PACKAGE_ROOT" -type f \( -iname '*.md' -o -iname 'readme*' \) -print -quit | grep -q .; then
    printf 'Error: Markdown documentation unexpectedly entered the package.\n' >&2
    exit 1
fi

SOURCE_DATE_EPOCH=${SOURCE_DATE_EPOCH:-}
if [[ -z "$SOURCE_DATE_EPOCH" ]] && git -C "$REPOSITORY_ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    SOURCE_DATE_EPOCH=$(git -C "$REPOSITORY_ROOT" log -1 --format=%ct -- unifi-network)
fi
SOURCE_DATE_EPOCH=${SOURCE_DATE_EPOCH:-0}

rm -f -- "$ARCHIVE" "$CHECKSUM"
tar --sort=name --mtime="@$SOURCE_DATE_EPOCH" --owner=0 --group=0 --numeric-owner \
    -C "$STAGING_ROOT" -cf - "$PACKAGE_NAME" | gzip -n > "$ARCHIVE"
(
    cd -- "$OUTPUT_DIR"
    sha256sum "$(basename -- "$ARCHIVE")" > "$(basename -- "$CHECKSUM")"
)

printf '%s\n%s\n' "$ARCHIVE" "$CHECKSUM"
