# UniFi Network Topology for Zabbix

A read-only Zabbix 7.4 dashboard widget that visualizes a UniFi Network topology, links network devices to Zabbix hosts, and overlays the current Zabbix problem severity. The accompanying template collects the required topology JSON from the UniFi controller.

This directory contains the unmodified **UniFi Topologie 0.2.0** frontend module and the supplied **Zabbix 7.4** topology template.

## Contents

- [Features](#features)
- [Screenshot](#screenshot)
- [Requirements](#requirements)
- [Repository layout](#repository-layout)
- [Install the topology template](#install-the-topology-template)
- [Install the frontend module](#install-the-frontend-module)
- [Configure the widget](#configure-the-widget)
- [Host mapping](#host-mapping)
- [Manual devices and connections](#manual-devices-and-connections)
- [Status and problem colors](#status-and-problem-colors)
- [Updates](#updates)
- [Operational limits](#operational-limits)
- [Security and permissions](#security-and-permissions)
- [Removal](#removal)
- [License](#license)

## Features

- Draws UniFi entries with `type=DEVICE` as network nodes.
- Displays automatic wired and wireless connections from the UniFi topology response.
- Shows port numbers, link rates, SSIDs, channels, and experience values when UniFi supplies them.
- Expands a selected device to show directly attached clients, network links, and open Zabbix problems.
- Uses Zabbix's configured severity names and colors.
- Supports exact automatic host-name mapping and explicit MAC-to-host-ID mapping.
- Supports up to 50 optional manual nodes and 100 manual links per widget.
- Marks stale topology or problem data instead of presenting old status as current.
- Performs no direct browser-to-UniFi requests and uses no external JavaScript libraries or CDNs.
- Does not create or modify Zabbix maps, hosts, items, triggers, or UniFi configuration.

The module reads the latest value of the selected `UniFi topology: Raw JSON` text item. Problem and host information is queried through the current frontend user's Zabbix API context.

## Screenshot

<a href="assets/unifi-network-topology-overview.png">
  <img src="assets/unifi-network-topology-overview.png" alt="UniFi Network Topology widget showing switches, access points, a manually added firewall, automatic links, and manual links" width="760">
</a>

*Example topology with automatic UniFi links, manually added connections, host mapping, and Zabbix problem-state colors. Select the image to open it at full resolution.*

## Requirements

- Zabbix 7.4 frontend with support for manifest version 2 widget modules.
- Administrative access to install and enable a frontend module.
- The supplied `UniFi Network Topology` template linked to the UniFi controller host.
- Existing effective host macros:
  - `{$UNIFI_NETWORK_API_ADDRESS}` — hostname or IP address, optionally followed by a port; no `https://` prefix and no path.
  - `{$UNIFI_NETWORK_API_KEY}` — UniFi API key, preferably stored as a Zabbix secret macro.
- DNS and HTTPS connectivity from the responsible Zabbix server or proxy to the UniFi controller.
- A TLS certificate trusted by the responsible Zabbix server or proxy and matching the configured controller name.
- Read access for dashboard users to the topology item and to any Zabbix hosts whose problem status should be displayed.

The supplied template uses the UniFi site name `default` in its request URL. Change management for another site would require a deliberate template modification and is outside this preserved package.

## Repository layout

```text
unifi-network/
├── README.md
├── assets/                                  # README screenshots
└── source/
    ├── docs/original/                     # Supplied external Markdown documents
    ├── modules/unifi_topology/            # Unmodified installable widget 0.2.0
    │   ├── actions/
    │   ├── assets/
    │   ├── includes/
    │   ├── views/
    │   ├── INSTALL_PROXMOX.md
    │   ├── MANUAL_TOPOLOGY.md
    │   ├── README.md
    │   ├── Widget.php
    │   └── manifest.json
    └── templates/
        └── unifi_network_topology_zabbix74.yaml
```

The supplied standalone HTML previews are intentionally not included because they embed a historical topology snapshot with device names and MAC addresses. The nested ZIP is also omitted because its unchanged contents are available directly below `source/modules/unifi_topology/`.

The external `source/docs/original/UniFi_Topologie_Installation.md` describes the older 0.1.0 module. For version 0.2.0 behavior, use this guide and the preserved `source/modules/unifi_topology/README.md` and `MANUAL_TOPOLOGY.md`.

## Install the topology template

Clone the repository and work from its root:

```bash
git clone https://github.com/tseiman/ZabbixWidgets.git
cd ZabbixWidgets
```

In Zabbix:

1. Open **Data collection → Templates → Import**.
2. Import `unifi-network/source/templates/unifi_network_topology_zabbix74.yaml`.
3. On the first import, allow creation of the template and the `Templates/UniFi Topology` template group.
4. Keep **Delete missing** disabled.
5. Review the import preview. It should contain the new template, its group, and five items only.
6. Link **UniFi Network Topology** to the existing UniFi controller host. Do not link it to every discovered UniFi device host.
7. Check the effective `{$UNIFI_NETWORK_API_ADDRESS}` and `{$UNIFI_NETWORK_API_KEY}` values under inherited and host macros. The topology template deliberately does not define or override them.
8. Run **Execute now** for `UniFi topology: Raw JSON`.
9. Under **Monitoring → Latest data**, filter for `UniFi topology` and confirm that the raw JSON and four dependent items receive values.

The template creates these items:

- `unifi.topology.json` — HTTP-agent master item, queried every minute.
- `unifi.topology.vertices` — complete `vertices` array as dependent text.
- `unifi.topology.edges` — complete `edges` array as dependent text.
- `unifi.topology.vertices.count` — number of all returned vertices.
- `unifi.topology.edges.count` — number of all returned edges.

The dependent items do not cause additional controller requests. Vertex and edge counts are inventory counts, not reachability or health indicators.

## Install the frontend module

First identify the actual Zabbix frontend module directory. For packaged Zabbix 7.4 installations it is commonly `/usr/share/zabbix/ui/modules`:

```bash
ls -ld /usr/share/zabbix/ui/modules /usr/share/zabbix/modules 2>/dev/null
```

Use only the path belonging to the frontend actually served by the webserver. Do not copy the module blindly to both locations.

For a confirmed `/usr/share/zabbix/ui/modules` installation, ensure that no previous module directory exists and copy the module:

```bash
test -d /usr/share/zabbix/ui/modules
test ! -e /usr/share/zabbix/ui/modules/unifi_topology
sudo cp -a unifi-network/source/modules/unifi_topology /usr/share/zabbix/ui/modules/
sudo chown -R root:root /usr/share/zabbix/ui/modules/unifi_topology
```

The resulting path must be:

```text
/usr/share/zabbix/ui/modules/unifi_topology/manifest.json
```

The webserver must be able to read the module, but should not be able to modify it. The supplied files and directories use conventional read-only module permissions.

For multiple frontend instances, install the same module on every instance. In containers, place it in a persistent frontend volume; a copy made only inside a running container will disappear when that container is recreated.

In Zabbix:

1. Open **Administration → General → Modules** as a Super Admin.
2. Select **Scan directory**.
3. Enable **UniFi Topologie**.
4. Fully reload the dashboard page.

No Zabbix server restart and no additional API token are required. If an enabled PHP OPcache retains old files, refresh it using the site's normal administrative procedure.

## Configure the widget

Edit a dashboard and add **UniFi Topologie**. Configure:

- **Topologie-Master-Item** — select `UniFi topology: Raw JSON` on the controller host.
- **Refresh interval** — normally one minute, matching the template item.
- **Exact Zabbix host name with `{name}`** — mapping pattern described below.
- **Optional mapping** — explicit `MAC=Host-ID` entries.
- **Additional devices** — optional manual nodes.
- **Additional connections** — optional manual links.
- **Maximum topology age** — 60 to 86,400 seconds; default: 300 seconds.
- **Include suppressed problems** — disabled by default.

The widget displays only topology nodes whose JSON `type` is `DEVICE` in the main graph. UniFi clients, including client-classified cameras, appear in the selected device's detail panel instead.

## Host mapping

Host mapping lets a topology node display real problems from its corresponding Zabbix host.

### Exact name pattern

The default pattern is:

```text
UniFi Network - Site: "Default" - Device: "{name}"
```

`{name}` is replaced exactly with the device name from the UniFi topology JSON. The widget checks both the technical and visible Zabbix host names, without fuzzy substring matching. The pattern must contain `{name}` exactly once, or be empty.

If no host is found, more than one host matches, or one host would be attached to multiple topology nodes, the affected node remains gray with an unknown mapping status.

### Explicit MAC mapping

Use one line per device:

```text
aa:bb:cc:dd:ee:ff=12345
```

Replace the example with the real UniFi device MAC address and numeric Zabbix host ID. Comments start with `#`. Explicit mapping takes precedence over the name pattern. Clear the name pattern to use explicit mappings exclusively.

## Manual devices and connections

Version 0.2.0 can add non-UniFi or missing infrastructure to one widget without changing the source JSON or Zabbix objects.

### Additional devices

Use one line per node:

```text
id | Display name | optional Zabbix host ID
```

Example:

```text
internet | Internet
firewall | Firewall | 12345
remote_site | Remote site
```

Rules:

- IDs begin with a lowercase letter and contain lowercase letters, digits, `_`, or `-`.
- IDs are at most 40 characters.
- Display names are at most 100 characters and cannot contain `|`.
- A positive numeric Zabbix host ID is optional.
- Without a host ID, the widget shows **Manual · status unknown** rather than inventing a healthy state.

### Additional connections

Use one line per link:

```text
from | to | optional label
```

An endpoint is either a manual node ID or the real MAC address of a UniFi network device. Example values below are placeholders:

```text
internet | firewall | WAN
firewall | aa:bb:cc:dd:ee:01 | Internal LAN
```

Manual links are dashed and marked as manual. An automatic UniFi link between the same endpoints takes precedence. Missing endpoints produce a visible warning and leave the saved configuration intact.

Empty lines and lines beginning with `#` are ignored. Duplicate IDs, duplicate links, self-links, invalid host IDs, and malformed endpoints are rejected when the widget is saved.

## Status and problem colors

- The highest severity among currently visible open problems determines the node border color.
- Severity names and colors come from the Zabbix configuration.
- Acknowledging a problem does not remove it. The highlight disappears after the problem is resolved and the widget refreshes successfully.
- Suppressed problems are excluded by default and can be enabled per widget.
- Selecting a problem opens its Zabbix event.
- Green means that the uniquely mapped, monitored host has no open problems visible to the current user under the selected filter. It is not an independent availability measurement.
- Missing or ambiguous mappings, disabled host monitoring, manually added nodes without a host, and stale status are gray.
- Existing open problems remain visible when host monitoring is disabled.
- API or frontend errors replace the status with an error instead of displaying old data as current.

Gray connection lines represent topology only. They are not port alarms. Device problems color the device, not its links.

## Updates

Start from a clean repository and retrieve the latest version:

```bash
cd ZabbixWidgets
git status --short
git pull --ff-only
```

Back up the installed module before replacing it:

```bash
sudo tar -czf /var/backups/unifi_topology-$(date +%Y%m%d-%H%M%S).tar.gz -C /usr/share/zabbix/ui/modules unifi_topology
```

**Warning:** The next command removes only `/usr/share/zabbix/ui/modules/unifi_topology`. Run it only after the backup succeeds and only when `/usr/share/zabbix/ui/modules` is the confirmed active frontend path.

```bash
sudo rm -rf /usr/share/zabbix/ui/modules/unifi_topology
sudo cp -a unifi-network/source/modules/unifi_topology /usr/share/zabbix/ui/modules/
sudo chown -R root:root /usr/share/zabbix/ui/modules/unifi_topology
```

Then scan the module directory in Zabbix and fully reload the dashboard. Keep the module directory name and module ID `unifi_topology`; existing master-item selection, mappings, manual topology, and refresh settings are stored with the dashboard widget.

Import an updated topology template only when its release documentation requires it. Keep **Delete missing** disabled and review the import comparison before applying changes.

## Operational limits

- Automatic topology: at most 1,000 vertices and 3,000 edges per response.
- Open problems: at most 2,000; the widget rejects an incomplete result rather than silently truncating status.
- Manual topology: at most 50 nodes and 100 links per widget.
- Manual input line: at most 500 bytes.
- Manual link label: at most 150 characters.
- Default stale-data threshold: 300 seconds.
- Widget zoom range: 60% to 240%.
- Template request interval: one minute.
- Raw topology and dependent text history: one day.
- Count history: 30 days; count trends: 365 days.

The UniFi topology endpoint is internal and may change with controller updates. The template requires HTTP 200, rejects redirects, and verifies both TLS peer and host name. On collection failure, Zabbix retains the previous item values; always check timestamps when diagnosing stale data.

If UniFi reports `has_unknown_switch=true`, the widget warns that intermediate devices may be missing. Manual additions do not prove that the automatic topology is complete.

## Security and permissions

- The topology collection performs HTTPS `GET` requests only.
- Store `{$UNIFI_NETWORK_API_KEY}` as a secret host macro.
- The browser does not receive or use the UniFi API key.
- The widget performs no direct database queries and makes no direct browser-side requests to UniFi.
- Host and problem lookups use the logged-in frontend user's Zabbix API permissions.
- Users need read access to the topology item and each mapped host whose problems they should see.
- Do not use disabled TLS verification as a permanent workaround. A successful `curl -k` request does not prove that Zabbix can validate the controller certificate.
- No external CDN, JavaScript library, or additional service is used.

## Removal

1. Remove the widget from dashboards.
2. Disable **UniFi Topologie** under **Administration → General → Modules**.
3. Remove the `unifi_topology` module directory only after it is disabled.

Removing the frontend module does not remove or modify the topology template or collected item data.

## License

No project-specific license file was included with the supplied UniFi widget package. See the repository-level [LICENSE](../LICENSE) for the repository's license information.