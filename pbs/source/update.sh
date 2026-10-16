#!/bin/sh
set -eu
pbs_source=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
pbs_frontend=${1:?Usage: update.sh /path/to/zabbix/frontend [backup-directory]}
pbs_backup_root=${2:-/var/backups/pbs-dashboard}
if [ -f "$pbs_frontend/ui/zabbix.php" ] && [ -d "$pbs_frontend/ui/include/classes/core" ] && [ -d "$pbs_frontend/ui/widgets" ]; then
    pbs_frontend="$pbs_frontend/ui"
fi
if [ ! -f "$pbs_frontend/zabbix.php" ] || [ ! -d "$pbs_frontend/include/classes/core" ] || [ ! -d "$pbs_frontend/widgets" ]; then
    echo "No complete Zabbix frontend found: $pbs_frontend" >&2
    exit 1
fi
for pbs_name in pbs_longest pbs_summary pbs_datastore pbs_running pbs_tasks; do
    if [ ! -f "$pbs_source/modules/$pbs_name/manifest.json" ] || [ ! -f "$pbs_source/modules/$pbs_name/Widget.php" ]; then
        echo "Package is incomplete: $pbs_name" >&2
        exit 1
    fi
    if [ -L "$pbs_frontend/modules/$pbs_name" ]; then
        echo "Target is a symbolic link; use the actual module directory: $pbs_name" >&2
        exit 1
    fi
done
pbs_backup="$pbs_backup_root/$(date +%Y%m%d-%H%M%S)-$$"
mkdir -p "$pbs_backup"
for pbs_name in pbs_longest pbs_summary pbs_datastore pbs_running pbs_tasks; do
    if [ -e "$pbs_frontend/modules/$pbs_name" ]; then
        cp -pR "$pbs_frontend/modules/$pbs_name" "$pbs_backup/"
    fi
done
echo "Backup: $pbs_backup"
mkdir -p "$pbs_frontend/modules"
for pbs_name in pbs_longest pbs_summary pbs_datastore pbs_running pbs_tasks; do
    mkdir -p "$pbs_frontend/modules/$pbs_name"
    cp -R "$pbs_source/modules/$pbs_name/." "$pbs_frontend/modules/$pbs_name/"
done
echo "PBS Dashboard 1.0.6 installed: $pbs_frontend/modules"
echo "Next: Administration → General → Modules → Scan directory; enable the PBS modules."
echo "No template import is required when upgrading from 1.0.5. Reload the dashboard completely."
echo "For task logs, configure the PBS connection once as described in README.md."
