# PBS Dashboard 1.0.5

Vollständiges Paket mit fünf Zabbix-Widgets, Template, Sammlern, Installationsskript und Tests. Baut auf Version 1.0.4 auf.

## Neu in 1.0.5

- **PBS Task List:** abgeschlossene Tasks mit Start, Ende, Benutzer, Beschreibung und Status; neueste Startzeit zuerst. Fehler rot, Warnungen gelb. Standardmäßig 50 Zeilen, im Widget 1 bis 1000 einstellbar; die Gesamtzahl wird angezeigt. Der Dashboard-Zeitraum wird wie bisher anhand der Abschlusszeit ausgewertet.
- **Logs direkt in Zabbix:** Task in Task List, Longest Tasks oder Running Tasks anklicken. In Summary zunächst die Kategorie/Status-Zahl, danach den Task wählen. Bei genau einem Task öffnet sich das Log sofort. „Weitere Log-Zeilen“ und „Neu laden“ sind verfügbar.
- Template und Sammler sind unverändert. Der Benutzername wird bei bestehenden Snapshots aus der PBS-UPID gelesen.
- Summary-Filter, Zeiträume, Deduplizierung und sonstige Funktionen bleiben erhalten. Der Summary-Filter gilt ausschließlich im jeweiligen Summary-Widget; Task List zeigt alle verfügbaren abgeschlossenen Tasks.

## Upgrade von 1.0.4

Als root auf dem Zabbix-Webfrontend-Server:

```sh
tar -xzf pbs-dashboard-1.0.5.tar.gz
sh pbs-dashboard-1.0.5/update.sh /usr/share/zabbix/ui
```

Auch `/usr/share/zabbix` wird unterstützt, wenn das Frontend darunter in `ui` liegt. Das Skript sichert vorhandene PBS-Module nach `/var/backups/pbs-dashboard/<Zeitstempel-Prozessnummer>` und zeigt den Pfad an. Andere Module bleiben unberührt. Bei Containern müssen die Module im dauerhaften Frontend-Volume liegen.

1. **Administration → General → Modules → Scan directory** ausführen, **PBS Task List** aktivieren. Vorhandene Module aktiviert lassen.
2. Dashboard mit **Strg+F5** / **Cmd+Shift+R** vollständig neu laden.
3. Dashboard bearbeiten, **PBS Task List** hinzufügen, PBS-Host und Zeilenanzahl auswählen.
4. Für Logs einmalig die folgende Verbindung einrichten. Ohne sie funktionieren die Widgets weiter; der Log-Aufruf zeigt einen Einrichtungshinweis.

**Kein erneuter Template-Import nötig. Das Template nicht löschen oder neu installieren.** Die Modulversionen sind konsistent 1.0.5, bestehende Widget-IDs und Einstellungen bleiben erhalten.

## Direkte Task-Logs einmalig einrichten

Der Abruf erfolgt vom **Zabbix-Webfrontend/PHP** zum PBS. Benötigt werden PHP-cURL und HTTPS-Zugriff vom Frontend auf PBS. Bei getrennten Servern/Containern reicht eine Verbindung nur vom Zabbix-Server oder Proxy nicht aus.

Die geheimen Zabbix-Makros werden nicht ausgelesen. Eine Datei außerhalb des Webverzeichnisses ordnet erlaubten Zabbix-Host-IDs die PBS-Verbindung zu. Das vorhandene Monitoring-Token kann verwendet werden; für fremde Tasks benötigt es **Sys.Audit auf /system/tasks**, einschließlich der wirksamen Rechte bei aktivierter Token-Privilegientrennung.

Bei der ersten Einrichtung die Beispieldatei kopieren und mit **mcedit** bearbeiten. Die Gruppe `www-data` durch die tatsächliche PHP-FPM-/Webserver-Gruppe ersetzen, falls diese anders heißt. Falls `/etc/zabbix` fehlt, vorher anlegen; vorhandene Verzeichnisrechte unverändert lassen.

```sh
install -m 0640 -o root -g www-data pbs-dashboard-1.0.5/config/pbs-dashboard.example.json /etc/zabbix/pbs-dashboard.json
mcedit /etc/zabbix/pbs-dashboard.json
```

Eine bereits vorhandene Konfigurationsdatei nur bearbeiten, nicht erneut mit der Vorlage überschreiben. Beispielinhalt (alle Werte ersetzen):

```json
{
  "hosts": {
    "12345": {
      "url": "https://pbs.example.invalid:8007",
      "token_id": "monitor@pbs!zabbix",
      "token_secret": "DEIN_VORHANDENES_TOKEN_SECRET"
    }
  }
}
```

`12345` ist die **Zabbix-Host-ID des PBS-Hosts**, erkennbar beispielsweise als `hostid=12345` in der URL beim Bearbeiten des Hosts. Für weitere PBS-Hosts zusätzliche Einträge unter `hosts` ergänzen. Die URL ist die HTTPS-Basisadresse ohne API-Pfad. Zugangsdaten ausschließlich in diese geschützte Datei schreiben, niemals in Widget-Einstellungen oder JavaScript. Das Update-Skript überschreibt diese Datei nicht. In Containern die Datei dauerhaft in den Webfrontend-Container einbinden.

TLS-Zertifikate werden geprüft. Bei eigener CA optional `"ca_file": "/etc/zabbix/pbs-ca.pem"` im Host-Eintrag ergänzen und die vertrauenswürdige CA-Datei für PHP lesbar ablegen. Der DNS-Name muss zum Zertifikat passen. Eine Abschaltung der Zertifikatsprüfung ist nicht vorgesehen.

