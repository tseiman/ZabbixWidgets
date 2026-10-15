# UniFi Topologie – Dashboard-Widget für Zabbix 7.4

Version 0.3.0 · Mit RX/TX-Verlauf, konfigurierbaren manuellen Geräten und Verbindungen

Das Widget liest das Text-Item „UniFi topology: Raw JSON“ und zeichnet die Netzwerkgeräte. Die Zabbix-History der vom mitgelieferten Template entdeckten Traffic-Items liefert kleine RX/TX-Diagramme und einen vergrößerbaren Dialog. Ein Klick auf den übrigen Knoten öffnet dessen Endgeräte, Netzwerkverbindungen und Zabbix-Probleme. Das Frontend schreibt keine Maps, Hosts, Items oder Trigger und ruft UniFi nicht direkt ab.

## Neu in 0.3.0

- Jeder Switch und Access Point mit gesammelten Daten erhält rechts oben einen RX/TX-Mini-Graphen.
- Klick sowie Enter/Leertaste öffnen einen automatisch skalierten Dialog im Stil der Zabbix-Overlays.
- Mini- und Großdiagramm folgen standardmäßig dem Zeitraum des Dashboards.
- Das aktualisierte Template erfasst direkte Port-Raten und eine aus Byte-Zählern berechnete Ausweichserie.
- Es werden keine externen JavaScript-Bibliotheken oder Browser-Anfragen an UniFi verwendet.

Die in 0.2.0 eingeführten optionalen manuellen Geräte und Verbindungen bleiben unverändert verfügbar. Syntax und Beispiele stehen in `MANUAL_TOPOLOGY.md`.

## Installation

Das ZIP enthält genau den Ordner `unifi_topology`. Dieser gehört in das vorhandene Verzeichnis `modules` des **Zabbix-Webfrontends**. Bei mehreren Frontend-Instanzen muss er auf jeder Instanz verfügbar sein.

1. ZIP auf den Rechner mit dem Zabbix-Webfrontend übertragen und entpacken.
2. Den Ordner `unifi_topology` in dessen `modules`-Verzeichnis kopieren. Direkt darunter muss `unifi_topology/manifest.json` liegen, keine zusätzliche verschachtelte Ordnerstufe.
3. Als Zabbix-Super-Admin **Administration → General → Modules → Scan directory** öffnen.
4. **UniFi Topologie** aktivieren.
5. Dashboard neu laden, bearbeiten und ein Widget vom Typ **UniFi Topologie** hinzufügen.
6. Als **Topologie-Master-Item** auf dem Controller-Host das Item **UniFi topology: Raw JSON** auswählen.
7. Aktualisierungsintervall auf **1 Minute** einstellen und Dashboard speichern.

Ein typischer Frontend-Pfad ist `/usr/share/zabbix` oder `/usr/share/zabbix/ui`; je nach Paket und Installation kann er anders heißen. Es muss der Ordner des tatsächlich ausgelieferten Webfrontends sein. Bei Docker den Modulordner dauerhaft in das `modules`-Verzeichnis des Frontend-Containers einbinden; eine Kopie nur in den laufenden Container überlebt dessen Neuerstellung nicht.

Die Dateien müssen für den Webserver lesbar sein (typisch Dateien 0644, Ordner 0755), aber nicht durch ihn beschreibbar. Kein Neustart des Zabbix-Servers und kein zusätzlicher API-Token sind erforderlich. Falls PHP OPcache Änderungen nicht übernimmt, dessen Cache über das normale Administrationsverfahren aktualisieren.

Version 0.3.0 benötigt zusätzlich den Import von `source/templates/unifi_network_topology_zabbix74.yaml`. Das Template muss auf demselben Controller-Host liegen wie das ausgewählte Topologie-Master-Item. Die LLD erzeugt RX/TX-Items für `usw` und `uap`; erst nach den ersten Werten erscheinen Diagramme.

## Traffic-Diagramme

Blau zeigt RX, grün TX. Bevorzugt werden `rx_bytes-r` und `tx_bytes-r` jedes eindeutigen physischen Ports summiert und von Byte/s in bit/s umgerechnet. Fehlen diese Werte, verwendet Zabbix die Änderung pro Sekunde der summierten `rx_bytes`- beziehungsweise `tx_bytes`-Zähler. Logische LAG-, Bond- und Aggregate-Interfaces sowie doppelte Portindizes werden nicht zusätzlich gezählt. Bei Access Points ohne nutzbare `port_table` dient der einzelne physische `uplink` als Ausweichquelle.

Das Feld **Traffic-Zeitraum** verweist standardmäßig auf den Dashboard-Zeitraum. Das Backend nutzt begrenzte Zabbix-History-/Trend-Aggregate mit maximal 320 Punkten je zugrunde liegendem Item. Manuell ergänzte Geräte und Geräte ohne passende History zeigen keinen erfundenen Graphen.

