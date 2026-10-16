# Installation in a Proxmox Zabbix Container

The Proxmox community script installs Zabbix as Debian packages with Apache inside an LXC container. Install the widget in the Zabbix container, not on the Proxmox host.

> This document has been updated for the current rolling package. The standard and tested Zabbix 7.4 module root is `/usr/share/zabbix/ui/modules`.

## 1. Verify the active module root

Select the Zabbix container in Proxmox and open its **Console**. Run:

```bash
ls -ld /usr/share/zabbix/ui/modules /usr/share/zabbix/modules 2>/dev/null
```

For Zabbix 7.4, `/usr/share/zabbix/ui/modules` is normally expected. If both paths or neither path exists, inspect the active Apache/frontend configuration first. Do not copy the module blindly into both locations.

## 2. Download and verify the package

Download the current archive and matching `.sha256` sidecar from:

https://github.com/tseiman/ZabbixWidgets/releases/tag/unifi-network-latest

Transfer both files into an empty directory in the **Zabbix container**. Then verify them:

```bash
cd /tmp/unifi-topology
archive=$(find . -maxdepth 1 -type f -name 'unifi_topology-*.tar.gz' -print)
test -n "$archive"
test "$(printf '%s\n' "$archive" | grep -c .)" -eq 1
sha256sum -c "$archive.sha256"
```

Continue only when the checksum reports `OK`. Both files must come from the intended HTTPS release.

## 3. Install or update

Extract and run the packaged installer:

```bash
tar -xzf "$archive"
package_dir=${archive%.tar.gz}
sudo "$package_dir/install.sh"
```

When already operating as root in the container, omit `sudo`:

```bash
"$package_dir/install.sh"
```

The installer targets:

```text
/usr/share/zabbix/ui/modules/unifi_topology/manifest.json
```

For a different verified module root, pass the parent directory as the only argument:

```bash
"$package_dir/install.sh" /usr/share/zabbix/modules
```

Do not pass the complete `.../unifi_topology` target. The installer appends that name, rejects symbolic-link targets, stages a complete replacement, and rolls back on failure.

## 4. Enable the module

1. Open **Administration → General → Modules → Scan directory** as a Zabbix Super Admin.
2. Enable **UniFi topology**, or verify the updated version.
3. Fully reload the dashboard.
4. Add or edit the **UniFi topology** widget.
5. Select the controller host's **UniFi topology: Raw JSON** item as **Topology master item**.
6. Use a one-minute refresh interval unless your collection interval differs.

A Zabbix server restart is not required. If PHP OPcache keeps old module files, refresh it through the site's normal administration procedure.

## 5. Verify status and charts

- Confirm the expected count of network devices and mapped hosts.
- Select a managed UniFi switch and verify its displayed Zabbix host.
- Compare an existing open problem color with the Zabbix problem list.
- Verify every UniFi-managed switch or access point has one traffic mini chart.
- Verify manually added and other non-UniFi-managed devices have no traffic chart icon.
- Change the dashboard time period and confirm the large RX/TX dialog follows it.

Do not trigger alarms deliberately. Use existing problems and their normal resolution.

Community script reference: https://github.com/community-scripts/ProxmoxVE/blob/main/install/zabbix-install.sh