Jede Log-Anfrage prüft die Zabbix-Sitzung sowie Host- und Task-Item-Leserechte. **Wer den konfigurierten PBS-Host und dessen Task-Items in Zabbix lesen darf, kann dessen PBS-Task-Logs mit den Rechten des hinterlegten Tokens lesen.** Nur dafür gewünschte Host-Zuordnungen eintragen. Der Endpunkt kann keine PBS-Tasks stoppen, löschen oder starten.

Nach Einrichtung einen bekannten Task öffnen. HTTP 401/403: Token oder Berechtigungen prüfen. Verbindungs-/TLS-Hinweis: Erreichbarkeit und Zertifikat aus Sicht des PHP-Frontends prüfen. Bereits in PBS entfernte Logs können nicht aus der Zabbix-History rekonstruiert werden.

## Bestehende Widgets und Summary-Filter

- **PBS Task Summary:** Failed/Warning/OK nach Kategorie und Details.
- **PBS Longest Tasks:** längste abgeschlossene Tasks im gewählten Zeitraum.
- **PBS Running Tasks:** laufende Tasks mit Laufzeiten.
- **PBS Datastore:** aktuelle Kapazität, Belegung, Prognose und History-Kurve; dieses Widget enthält keine Tasks und daher keine Log-Funktion.

Unter **PBS Task Summary → Bearbeiten → Tasks ausschließen (Expression)** ist weiterhin vorbelegt:

```text
worker_type === 'reader' && status === 'connection error: not connected'
```

Pro Widget änderbar; **leeres Feld = keine Filterung**. Eigene gespeicherte Filter und leere Einstellungen bleiben erhalten. Nur die exakt passende Kombination entfällt aus Zählung und Details; `connection error: unexpected EOF` bleibt Other/Failed. Andere Widgets und Datenquellen sind davon unabhängig.

Felder: `worker_type`, `status`, `worker_id`, `user`, `node`. Operatoren: `===`, `!==`, `&&`, `||`, `!`, Klammern. Zeichenketten in einfachen/doppelten Anführungszeichen, exakte Groß-/Kleinschreibung, maximal 255 Bytes. Keine Ausführung von PHP/JavaScript. Fehlende Felder gelten als null; der bestehende Sammler liefert `user` nicht als separates Feld. Die Anzeige des Benutzers in Task List nutzt dafür die UPID.

## Neuinstallation

Module wie oben installieren und alle fünf aktivieren. `templates/pbs-template-extended.yaml` importieren und dem PBS-Host zuweisen; Host-, Port- und Token-Makros konfigurieren. Bei vorhandenen Templates den Importvergleich prüfen, **Delete missing ausgeschaltet lassen** und eigene Anpassungen berücksichtigen. Die beiden Items **PBS dashboard: Collect completed tasks** und **PBS dashboard: Running tasks** mit **Execute now** starten. Unter Latest data Werte prüfen; eine erfolgreiche leere Task-Liste ist gültig. Widgets hinzufügen und direkte Logs separat wie oben einrichten.

## Daten und Grenzen

Abgeschlossene Tasks werden alle fünf Minuten gesammelt, laufende jede Minute. Anfänglicher Rückblick: 48 Stunden, über `{$PBS.DASHBOARD.LOOKBACK.HOURS}` auf 1 bis 168 Stunden einstellbar. Danach wächst die History bis zur konfigurierten Aufbewahrung von 90 Tagen. Nicht gesammelte ältere PBS-Tasks fehlen weiterhin. Dashboard-Zeiträume sind auf 90 Tage begrenzt; Lücken werden angezeigt. Zabbix-Housekeeping und PBS-Aufbewahrung beeinflussen die Verfügbarkeit.

Die 16 überlappenden Text-Snapshots werden nach UPID dedupliziert. Grenzen: 60.000 konservativ geschätzte JSON-Bytes pro Teil, 8 Millionen Zeichen PBS-Archivantwort. Überläufe werden gemeldet.

Logs werden nicht dauerhaft in Zabbix gespeichert. Pro Abruf maximal 500 Zeilen und 2 MiB; Verbindungszeitlimit 5 Sekunden, Gesamtzeitlimit 15 Sekunden. Die Anzeige ist auf 20.000 Zeilen bzw. 8 Millionen Zeichen je geöffnetem Log begrenzt und meldet das Erreichen der Grenze. Laufende Logs mit „Neu laden“ aktualisieren. Größere Logs direkt in PBS prüfen.

## Prüfung und Dateien

Geprüft: PHP-/JavaScript-/Shell-Syntax, bestehende Regressionstests, Task-Reihenfolge, Benutzeranzeige, Filter, Log-Seiten, Eingabevalidierung, Berechtigungsentscheidungen und HTTP-Fehler mit simuliertem Transport. DOM-Tests prüfen die vier Task-Widgets mit simulierten Antworten. Installation/Upgrade werden in einem nachgebildeten Frontend geprüft. PHP-Tests laufen in einer WebAssembly-Laufzeit. Eine echte Browser-Vorschau war in der Build-Umgebung gesperrt. **Kein Live-Test in einer laufenden Zabbix-/PBS-Installation.**

```sh
php tests/test.php
php tests/test-expression.php
php tests/test-summary-filter.php
php tests/test-task-list.php
php tests/test-log.php
php tests/test-log-access.php
php tests/test-log-transport.php
node tests/test-collector.cjs
```

Enthalten: `modules/` (fünf vollständige Widgets), `templates/`, `collector/`, `config/pbs-dashboard.example.json` (ohne echte Zugangsdaten), `update.sh`, `tests/`, `SHA256SUMS`, `LICENSE` und diese README.

Referenzen: [PBS Task-/Log-API](https://github.com/proxmox/proxmox-backup/blob/master/src/api2/node/tasks.rs), [Zabbix-Modulaktionen](https://github.com/zabbix/zabbix/blob/release/7.0/ui/include/classes/core/CModuleManager.php).