## Verbindung zwischen Topologiegerät und Zabbix-Host

Damit switch3 seine echten Warnungen bekommt, muss das Gerät eindeutig zum entdeckten Zabbix-Host passen.

Das voreingestellte Namensmuster lautet:

```text
UniFi Network - Site: "Default" - Device: "{name}"
```

`{name}` wird exakt durch den Namen aus dem Topologie-JSON ersetzt. Das Widget prüft sowohl den technischen als auch den sichtbaren Zabbix-Hostnamen, ohne unscharfe Teiltreffer. Andere Site-Namen oder eine andere Benennung erfordern ein angepasstes Muster.

Oben steht beispielsweise **8/8 Hosts zugeordnet**. Beim Klick auf ein Gerät werden Host-ID und vollständiger Hostname angezeigt. Diese Zuordnung beim ersten Einsatz prüfen.

Falls ein Host nicht gefunden wird oder mehrere Treffer entstehen, bleibt der Knoten grau: **Status unbekannt**. Auch ein Host, der mehreren Topologiegeräten zugeordnet würde, wird nicht automatisch verwendet.

Alternativ im Feld **Optionale Zuordnung** eine Zeile pro Gerät eintragen:

```text
aa:bb:cc:dd:ee:ff=12345
```

Dies ist ein fiktives Syntaxbeispiel; durch die echte MAC-Adresse und die echte Zabbix-Host-ID ersetzen. Die Host-ID lässt sich beispielsweise aus dem `hostid`-Parameter beim Öffnen des Hosts ablesen. Eine explizite Zuordnung hat Vorrang vor dem Namensmuster. Für ausschließlich explizite Zuordnung das Namensmuster leeren. Kommentare beginnen mit `#`.

Für manuelle Dateiänderungen `mcedit` verwenden; die normale Widget-Konfiguration erfolgt direkt im Zabbix-Dialog.

## Problemfarben

- Die höchste Schwere der offenen Probleme bestimmt den Rahmen des Netzwerkgeräts.
- Badge und Text zeigen Schweregrad und Anzahl der Probleme.
- Namen und Farben stammen aus den Zabbix-Einstellungen, einschließlich benutzerdefinierter Farben.
- Bestätigen allein entfernt die Hervorhebung nicht. Erst wenn das Problem behoben ist, verschwindet es beim nächsten erfolgreichen Widget-Refresh.
- Unterdrückte Probleme sind standardmäßig ausgeblendet. Die Widget-Option kann sie einschließen.
- Klick auf die Problemmeldung öffnet das zugehörige Zabbix-Ereignis.
- Ein grüner Rahmen bedeutet **keine offenen, für den angemeldeten Benutzer sichtbaren Probleme im gewählten Filter**, nicht eine eigenständig gemessene Erreichbarkeit.
- Host-Monitoring deaktiviert, fehlende/mehrdeutige Zuordnung und veralteter Problemstatus werden grau angezeigt. Vorhandene offene Probleme eines deaktivierten Hosts bleiben sichtbar.
- Bei Verbindungs- oder API-Fehlern wird der bisherige Status durch eine Fehlermeldung ersetzt, nicht als weiterhin aktuell dargestellt.

Der angemeldete Benutzer benötigt Leserechte auf das Topologie-Item und die jeweiligen Geräte-Hosts. Die normalen Zabbix-Berechtigungen gelten auch für Probleme. Es werden keine Zugangsdaten im Browser hinterlegt und keine direkten Datenbankabfragen ausgeführt.

## Bedienung und Topologie

- Übersicht: Knoten mit `type=DEVICE`; Clients bleiben zunächst ausgeblendet.
- Klick oder Enter/Leertaste auf ein Gerät: Details und ihm unmittelbar im JSON zugeordnete Endgeräte ein-/ausblenden.
- Endgeräte hinter einem weiteren Switch erscheinen in dessen Detailbereich.
- Kabelgebunden: Port und Linkrate, soweit geliefert.
- WLAN: SSID, Kanal und Experience, soweit geliefert.
- Verbindung zwischen Netzwerkgeräten: Portnummern in der Übersicht, Linkrate im Tooltip und im Detailbereich.
- Plus/Minus vergrößern oder verkleinern. Große Ansichten können gescrollt werden.
- Geräte ohne gelieferte Verbindung bleiben als separate Knoten sichtbar. Das Widget erfindet keine Kabel oder Gateways.
- Graue Verbindungslinien sind Topologieinformationen, **keine Port-Alarme**. Ein Speicher-Warning färbt das Gerät, nicht seine Leitungen.
- Die Endgeräteliste zeigt die Topologiedaten. Automatisches Problem-Highlighting ist in dieser Version auf die Netzwerkgeräte der Übersicht konzentriert.

