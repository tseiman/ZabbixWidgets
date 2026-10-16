# UniFi Network Topology — Zabbix 7.4

Standalone template in its own **Templates/UniFi Topology** group. The community template is neither linked nor modified. It performs only `GET` requests to UniFi.

> This is a historical document for the original standalone topology template. See [`../../../README.md`](../../../README.md) for the current release.

## Usage

1. Open **Data collection → Templates → Import** and select `unifi_network_topology_zabbix74.yaml`. On the first import, enable **Create new** for the template and template group. Keep **Delete missing** disabled. The import preview should show only the new template with five items and its group.
2. Open the original controller host under **Data collection → Hosts**. In addition to the existing **UniFi Network API** template, link **UniFi Network Topology**. Do not link it to every discovered device host.
3. Check the existing macros on the controller host:
   - `{$UNIFI_NETWORK_API_ADDRESS}`: host name or IP address, optionally followed by `:port`; do not include `https://` or a path. Example: `unifi.example.net`.
   - `{$UNIFI_NETWORK_API_KEY}`: the existing API key, preferably stored as **Secret text**.

   The new template defines no macro defaults. If the values were previously inherited only from the community template, verify their effective values under **Inherited and host macros**.
4. Select **Execute now** for the **UniFi topology: Raw JSON** item. Then filter **Monitoring → Latest data** for `UniFi topology`.

## Included items

| Key | Content |
| --- | --- |
| `unifi.topology.json` | HTTP-agent master item, complete JSON body, `GET` every minute, HTTP 200 required |
| `unifi.topology.vertices` | Text/JSON dependent item using JSONPath `$.vertices` |
| `unifi.topology.edges` | Text/JSON dependent item using JSONPath `$.edges` |
| `unifi.topology.vertices.count` | Unsigned dependent item using JSONPath `$.vertices.length()` |
| `unifi.topology.edges.count` | Unsigned dependent item using JSONPath `$.edges.length()` |

The four dependent items cause no additional HTTP requests. Text values are retained for one day; counts are retained for 30 days with trends for 365 days. Global housekeeping rules can override these periods. Counts include every returned entry, not only infrastructure devices or physical cables. The template does not invent online or health values or alarm thresholds.

## Operation and limitations

- The site is fixed to `default`, matching the verified example.
- The responsible Zabbix server or proxy requires DNS and HTTPS access to the controller; no agent interface is required.
- The TLS certificate and host name are verified. When a private CA is used, the responsible Zabbix server or proxy must trust it. A successful test with `curl -k` does not prove valid certificate verification.
- Redirects are disabled. Diagnose HTTP 401/403 responses, TLS errors, or a changed JSON structure on the items. Missing fields are not converted artificially to zero. Zabbix retains the last values after a query failure, so also check their timestamps.
- Manually created items with identical keys conflict when the template is linked. Review and, if necessary, rename those manual items first; leave the existing community template unchanged.
- The internal UniFi endpoint may change after an update. This template collects the data required for a later network map; it does not create a Zabbix map itself.

## Verification

The YAML was parsed and checked for unique UUIDs and keys and for correct master-item references. The supplied original topology response was checked for both arrays. No actual import into a running Zabbix 7.4 server was performed at that historical stage.

Format reference: https://www.zabbix.com/documentation/7.4/en/manual/xml_export_import/templates
