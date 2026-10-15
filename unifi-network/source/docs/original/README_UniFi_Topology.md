# UniFi Network Topology – Zabbix 7.4

Eigenständiges Template in der eigenen Gruppe „Templates/UniFi Topology“. Das Community-Template wird nicht verknüpft oder geändert. Es werden ausschließlich GET-Abfragen an UniFi ausgeführt.

## Verwendung

1. Unter Data collection → Templates → Import die Datei unifi_network_topology_zabbix74.yaml wählen. Beim ersten Import „Create new“ für Template und Template-Gruppe aktivieren. „Delete missing“ deaktiviert lassen. Die Importvorschau soll nur das neue Template mit fünf Items und seine Gruppe zeigen.
2. Unter Data collection → Hosts den ursprünglichen Controller-Host (z. B. unifi.tsei.mdn) öffnen. Zusätzlich zum bestehenden UniFi Network API Template das Template „UniFi Network Topology“ verknüpfen. Nicht an jeden entdeckten Geräte-Host hängen.
3. Am Controller-Host die vorhandenen Makros prüfen:
   - {$UNIFI_NETWORK_API_ADDRESS}: nur Hostname oder IP, optional :Port; ohne https:// und ohne Pfad, z. B. unifi.tsei.mdn.
   - {$UNIFI_NETWORK_API_KEY}: vorhandener API-Key, vorzugsweise als Secret text.
   Das neue Template definiert keine Makro-Standardwerte. Falls die Werte bisher nur aus dem Community-Template geerbt werden, ihre wirksamen Werte unter „Inherited and host macros“ prüfen.
4. Beim Item „UniFi topology: Raw JSON“ Execute now wählen. Anschließend unter Monitoring → Latest data nach „UniFi topology“ filtern.

## Enthaltene Items

| Key | Inhalt |
| --- | --- |
| unifi.topology.json | HTTP-Agent-Master, vollständiger JSON-Body, GET alle 1 Minute, HTTP 200 erforderlich |
| unifi.topology.vertices | Text/JSON, JSONPath $.vertices |
| unifi.topology.edges | Text/JSON, JSONPath $.edges |
| unifi.topology.vertices.count | Unsigned, JSONPath $.vertices.length() |
| unifi.topology.edges.count | Unsigned, JSONPath $.edges.length() |

Die vier abhängigen Items verursachen keine zusätzlichen HTTP-Aufrufe. Textwerte werden 1 Tag aufbewahrt, Counts 30 Tage mit Trends für 365 Tage; globale Housekeeping-Regeln können dies überschreiben. Counts zählen alle gelieferten Einträge, nicht ausschließlich Infrastrukturgeräte oder physische Kabel. Es gibt keine erfundenen Online-/Health-Werte oder Alarmgrenzen.

## Betrieb und Grenzen

- Site ist fest „default“, wie im geprüften Beispiel.
- Der zuständige Zabbix-Server beziehungsweise Proxy benötigt DNS-/HTTPS-Zugriff auf den Controller; ein Agent-Interface ist nicht erforderlich.
- TLS-Zertifikat und Hostname werden geprüft. Bei einer privaten CA muss diese auf dem zuständigen Zabbix-Server/Proxy vertrauenswürdig sein. Der frühere Test mit curl -k belegt keine gültige Zertifikatsprüfung.
- Redirects sind deaktiviert. Fehler wie HTTP 401/403, TLS-Fehler oder abweichende JSON-Struktur unter Items prüfen. Fehlende Felder werden nicht künstlich zu 0; bei einem Abfragefehler bleiben die letzten Werte stehen, daher auch deren Zeitstempel beachten.
- Falls die vorgeschlagenen Items aus dem früheren Gespräch bereits manuell angelegt wurden, kollidieren identische Keys beim Verknüpfen. Vorher diese manuellen Items prüfen und gegebenenfalls umbenennen; das bestehende Community-Template dabei unverändert lassen.
- Der interne UniFi-Endpunkt kann sich bei Updates ändern. Dieses Template sammelt die Grundlage für eine spätere Netzkarte; es erzeugt noch keine Zabbix Map.

## Prüfung

YAML wurde eingelesen und auf eindeutige UUIDs/Keys sowie die Master-Verweise geprüft. Die beigefügte originale Topologie-Antwort wurde auf die beiden Arrays geprüft. Ein tatsächlicher Import in einen laufenden Zabbix-7.4-Server wurde hier nicht durchgeführt.

Formatreferenz: https://www.zabbix.com/documentation/7.4/en/manual/xml_export_import/templates
