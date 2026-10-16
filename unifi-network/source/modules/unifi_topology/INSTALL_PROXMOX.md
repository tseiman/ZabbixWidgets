# Installation im Proxmox-Zabbix-Container

Das Community-Skript installiert Zabbix als Debian-Pakete mit Apache im LXC-Container. Das Widget wird im Zabbix-Container installiert, nicht auf dem Proxmox-Host.

## 1. Pfad prüfen

In Proxmox den Zabbix-Container auswählen und dessen **Console** öffnen. Dort ausführen:

```bash
ls -ld /usr/share/zabbix/ui/modules /usr/share/zabbix/modules 2>/dev/null
```

Der vorhandene Pfad ist der Kandidat für die Installation. Bei Zabbix 7.4 ist normalerweise `/usr/share/zabbix/ui/modules` zu erwarten. Falls beide oder keiner erscheinen, zuerst die tatsächliche Apache-Konfiguration prüfen; nicht blind in beide kopieren.

## 2. Modul übertragen

Das Paket `unifi_topology_zabbix74_v0.2.0.tar.gz` herunterladen und über den üblichen Dateiübertragungsweg nach `/tmp` im **Zabbix-Container** übertragen. Das Paket enthält nur den Modulordner, keinen Demo-Datensatz und keine Zugangsdaten. Die TAR-Version benötigt im Container kein zusätzliches unzip-Paket.

Als root in der Container-Konsole zunächst das Paket anzeigen:

```bash
tar -tzf /tmp/unifi_topology_zabbix74_v0.2.0.tar.gz
```

Wenn Schritt 1 den Pfad `/usr/share/zabbix/ui/modules` bestätigt hat, dort erstmalig installieren:

```bash
test -d /usr/share/zabbix/ui/modules && test ! -e /usr/share/zabbix/ui/modules/unifi_topology && tar --no-same-owner -xzf /tmp/unifi_topology_zabbix74_v0.2.0.tar.gz -C /usr/share/zabbix/ui/modules
```

Bei bestätigtem älteren Pfad stattdessen `/usr/share/zabbix/modules` verwenden. Der Befehl überschreibt keinen schon existierenden Modulordner. Erscheint kein neuer Ordner, zuerst die Pfade und einen eventuell vorhandenen Modulordner prüfen.

## 3. Aktivieren

1. Als Zabbix-Super-Admin **Administration → General → Modules → Scan directory** öffnen.
2. **UniFi Network Topology** aktivieren.
3. Dashboard neu laden und bearbeiten.
4. Widget **UniFi Network Topology** hinzufügen.
5. Auf dem Controller-Host das Master-Item **UniFi topology: Raw JSON** auswählen.
6. Aktualisierungsintervall **1 Minute** und Dashboard speichern.

Ein Zabbix-Server-Neustart ist dafür nicht nötig.

## 4. Echte Warnungen prüfen

Oben steht, wie viele Netzwerkgeräte einem Zabbix-Host zugeordnet sind. switch3 anklicken und den angezeigten Hostnamen kontrollieren. Solange dessen Speicher-Warning noch offen und für den angemeldeten Benutzer sichtbar ist, muss das Widget es mit der konfigurierten Warning-Farbe anzeigen.

Bei grauem switch3 die Namenszuordnung anpassen; die vollständige Erklärung steht in README.md. Fehlende Zuordnung wird bewusst nicht als „keine Probleme“ dargestellt.

Das Widget ist lokal getestet, aber noch nicht in deinem laufenden Zabbix installiert oder integriert getestet.

Referenz des Community-Skripts: https://github.com/community-scripts/ProxmoxVE/blob/main/install/zabbix-install.sh
