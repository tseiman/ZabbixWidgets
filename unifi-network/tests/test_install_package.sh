#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
PROJECT_DIR=$(CDPATH='' cd -- "$SCRIPT_DIR/.." && pwd -P)
WORK=$(mktemp -d)
cleanup() {
    rm -rf -- "$WORK"
}
trap cleanup EXIT
trap 'exit 130' INT TERM HUP

OUTPUT="$WORK/output"
mkdir -p -- "$OUTPUT"
"$PROJECT_DIR/scripts/build-install-archive.sh" "$OUTPUT" test >/dev/null

SECOND_OUTPUT="$WORK/output-second"
mkdir -p -- "$SECOND_OUTPUT"
"$PROJECT_DIR/scripts/build-install-archive.sh" "$SECOND_OUTPUT" test >/dev/null
cmp "$OUTPUT"/*.tar.gz "$SECOND_OUTPUT"/*.tar.gz
cmp "$OUTPUT"/*.tar.gz.sha256 "$SECOND_OUTPUT"/*.tar.gz.sha256

shopt -s nullglob
archives=("$OUTPUT"/*.tar.gz)
checksums=("$OUTPUT"/*.tar.gz.sha256)
[[ ${#archives[@]} -eq 1 ]]
[[ ${#checksums[@]} -eq 1 ]]
(
    cd -- "$OUTPUT"
    sha256sum -c "$(basename -- "${checksums[0]}")"
)

if tar -tzf "${archives[0]}" | grep -Ei '(^|/)(readme[^/]*|[^/]+\.md)$' >/dev/null; then
    printf 'Archive unexpectedly contains Markdown documentation.\n' >&2
    exit 1
fi

EXTRACT="$WORK/extract"
mkdir -p -- "$EXTRACT"
tar -xzf "${archives[0]}" -C "$EXTRACT"
mapfile -t package_roots < <(find "$EXTRACT" -mindepth 1 -maxdepth 1 -type d -print)
[[ ${#package_roots[@]} -eq 1 ]]
PACKAGE_ROOT=${package_roots[0]}
[[ -x "$PACKAGE_ROOT/install.sh" ]]
[[ -f "$PACKAGE_ROOT/module/manifest.json" ]]
[[ -f "$PACKAGE_ROOT/module/Widget.php" ]]
[[ -f "$PACKAGE_ROOT/module/includes/I18n.php" ]]
[[ -f "$PACKAGE_ROOT/module/includes/i18n.json" ]]
[[ -f "$PACKAGE_ROOT/template/unifi_network_topology_zabbix74.yaml" ]]

MODULE_ROOT="$WORK/custom modules"
mkdir -p -- "$MODULE_ROOT"
"$PACKAGE_ROOT/install.sh" "$MODULE_ROOT"
TARGET="$MODULE_ROOT/unifi_topology"
[[ -f "$TARGET/manifest.json" ]]
[[ -f "$TARGET/Widget.php" ]]
[[ -f "$TARGET/includes/I18n.php" ]]
[[ -f "$TARGET/includes/i18n.json" ]]
[[ $(stat -c '%a' "$TARGET") == 755 ]]
[[ $(stat -c '%a' "$TARGET/manifest.json") == 644 ]]
if find "$TARGET" -type f \( -iname '*.md' -o -iname 'readme*' \) -print -quit | grep -q .; then
    printf 'Installation unexpectedly contains Markdown documentation.\n' >&2
    exit 1
fi

touch "$TARGET/stale-file-from-previous-version"
printf 'legacy documentation\n' > "$TARGET/README.md"
printf 'legacy documentation\n' > "$TARGET/INSTALL_PROXMOX.md"
"$PACKAGE_ROOT/install.sh" "$MODULE_ROOT"
[[ ! -e "$TARGET/stale-file-from-previous-version" ]]
[[ ! -e "$TARGET/README.md" ]]
[[ ! -e "$TARGET/INSTALL_PROXMOX.md" ]]
diff -ru "$PACKAGE_ROOT/module" "$TARGET"

REPOSITORY_MODULE_ROOT="$WORK/repository modules"
mkdir -p -- "$REPOSITORY_MODULE_ROOT"
"$PROJECT_DIR/install.sh" "$REPOSITORY_MODULE_ROOT"
diff -ru "$PACKAGE_ROOT/module" "$REPOSITORY_MODULE_ROOT/unifi_topology"

if "$PACKAGE_ROOT/install.sh" "$TARGET" >"$WORK/wrong-target.out" 2>&1; then
    printf 'Installer accepted the module directory instead of its parent.\n' >&2
    exit 1
fi

SYMLINK_MODULE_ROOT="$WORK/symlink modules"
mkdir -p -- "$SYMLINK_MODULE_ROOT" "$WORK/symlink destination"
ln -s "$WORK/symlink destination" "$SYMLINK_MODULE_ROOT/unifi_topology"
if "$PACKAGE_ROOT/install.sh" "$SYMLINK_MODULE_ROOT" >"$WORK/symlink-target.out" 2>&1; then
    printf 'Installer accepted a symbolic-link module target.\n' >&2
    exit 1
fi
[[ -L "$SYMLINK_MODULE_ROOT/unifi_topology" ]]

"$PACKAGE_ROOT/install.sh" --help | grep -Fq '/usr/share/zabbix/ui/modules'

printf 'Package and installer test passed: %s\n' "$(basename -- "${archives[0]}")"
