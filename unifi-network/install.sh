#!/usr/bin/env bash
set -Eeuo pipefail

DEFAULT_MODULE_ROOT="/usr/share/zabbix/ui/modules"
SCRIPT_DIR=$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)

usage() {
    cat <<'EOF'
Usage: install.sh [ZABBIX_MODULE_ROOT]

Install or update the UniFi Network Topology frontend module.

ZABBIX_MODULE_ROOT is the directory that contains Zabbix frontend modules.
Default: /usr/share/zabbix/ui/modules

Examples:
  sudo ./install.sh
  sudo ./install.sh /opt/zabbix/ui/modules

Pass the parent modules directory, not an existing unifi_topology directory.
EOF
}

case ${1-} in
    -h|--help)
        usage
        exit 0
        ;;
esac

if (( $# > 1 )); then
    usage >&2
    exit 2
fi

MODULE_ROOT=${1:-$DEFAULT_MODULE_ROOT}

if [[ -d "$SCRIPT_DIR/module" ]]; then
    PAYLOAD="$SCRIPT_DIR/module"
elif [[ -d "$SCRIPT_DIR/source/modules/unifi_topology" ]]; then
    PAYLOAD="$SCRIPT_DIR/source/modules/unifi_topology"
else
    printf 'Error: module payload not found next to the installer or in the repository source tree.\n' >&2
    exit 1
fi

if [[ ! -d "$MODULE_ROOT" ]]; then
    printf 'Error: Zabbix module root does not exist: %s\n' "$MODULE_ROOT" >&2
    exit 1
fi

MODULE_ROOT=$(CDPATH='' cd -- "$MODULE_ROOT" && pwd -P)
TARGET="$MODULE_ROOT/unifi_topology"

if [[ $(basename -- "$MODULE_ROOT") == unifi_topology ]]; then
    printf 'Error: pass the parent modules directory, not the unifi_topology directory itself.\n' >&2
    exit 1
fi
if [[ ! -w "$MODULE_ROOT" ]]; then
    printf 'Error: Zabbix module root is not writable: %s\nRun the installer with sudo when installing into a system directory.\n' "$MODULE_ROOT" >&2
    exit 1
fi
if [[ -L "$TARGET" ]]; then
    printf 'Error: refusing to replace symbolic-link target: %s\n' "$TARGET" >&2
    exit 1
fi
if [[ -e "$TARGET" && ! -d "$TARGET" ]]; then
    printf 'Error: target exists but is not a directory: %s\n' "$TARGET" >&2
    exit 1
fi
if [[ ! -f "$PAYLOAD/manifest.json" || ! -f "$PAYLOAD/Widget.php" ]]; then
    printf 'Error: incomplete module payload: %s\n' "$PAYLOAD" >&2
    exit 1
fi
if find "$PAYLOAD" -type l -print -quit | grep -q .; then
    printf 'Error: symbolic links are not allowed in the module payload.\n' >&2
    exit 1
fi

STAGING=$(mktemp -d "$MODULE_ROOT/.unifi_topology.install.XXXXXX")
PREVIOUS=""
cleanup() {
    local status=$?
    if [[ -n "$PREVIOUS" && -d "$PREVIOUS" && ! -e "$TARGET" ]]; then
        mv -- "$PREVIOUS" "$TARGET" || true
        PREVIOUS=""
    fi
    if [[ -n "$STAGING" && -d "$STAGING" ]]; then
        rm -rf -- "$STAGING"
    fi
    if [[ -n "$PREVIOUS" && -d "$PREVIOUS" ]]; then
        printf 'Warning: previous installation remains at %s\n' "$PREVIOUS" >&2
    fi
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT TERM HUP

install -d -m 0755 "$STAGING"
while IFS= read -r -d '' source_dir; do
    relative=${source_dir#"$PAYLOAD"/}
    [[ "$source_dir" == "$PAYLOAD" ]] && continue
    install -d -m 0755 "$STAGING/$relative"
done < <(find "$PAYLOAD" -type d -print0)

while IFS= read -r -d '' source_file; do
    relative=${source_file#"$PAYLOAD"/}
    case "$relative" in
        *.md|*.MD) continue ;;
    esac
    install -D -m 0644 "$source_file" "$STAGING/$relative"
done < <(find "$PAYLOAD" -type f -print0)

if [[ ! -f "$STAGING/manifest.json" || ! -f "$STAGING/Widget.php" ]]; then
    printf 'Error: staged module failed validation.\n' >&2
    exit 1
fi
if find "$STAGING" -type f \( -iname '*.md' -o -iname 'readme*' \) -print -quit | grep -q .; then
    printf 'Error: documentation unexpectedly entered the staged module.\n' >&2
    exit 1
fi

if [[ -d "$TARGET" ]]; then
    PREVIOUS="$MODULE_ROOT/.unifi_topology.previous.$$"
    if [[ -e "$PREVIOUS" ]]; then
        printf 'Error: temporary rollback path already exists: %s\n' "$PREVIOUS" >&2
        exit 1
    fi
    mv -- "$TARGET" "$PREVIOUS"
fi

mv -- "$STAGING" "$TARGET"
STAGING=""

if [[ -n "$PREVIOUS" ]]; then
    rm -rf -- "$PREVIOUS"
    PREVIOUS=""
fi

trap - EXIT INT TERM HUP
printf 'Installed UniFi Network Topology module in %s\n' "$TARGET"
printf 'Next: scan the module directory in Zabbix and enable or reload UniFi Network Topology.\n'
