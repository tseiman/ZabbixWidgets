# Manuelle Geräte und Verbindungen – Version 0.2.0

Die Ergänzungen werden im Bearbeitungsdialog des Widgets eingetragen und mit dem Dashboard gespeichert. Sie gelten nur für dieses Widget. Beide Felder sind standardmäßig leer; dann bleibt die automatische Topologie unverändert.

## Zusätzliche Geräte

Eine Zeile je Gerät:

```text
id | Anzeigename | optionale Zabbix-Host-ID
```

Beispiel:

```text
internet | Internet
firewall | Firewall
standort_b | Standort B
```

Die ID beginnt mit einem kleinen Buchstaben und darf kleine Buchstaben, Ziffern, Unterstriche und Bindestriche enthalten (maximal 40 Zeichen). Namen sind frei wählbar. Das Trennzeichen `|` darf nicht im Namen stehen.

Eine dritte Spalte verbindet den Knoten optional mit einem vorhandenen Zabbix-Host. Beispiel mit **fiktiver** Host-ID:

```text
firewall | Firewall | 12345
```

Ohne Host-ID wird kein grüner Gesundheitsstatus erfunden. Der Knoten zeigt „Manuell · Status unbekannt“. Mit einer lesbaren Host-ID gelten dieselben Problemfarben wie bei UniFi-Geräten. Eine Host-ID darf nicht mehreren Knoten zugeordnet sein; dann wird die Zuordnung als mehrdeutig behandelt.

## Zusätzliche Verbindungen

Eine Zeile je Verbindung:

```text
von | nach | optionale Beschriftung
```

Als Endpunkt ist entweder eine oben definierte manuelle ID oder die echte MAC-Adresse eines Netzwerkgeräts aus dem UniFi-JSON erlaubt. Die MAC-Adresse steht im Detailbereich beim Anklicken des Geräts.

Allgemeines Beispiel mit **fiktiven** MAC-Adressen:

```text
internet | firewall | WAN
firewall | aa:bb:cc:dd:ee:01 | Internes LAN
firewall | aa:bb:cc:dd:ee:02 | Kameranetz / Port 3
```

Die MAC-Adressen vor dem Speichern durch die tatsächlichen Adressen ersetzen. Die Reihenfolge „von → nach“ bestimmt die bevorzugte Anordnung von oben nach unten; sie behauptet keine Verkehrsfreigabe durch eine Firewall. Die Beschriftung ist frei, etwa Netzname, VLAN oder Portangaben. Eine manuelle Verbindung ist immer gestrichelt und mit „Manuell“ gekennzeichnet.

IDs statt Gerätenamen vermeiden Verwechslungen nach Umbenennung oder bei gleichnamigen Geräten. Die interne ID `manual:...` ist kein MAC-Wert und muss nicht eingetragen werden.

## Verhalten bei Änderungen

- Automatische UniFi-Verbindungen bleiben unverändert.
- Liefert UniFi später selbst eine Verbindung zwischen denselben Endpunkten, hat diese Vorrang; der manuelle Doppeleintrag wird mit Hinweis ausgelassen.
- Fehlt eine referenzierte UniFi-MAC im aktuellen JSON, bleibt die Konfiguration gespeichert. Der Link wird mit Hinweis ausgelassen, bis der Knoten wieder vorhanden ist.
- Tippfehler bei manuellen IDs, doppelte IDs/Verbindungen, Selbstverbindungen und ungültige Host-IDs werden beim Speichern beanstandet.
- Es gibt höchstens eine manuelle logische Verbindung je Gerätepaar. Parallele physische Kabel müssen in der Beschriftung beschrieben werden.
- Ein Link zu einem UniFi-Client wird nicht als Netzwerkverbindung gezeichnet; dafür bleibt die vorhandene Endgeräteliste zuständig.
- Der Hinweis auf unbekannte Switches aus UniFi bleibt sichtbar. Manuelle Ergänzungen beweisen nicht, dass die automatische Topologie vollständig ist.
- Bis zu 50 zusätzliche Knoten und 100 zusätzliche Links; außerdem gelten die Feldlängenlimits des Zabbix-Formulars.
- Leere Zeilen und mit `#` beginnende Kommentarzeilen werden ignoriert.

Das Widget zeichnet die ergänzte Übersicht; es ändert keine Firewall-Regeln, UniFi-Konfiguration, Zabbix-Hosts oder klassischen Zabbix-Maps.

## Update von 0.1.0 im vorhandenen Container

Paket `unifi_topology_zabbix74_v0.2.0.tar.gz` nach `/tmp` des Zabbix-Containers übertragen. Der bei dir bereits bestätigte Modulpfad ist `/usr/share/zabbix/ui/modules`.

Als root zunächst sichern:

```bash
tar -czf /tmp/unifi_topology-backup-$(date +%Y%m%d-%H%M%S).tar.gz -C /usr/share/zabbix/ui/modules unifi_topology
```

Nur nach erfolgreicher Sicherung die neue Version darüber entpacken:

```bash
tar --no-same-owner -xzf /tmp/unifi_topology_zabbix74_v0.2.0.tar.gz -C /usr/share/zabbix/ui/modules
```

Dann in Zabbix **Administration → General → Modules → Scan directory** ausführen und die Dashboard-Seite vollständig neu laden, damit neues JavaScript geladen wird. Das bestehende Widget bearbeiten: Die beiden zusätzlichen Felder sollten erscheinen. Vorhandenes Master-Item, Zuordnungen und Intervall bleiben gespeichert.

Das Modul nicht löschen oder neu benennen; seine ID bleibt `unifi_topology`. Falls die Oberfläche alte PHP-Dateien verwendet, den PHP-OPcache im Rahmen der üblichen Administration aktualisieren.

## Teststand

Die bestehende Datenauswertung und neue Konfiguration wurden lokal mit simulierten Zabbix-Antworten getestet. Geprüft wurden zusätzliche Knoten/Links, Problemfarben optional verknüpfter Hosts, fehlende Berechtigungen, doppelte/fehlende Endpunkte, Escaping und die Darstellung. Ein Live-Test der Version 0.2.0 in deinem Zabbix steht noch aus.