Die Auswahl basiert bewusst auf `type=DEVICE`, nicht auf `unifiDevice`: Das Beispiel enthält auch UniFi-Kameras mit `type=CLIENT`.

## Aktualität und Grenzen

Das Widget aktualisiert sich, solange die Dashboard-Seite aktiv ist. Der aktuelle Problemstatus wird bei jedem Refresh neu gelesen. Die Topologie stammt aus dem letzten gespeicherten Master-Item-Wert. Eine Änderung des Dashboard-Zeitraums stößt eine neue Traffic-Abfrage an.

Standardmäßig werden Topologiedaten nach 300 Sekunden als veraltet markiert. Bei einem deaktivierten oder nicht unterstützten Quell-Item erscheint ebenfalls ein Hinweis. Datum der Topologie und Zeitpunkt der Problemabfrage stehen unten. Wird der Problemstatus länger als die konfigurierte Altersgrenze nicht aktualisiert, werden die Knoten grau. Ein erneuter erfolgreicher Refresh stellt den aktuellen Zustand wieder her.

UniFi `has_unknown_switch=true` wird sichtbar gemeldet. Insbesondere Clients hinter einem unbekannten Switch oder einer Bridge können alle demselben Uplink-Port zugeordnet sein; daraus wird kein direkter physischer Anschluss behauptet.

Diese erste Version ist für ein normales Dashboard mit ausgewähltem Controller-Item ausgelegt. Automatische Host-Override-/Template-Dashboard-Vererbung, Port-Trigger-Zuordnung, persistentes freies Verschieben von Knoten und das Aufklappen der Clients direkt als zusätzliche Diagrammknoten sind nicht enthalten. Die Clients erscheinen im Detailbereich.

Grenzen pro Antwort: 1000 Knoten, 3000 Kanten, 2000 offene Probleme. Überschreitungen werden gemeldet, nicht stillschweigend als vollständige Anzeige ausgegeben.

## Teststand

- Template-, LLD- und Port-Summenlogik werden mit automatisierten Regressionstests geprüft, einschließlich doppelter Portindizes, logischer LAG-Interfaces und AP-Uplink-Fallback.
- JavaScript-Syntax und die PHP-Syntaxstruktur aller Moduldateien wurden automatisiert geprüft.
- Mini-Diagramme, Skalierung, Overlay, Beschriftungen und Hellmodus wurden mit Firefox in einem datenschutzneutralen Browser-Harness visuell geprüft.
- Zeitbereich, History-Aggregation und Widget-Feldklassen wurden mit dem offiziellen Zabbix-Quellzweig `release/7.4` abgeglichen.
- **Es gibt noch keinen Live-Integrationstest gegen die produktive Zabbix-/UniFi-Installation.** Die endgültige Prüfung erfolgt nach Template-Import und Modulaktivierung.

Die separate HTML-Vorschau enthält einen älteren Topologie-Schnappschuss und ein simuliertes switch3-Warning. Sie läuft ohne Verbindung zu Zabbix. Ihre beispielhaften Host-IDs sind keine Konfiguration für dein System. Das installierbare ZIP enthält weder diesen Schnappschuss noch API-Schlüssel.

## Erste Live-Prüfung

1. Erscheinen die erwarteten Netzwerkgeräte?
2. Zeigt die Kopfzeile die erwartete Anzahl zugeordneter Hosts?
3. Ist bei switch3 der richtige Zabbix-Host im Detailbereich genannt?
4. Falls das Speicher-Warning noch offen ist: stimmt die Farbe mit der Zabbix-Problemliste überein?
5. Öffnet der Problem-Link das richtige Ereignis?
6. Werden bei einem AP die erwarteten WLAN-Clients angezeigt?
7. Nach normaler Behebung eines Problems: verschwindet es auch im Widget beim nächsten Refresh?
8. Haben alle erwarteten Switches und Access Points einen Mini-Graphen?
9. Folgt der große RX/TX-Dialog nach einer Änderung dem Dashboard-Zeitraum?

Keine Alarme absichtlich auslösen; vorhandene Probleme und ihre normale Behebung verwenden.

## Entfernen

Das Widget aus den Dashboards entfernen und das Modul unter **Administration → General → Modules** deaktivieren. Das beeinflusst die Templates und die Datensammlung nicht. Den Modulordner erst danach bei Bedarf entfernen.

## Technische Referenzen

- https://www.zabbix.com/documentation/7.4/en/devel/modules/tutorials/widget
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/history/get
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/problem/get
- https://github.com/zabbix/zabbix/tree/release/7.4/ui/widgets

Die Quelldateien sind vollständig im Modul enthalten; es gibt keine extern geladenen JavaScript-Bibliotheken, CDNs oder einen separaten Dienst.
