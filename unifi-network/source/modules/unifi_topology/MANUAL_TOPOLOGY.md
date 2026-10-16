# Manual Devices and Links — Version 0.2.0 and Later

Manual additions are entered in the widget edit dialog and stored with the dashboard. They affect only that widget. Both fields are empty by default, leaving the automatic UniFi topology unchanged.

## Additional devices

Enter one device per line:

```text
id | display name | optional Zabbix host ID
```

Example:

```text
internet | Internet
firewall | Firewall
site_b | Site B
```

The ID must start with a lowercase letter and may contain lowercase letters, digits, underscores, and hyphens, with a maximum length of 40 characters. Display names may be chosen freely but cannot contain `|`.

A third column can link the node to an existing Zabbix host. This example uses a **fictitious** host ID:

```text
firewall | Firewall | 12345
```

Without a host ID, the widget does not invent a healthy state; it displays **Manual · Status unknown**. With a readable host ID, the same problem colors as for UniFi devices apply. One host ID cannot map to multiple nodes; such a mapping is treated as ambiguous.

Manual devices are not managed by UniFi. They therefore never receive a traffic mini-chart icon, even when mapped to a Zabbix host.

## Additional links

Enter one link per line:

```text
from | to | optional label
```

An endpoint is either a manual ID defined above or the actual MAC address of a network device from the UniFi JSON. The MAC address appears in device details.

Generic example with **fictitious** MAC addresses:

```text
internet | firewall | WAN
firewall | aa:bb:cc:dd:ee:01 | Internal LAN
firewall | aa:bb:cc:dd:ee:02 | Camera network / Port 3
```

Replace example MAC addresses with actual addresses before saving. The `from → to` order influences the preferred top-to-bottom layout; it does not claim traffic is permitted through a firewall. Labels can contain a network name, VLAN, or port information. Manual links are dashed and marked **Manual**.

IDs avoid ambiguity when devices are renamed or share a display name. The internal `manual:...` prefix is not a MAC address and must not be entered.

## Behavior when topology changes

- Automatic UniFi links remain unchanged.
- If UniFi later supplies the same endpoint pair, the automatic link takes precedence and the duplicate manual link is omitted with a warning.
- If a referenced UniFi MAC is absent from the current JSON, configuration remains saved. The link is omitted with a warning until the node returns.
- Misspelled IDs, duplicate IDs or links, self-links, and invalid host IDs are rejected when saving.
- Only one manual logical link is allowed per device pair. Describe parallel physical cables in the label.
- A link to a UniFi client is not drawn as a network link; clients remain in the device detail list.
- The UniFi unknown-switch warning remains visible. Manual additions do not prove the automatic topology is complete.
- Limits are 50 additional nodes and 100 additional links, plus Zabbix form field-length limits.
- Empty lines and lines beginning with `#` are ignored.

The widget only renders the augmented overview. It does not modify firewall rules, UniFi configuration, Zabbix hosts, or classic Zabbix maps.

## Updating an existing installation

Use the current rolling release and installer documented in `README.md`. Do not overlay old 0.2.0 archives onto a current installation. Keep an operator-controlled backup before a production update:

```bash
sudo tar -czf /var/backups/unifi_topology-$(date +%Y%m%d-%H%M%S).tar.gz \
  -C /usr/share/zabbix/ui/modules unifi_topology
```

After installation, run **Administration → General → Modules → Scan directory** and fully reload the dashboard so the new JavaScript is loaded. Existing master-item selection, mappings, manual topology, and refresh settings remain stored with the widget as long as the module ID stays `unifi_topology`.
