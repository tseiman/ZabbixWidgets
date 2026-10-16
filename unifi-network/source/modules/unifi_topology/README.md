# UniFi Topology — Dashboard Widget for Zabbix 7.4

Version 0.3.3 · RX/TX history and configurable manual devices and links

The widget reads the `UniFi topology: Raw JSON` text item and renders the network devices. Zabbix history from traffic items discovered by the supplied template provides per-device RX/TX mini charts and an expandable dialog. Selecting the rest of a node opens its directly attached clients, network links, and Zabbix problems. The frontend does not create or modify maps, hosts, items, or triggers and does not contact UniFi directly.

## Changes in 0.3.3

- Manually added topology nodes, such as a LANCOM device not managed by UniFi, have no traffic chart icon because UniFi supplies no traffic information for them.
- Every automatically discovered UniFi switch, access point, and other network device retains its traffic chart icon. The decision does not depend on a separate `unifiDevice` or `managed` property.
- Devices without history in the selected period retain their chart icon and display the existing no-data state instead of fabricated values.

## Changes retained from 0.3.1

- Every automatically discovered UniFi network device has its own MAC-bound mini chart in the upper-right corner. Pure SVG positioning prevents global Zabbix button rules from moving charts outside their device cards.
- Mini and large charts use translucent green area fills.
- Orange UniFi warnings include a **×**. Dismissal lasts only for the current dashboard page; a full reload shows the warning again.

## Installation and update

The rolling installable archive is rebuilt whenever `unifi-network/` changes. It contains:

- `install.sh` — installer for new installations and updates;
- `module/` — only the widget runtime files;
- `template/unifi_network_topology_zabbix74.yaml` — imported separately into Zabbix.

Markdown source documents are not included in the archive or installed module. Download the current archive and matching `.sha256` file from:

https://github.com/tseiman/ZabbixWidgets/releases/tag/unifi-network-latest

Place both files in an empty directory and verify the archive before extracting it:

```bash
cd ~/Downloads/unifi-topology
archive=$(find . -maxdepth 1 -type f -name 'unifi_topology-*.tar.gz' -print)
test -n "$archive"
test "$(printf '%s\n' "$archive" | grep -c .)" -eq 1
sha256sum -c "$archive.sha256"
```

Continue only when `sha256sum` reports `OK`. Then extract and install into the default Zabbix module root:

```bash
tar -xzf "$archive"
package_dir=${archive%.tar.gz}
sudo "$package_dir/install.sh"
```

The resulting manifest is:

```text
/usr/share/zabbix/ui/modules/unifi_topology/manifest.json
```

For another verified frontend module root, pass its parent directory:

```bash
sudo "$package_dir/install.sh" /opt/zabbix/ui/modules
```

Do not pass the existing `unifi_topology` directory itself; the installer appends that name. It stages a complete replacement on the same filesystem, rejects symbolic-link targets, uses directory mode `0755` and file mode `0644`, and rolls back if replacement fails. It never invokes `sudo` itself and does not change credentials or Zabbix configuration.

The installer can also run from a repository checkout:

```bash
git clone https://github.com/tseiman/ZabbixWidgets.git
cd ZabbixWidgets
sudo ./unifi-network/install.sh
```

After installation or update:

1. Open **Administration → General → Modules → Scan directory** as a Zabbix Super Admin.
2. Enable **UniFi topology**, or verify the detected version.
3. Fully reload the dashboard.
4. For a new widget, select the controller host's **UniFi topology: Raw JSON** item as **Topology master item**.

No Zabbix server restart or extra API token is required. If PHP OPcache retains old files, refresh it through the site's normal administrative procedure.

Version 0.3.0 and later require the supplied template. Import `template/unifi_network_topology_zabbix74.yaml` with **Delete missing** disabled. The template must be linked to the same controller host as the selected topology master item. Low-level discovery creates RX/TX items for `usw` and `uap` devices.

