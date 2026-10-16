# UniFi Network Topology — Zabbix 7.4

> Historical document translated to English. It describes the original standalone topology template. Use `unifi-network/README.md` for the current release.

This was a standalone template in its own `Templates/UniFi Topology` group. It did not link or modify the community template. It performed only `GET` requests to UniFi.

## Usage

1. Open **Data collection → Templates → Import** and select `unifi_network_topology_zabbix74.yaml`. On the first import, enable creation of the template and template group. Keep **Delete missing** disabled. The original import preview was expected to show only the new template, five items, and its group.
2. Open the existing controller host under **Data collection → Hosts**. Link **UniFi Network Topology** in addition to the existing UniFi Network API template. Do not link it to every discovered device host.
3. Check the effective host macros:
   - `{$UNIFI_NETWORK_API_ADDRESS}` — host name or IP address, optionally followed by a port, without `https://` or a path.
   - `{$UNIFI_NETWORK_API_KEY}` — existing API key, preferably stored as a secret macro.
   The topology template defined no macro defaults. If values were inherited only from the community template, their effective values had to be checked under **Inherited and host macros**.
4. Run **Execute now** for `UniFi topology: Raw JSON`, then filter **Monitoring → Latest data** for `UniFi topology`.

## Original items

- `unifi.topology.json` — HTTP-agent master item, complete JSON response, `GET` every minute, HTTP 200 required.
- `unifi.topology.vertices` — dependent text/JSON item using JSONPath `$.vertices`.
- `unifi.topology.edges` — dependent text/JSON item using JSONPath `$.edges`.
- `unifi.topology.vertices.count` — unsigned dependent item using `$.vertices.length()`.
- `unifi.topology.edges.count` — unsigned dependent item using `$.edges.length()`.

The four dependent items generated no additional HTTP requests. Text values were retained for one day; counts for 30 days with trends for 365 days. Global housekeeping could override those periods. Counts represented every returned entry, not only infrastructure devices or physical cables. The template did not invent online, health, or threshold values.

## Operation and limits

- The site was fixed to `default`, matching the verified sample.
- The responsible Zabbix server or proxy required DNS and HTTPS access to the controller; no agent interface was required.
- TLS certificate and host name verification were enabled. A private CA had to be trusted on the responsible server or proxy. A successful `curl -k` test did not prove valid certificate verification.
- Redirects were disabled. HTTP 401/403, TLS failures, or changed JSON structure had to be diagnosed on the items. Missing fields were not converted artificially to zero. Zabbix retained old values after a collection failure, so timestamps also had to be checked.
- Manually created items with the same keys could conflict when linking the template. Such items had to be reviewed or renamed without changing the community template.
- The internal UniFi endpoint could change after controller updates. The template collected data for a topology display; it did not create a classic Zabbix map.

## Historical verification

The YAML was parsed and checked for unique UUIDs and keys and correct master-item references. The supplied original topology response was checked for both arrays. No import into a running Zabbix 7.4 server had been performed at that historical stage.

Format reference: https://www.zabbix.com/documentation/7.4/en/manual/xml_export_import/templates
