# UniFi Topology — Dashboard Widget for Zabbix 7.4

Version 0.1.0 · First installable release

> Historical document translated to English. It describes version 0.1.0 and is retained only as source history. Use `unifi-network/README.md` for the current release.

The widget reads the existing `UniFi topology: Raw JSON` text item and renders network devices. Selecting a node opens its clients, network links, and Zabbix problems. It does not create maps, hosts, items, or triggers and does not make additional UniFi requests.

## Installation

The original ZIP contained exactly one `unifi_topology` directory. That directory belonged in the existing `modules` directory of the **Zabbix web frontend**. It had to be available on every frontend instance.

1. Transfer the ZIP to the system running the Zabbix web frontend and extract it.
2. Copy `unifi_topology` into the frontend's `modules` directory. `unifi_topology/manifest.json` must be directly below it, without an extra directory level.
3. Open **Administration → General → Modules → Scan directory** as a Zabbix Super Admin.
4. Enable **UniFi topology**.
5. Reload the dashboard, edit it, and add a **UniFi topology** widget.
6. Select the controller host's **UniFi topology: Raw JSON** item as **Topology master item**.
7. Set the refresh interval to **1 minute** and save the dashboard.

Typical frontend roots were `/usr/share/zabbix` and `/usr/share/zabbix/ui`, depending on packaging. The module had to be installed in the directory used by the active web frontend. For Docker, it had to be stored in a persistent frontend volume; a copy made only inside a running container would disappear when the container was recreated.

Files had to be readable by the web server, typically mode `0644` for files and `0755` for directories, but not writable by it. No Zabbix server restart or additional API token was required. If PHP OPcache retained old files, its cache had to be refreshed through normal administration.

The community template and separate topology template remained unchanged. This was a frontend module, not a YAML import.

## Mapping topology devices to Zabbix hosts

For a device such as `switch3` to show its actual warnings, it had to map unambiguously to the discovered Zabbix host.

The default name pattern was:

```text
UniFi Network - Site: "Default" - Device: "{name}"
```

`{name}` was replaced exactly by the name from the topology JSON. The widget checked both technical and visible Zabbix host names without fuzzy substring matches. Different site names or naming schemes required an adjusted pattern.

The header showed a value such as **8/8 hosts mapped**. Selecting a device displayed its host ID and complete host name so the mapping could be verified during initial deployment.

If no host was found or more than one matched, the node stayed gray with **Status unknown**. A host that would map to several topology devices was also rejected automatically.

As an alternative, **Optional mapping** accepted one device per line:

```text
aa:bb:cc:dd:ee:ff=12345
```

This is a fictitious syntax example. It had to be replaced with the actual MAC address and Zabbix host ID. The host ID could be read from the `hostid` URL parameter when opening a host. Explicit mapping took precedence over the name pattern. Clearing the name pattern enabled explicit-only mapping. Comments began with `#`.

## Problem colors

- The highest severity among open problems determined the network device border.
- Badge and text showed the severity and problem count.
- Names and colors came from Zabbix settings, including custom colors.
- Acknowledging a problem alone did not remove the highlight. It disappeared after resolution and the next successful refresh.
- Suppressed problems were hidden by default and could be included by widget configuration.
- Selecting a problem opened the corresponding Zabbix event.
- A green border meant **no open problem visible to the logged-in user under the selected filter**, not independently measured reachability.
- Disabled host monitoring, missing or ambiguous mapping, and stale problem status were gray. Existing open problems for a disabled host remained visible.
- Connection or API failures replaced the old status with an error rather than presenting it as current.

The logged-in user needed read permission for the topology item and relevant device hosts. Normal Zabbix permissions also applied to problems. No credentials were stored in the browser and no direct database query was used.

## Operation and topology

- The overview used nodes with `type=DEVICE`; clients were initially hidden.
- Selecting a device, or pressing **Enter** or **Space**, toggled its directly attached clients and details.
- Clients behind another switch appeared in that switch's detail area.
- Wired links showed port and rate when supplied.
- Wireless links showed SSID, channel, and experience when supplied.
- Links between network devices showed port numbers in the overview and link rates in tooltips and details.
- Plus and minus controlled zoom; large views could be scrolled.
- Devices without a supplied link remained visible as separate nodes. The widget did not invent cables or gateways.
- Gray lines represented topology, not port alarms. A memory warning colored the device, not its links.
- Automatic problem highlighting was limited to the network devices in the overview.

Selection intentionally used `type=DEVICE`, not `unifiDevice`, because the sample also contained UniFi cameras classified as `CLIENT`.

## Freshness and limits

The widget refreshed while the dashboard page was active. Current problem status was read on every refresh. Topology came from the latest stored master-item value.

Topology data older than 300 seconds was marked stale by default. An inactive or unsupported source item also produced a notice. The topology timestamp and problem-query time appeared in the footer. If status was not refreshed within the configured age limit, nodes became gray until a successful refresh restored current status.

`has_unknown_switch=true` produced a visible warning. Clients behind an unknown switch or bridge could all appear on the same uplink port, so the widget did not claim a direct physical connection.

This release targeted a regular dashboard with a selected controller item. Automatic host overrides, inherited template dashboards, port-trigger mapping, persistent free node placement, and expanding clients into graph nodes were not included.

Limits per response were 1,000 nodes, 3,000 edges, and 2,000 open problems. Exceeding them produced an error instead of silently showing incomplete data.

## Historical test state

- PHP files were syntax-checked against PHP 8.5 in an isolated WebAssembly environment and against PHP 8.0 syntax.
- The backend was tested with simulated Zabbix API responses for host mapping, ambiguity, warnings, acknowledgement, resolution, suppression, invalid data, missing hosts, and errors.
- Rendering and pointer/keyboard operation were tested in a browser with a complete older UniFi example.
- Automated DOM tests covered highest severity, custom colors, resolution, unknown and stale status, escaping, zoom, and cycles.
- Interfaces were compared with the official Zabbix `release/7.4` source branch.
- No live integration test against the target Zabbix installation had yet been performed.

The separate HTML preview contained an older topology snapshot and a simulated `switch3` warning. It ran without Zabbix. Its sample host IDs were not target-system configuration. The installable ZIP contained neither the snapshot nor API keys.

## Initial live verification

1. Confirm the expected network devices appear.
2. Confirm the header reports the expected mapped-host count.
3. Confirm details for `switch3` show the correct Zabbix host.
4. If the memory warning is still open, compare its color with the Zabbix problem list.
5. Confirm the problem link opens the correct event.
6. Confirm expected wireless clients appear for an access point.
7. After normal problem resolution, confirm it disappears after refresh.

No alarm should be triggered deliberately; existing problems and normal resolution were sufficient.

## Removal

Remove the widget from dashboards and disable the module under **Administration → General → Modules**. This does not affect templates or collection. Remove the module directory only after disabling it.

## Technical references

- https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/history/get
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/problem/get
- https://github.com/zabbix/zabbix/tree/release/7.4/ui/widgets

All source files were contained in the module; no external JavaScript library, CDN, or separate service was loaded.