## Traffic charts

Blue shows RX and green shows TX. The template prefers the sum of `rx_bytes-r` and `tx_bytes-r` over every unique physical port and converts byte/s to bit/s. If those values are absent, Zabbix uses the change per second of summed `rx_bytes` and `tx_bytes` counters. Logical LAG, bond, and aggregate interfaces and duplicate port indexes are excluded. For an access point without a usable `port_table`, the physical `uplink` is the fallback source.

**Traffic time period** follows the dashboard time selector by default. The backend retrieves bounded Zabbix history or trend aggregates with at most 320 points per underlying item. An automatically discovered UniFi device without matching history retains an empty chart and reports that no data is available. A manually added topology node receives no traffic chart.

## Mapping topology devices to Zabbix hosts

The default exact-name pattern is:

```text
UniFi Network - Site: "Default" - Device: "{name}"
```

`{name}` is replaced exactly by the topology JSON device name. The widget checks both technical and visible Zabbix host names without fuzzy matching. Adapt the pattern for another site or naming convention.

If no host is found, several hosts match, or one host would map to multiple topology nodes, the node remains gray with an unknown status. Explicit mapping takes precedence. Enter one mapping per line:

```text
aa:bb:cc:dd:ee:ff=12345
```

Replace the example with the actual UniFi device MAC and numeric Zabbix host ID. Clear the name pattern to use only explicit mappings. Lines beginning with `#` are comments.

## Manual devices and links

Optional manual additions are stored only in the dashboard widget. See `MANUAL_TOPOLOGY.md` for syntax and examples. A manual node can map to a Zabbix host and show its problem state, but it is explicitly marked as not UniFi-managed and never receives a traffic chart icon.

## Problem colors

- The highest severity among visible open problems determines the node border.
- Severity labels and colors come from Zabbix configuration.
- Acknowledgement alone does not remove the highlight; resolution does.
- Suppressed problems are excluded by default and can be included in widget settings.
- Selecting a problem opens its Zabbix event.
- Green means the uniquely mapped, monitored host has no open problems visible to the current user under the selected filter. It is not a separate availability check.
- Missing or ambiguous mappings, disabled monitoring, stale status, and manual nodes without a host are gray.
- API or frontend errors replace old status rather than presenting it as current.

The logged-in user needs read permission for the topology item, controller traffic items, and mapped hosts. No credentials are stored in the browser and no direct database query is used.

## Operation and limits

- The overview renders nodes with `type=DEVICE`; clients appear in the selected device's details.
- Select a node, or press **Enter** or **Space**, to toggle details.
- Wired clients show port and link rate when available; wireless clients show SSID, channel, and experience.
- Gray link lines are topology information, not port alarms.
- Stale data is gray and clearly marked.
- `has_unknown_switch=true` creates a dismissible warning because intermediate devices may be missing.
- Limits per response: 1,000 nodes, 3,000 edges, and 2,000 open problems.
- Manual limits: 50 nodes and 100 links per widget.

## Verification checklist

1. Expected UniFi switches and access points are visible.
2. Every automatically discovered UniFi device has one mini chart bound to that device.
3. Manually added topology nodes have no mini chart.
4. Selecting each chart opens the matching device's RX/TX dialog.
5. The dialog follows dashboard time-period changes.
6. The mapped host shown in details is correct.
7. Existing Zabbix problems use the configured severity color.
8. Dismissing an orange warning survives widget refresh but not a full dashboard reload.

Do not create alarms deliberately; use existing problems and normal resolution for verification.

## Removal

Remove the widget from dashboards, disable **UniFi topology** under **Administration → General → Modules**, and only then remove the module directory. Removing the frontend module does not alter templates or collected data.

## Technical references

- https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/history/get
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/problem/get
- https://github.com/zabbix/zabbix/tree/release/7.4/ui/widgets

All runtime source files are contained in the module. No external JavaScript library, CDN, or separate service is loaded.
