<?php
namespace Modules\PbsLongest\Includes;

/** Module-local translations, independent from the Zabbix gettext catalog. */
final class I18n {
    private const DE = [
        'Close' => 'Schließen',
        'Data is stale' => 'Daten veraltet',
        'Snapshot when opened' => 'Stand beim Öffnen',
        'newest start time first' => 'neueste Startzeit zuerst',
        'tasks' => 'Tasks',
        'Task' => 'Task',
        'Start time' => 'Startzeit',
        'Start time ↓' => 'Startzeit ↓',
        'End time' => 'Endzeit',
        'Duration' => 'Dauer',
        'Status' => 'Status',
        'Description' => 'Beschreibung',
        'User name' => 'Benutzername',
        'Show more tasks' => 'Weitere Tasks anzeigen',
        'Running' => 'Läuft gerade',
        'Unknown' => 'Unbekannt',
        'No tasks in this category.' => 'Keine Tasks in dieser Kategorie.',
        'Open task log' => 'Task-Log öffnen',
        'Open task details' => 'Task-Details öffnen',
        'Task log' => 'Task-Log',
        'Reload' => 'Neu laden',
        'More log lines' => 'Weitere Log-Zeilen',
        'Loading log …' => 'Log wird geladen …',
        'Refresh the widget; host mapping is missing.' => 'Widget bitte aktualisieren; Host-Zuordnung fehlt.',
        'Log request failed (HTTP %d).' => 'Log-Anfrage fehlgeschlagen (HTTP %d).',
        'Log unavailable.' => 'Log nicht verfügbar.',
        'Invalid response; check the Zabbix login and module activation.' => 'Ungültige Antwort; Zabbix-Anmeldung und Modulaktivierung prüfen.',
        'Display limit reached (20,000 lines / 8 million characters). Open the complete log in PBS.' => 'Anzeigegrenze erreicht (20.000 Zeilen / 8 Mio. Zeichen). Vollständiges Log in PBS öffnen.',
        '%d of %d lines' => '%d von %d Zeilen',
        'Task is running; reload to refresh' => 'Task läuft, zum Aktualisieren neu laden',
        'No log lines yet' => 'Noch keine Log-Zeilen vorhanden',
        'Timed out. Please reload.' => 'Zeitüberschreitung. Bitte erneut laden.',
        'No valid response. Check the Zabbix login and module activation.' => 'Keine gültige Antwort. Zabbix-Anmeldung und Modulaktivierung prüfen.',
        'PBS host' => 'PBS-Host',
        'Time period' => 'Zeitraum',
        'Rows' => 'Zeilen',
        'Exclude tasks (expression)' => 'Tasks ausschließen (Expression)',
        'Exclude tasks' => 'Tasks ausschließen',
        'PBS Task Summary' => 'PBS Task-Zusammenfassung',
        'PBS Task List' => 'PBS Task-Liste',
        'PBS Longest Tasks' => 'PBS Längste Tasks',
        'PBS Running Tasks' => 'PBS Laufende Tasks',
        'PBS Datastore Usage' => 'PBS Datastore-Auslastung',
        'Select exactly one PBS host.' => 'Bitte genau einen PBS-Host auswählen.',
        'Host does not exist or access is denied.' => 'Host nicht vorhanden oder keine Berechtigung.',
        'Invalid time period.' => 'Ungültiger Zeitraum.',
        'Select a time period of no more than 90 days.' => 'Bitte einen Zeitraum von maximal 90 Tagen wählen.',
        'Item is missing or unreadable: ' => 'Item fehlt oder ist nicht lesbar: ',
        'Item is disabled or unsupported: ' => 'Item deaktiviert/nicht unterstützt: ',
        'No stored values: ' => 'Keine gespeicherten Werte: ',
        'Invalid JSON data.' => 'Ungültige JSON-Daten.',
        'Invalid task time window.' => 'Ungültiges Task-Zeitfenster.',
        'Task data is more than 15 minutes old.' => 'Task-Daten sind älter als 15 Minuten.',
        'Data gap until %s; statistics are incomplete.' => 'Datenlücke bis %s; Statistik unvollständig.',
        'Inconsistent task snapshot.' => 'Inkonsistenter Task-Snapshot.',
        'Task snapshot is incomplete: not all 16 history items are present.' => 'Task-Snapshot unvollständig: nicht alle 16 History-Items vorhanden.',
        'Invalid task record.' => 'Ungültiger Task-Datensatz.',
        'The time period is only partially captured.' => 'Zeitraum nur teilweise erfasst.',
        ' Available snapshots go back to %s.' => ' Verfügbare Snapshots reichen bis %s zurück.',
        ' No task snapshots are available yet.' => ' Noch keine Task-Snapshots vorhanden.',
        ' The initial lookback is 48 hours by default; older PBS tasks have not been imported yet.' => ' Der anfängliche Rückblick beträgt standardmäßig 48 Stunden; ältere PBS-Tasks wurden noch nicht übernommen.',
        'Invalid running-task data.' => 'Ungültige Running-Tasks-Daten.',
        'Running-task data is stale (>3 minutes). The current state is unknown.' => 'Running-Tasks-Daten sind veraltet (>3 Minuten). Aktueller Zustand unbekannt.',
        'Datastore values are more than 15 minutes old.' => 'Datastore-Werte sind älter als 15 Minuten.',
        'Datastore name is missing.' => 'Datastore-Name fehlt.',
        'History for %s: hourly averages (trends).' => 'Verlauf für %s: Stundenmittelwerte (Trends).',
        'History is too large; select a shorter time period.' => 'Verlauf zu groß; bitte Zeitraum verkürzen.',
        'No history in the selected time period: %s' => 'Kein Verlauf im gewählten Zeitraum: %s',
        'Backups' => 'Backups',
        'Prunes' => 'Prunes',
        'Garbage collections' => 'Garbage Collections',
        'Syncs' => 'Synchronisierungen',
        'Verify' => 'Prüfungen',
        'Tape Backup' => 'Band-Backup',
        'Tape Restore' => 'Band-Wiederherstellung',
        'Other' => 'Sonstige',
        'Datastore %s - Backup %s' => 'Datastore %s - Backup %s',
        'Datastore %s - Garbage Collect' => 'Datastore %s - Garbage Collection',
        'Verify Job %s - Scheduled Verification' => 'Prüfauftrag %s - Geplante Prüfung',
        'Datastore %s - Verification' => 'Datastore %s - Prüfung',
        'Invalid log request.' => 'Ungültige Log-Anfrage.',
        'The log could not be read. Check the server configuration.' => 'Log konnte nicht gelesen werden. Serverkonfiguration prüfen.',
        'Invalid task ID or line number.' => 'Ungültige Task-ID oder Zeilennummer.',
        'The PBS log connection is not configured yet. See the package README.md for setup.' => 'PBS-Log-Verbindung noch nicht eingerichtet. Einrichtung siehe README.md im Paket.',
        'No PBS log connection is configured for this Zabbix host.' => 'Für diesen Zabbix-Host ist keine PBS-Log-Verbindung eingerichtet.',
        'PBS configuration: an HTTPS base URL without a path or credentials is required.' => 'PBS-Konfiguration: HTTPS-Basisadresse ohne Pfad oder Zugangsdaten erforderlich.',
        'PBS configuration: token ID or secret is missing or invalid.' => 'PBS-Konfiguration: Token-ID oder Secret fehlt/ist ungültig.',
        'PBS configuration: CA file is unreadable.' => 'PBS-Konfiguration: CA-Datei ist nicht lesbar.',
        'PBS returned invalid log data.' => 'PBS lieferte keine gültigen Log-Daten.',
        'PBS returned invalid log lines.' => 'PBS lieferte ungültige Log-Zeilen.',
        'The PHP cURL extension is missing from the Zabbix frontend.' => 'Die PHP-cURL-Erweiterung fehlt im Zabbix-Webfrontend.',
        'The log page exceeds 2 MiB. Open the log directly in PBS.' => 'Log-Seite überschreitet 2 MiB. Bitte das Log direkt in PBS öffnen.',
        'PBS is unreachable or TLS verification failed (cURL %d).' => 'PBS nicht erreichbar oder TLS-Prüfung fehlgeschlagen (cURL %d).',
        'PBS log unavailable (HTTP %d). Check permissions and PBS log retention.' => 'PBS-Log nicht verfügbar (HTTP %d). Berechtigungen und PBS-Log-Aufbewahrung prüfen.',
        'Datastore %s Read Objects %s' => 'Datastore %s Objekte lesen %s',
        'Prune on Datastore %s' => 'Prune auf Datastore %s',
        'Update package database' => 'Paketdatenbank aktualisieren',
        'Current' => 'Aktuell',
        'Unknown completion status (not counted as Failed/Warning/OK): ' => 'Unbekannter Abschlussstatus (nicht in Fehlgeschlagen/Warnung/OK gezählt): ',
        'Failed' => 'Fehlgeschlagen',
        'Warning' => 'Warnung',
        'Name' => 'Name',
        'Size' => 'Größe',
        'Used' => 'Belegt',
        'Available' => 'Verfügbar',
        'Usage %' => 'Auslastung %',
        'Estimated full' => 'Voraussichtlich voll',
        'History' => 'Verlauf',
        'No growth predicted' => 'Kein Wachstum prognostiziert',
        'in %s' => 'in %s',
        'Used bytes in the selected time period, scale zero to total capacity' => 'Belegte Bytes im gewählten Zeitraum, Skala null bis Gesamtkapazität',
        'Used storage · scale 0 to total capacity' => 'Belegter Speicher · Skala 0 bis Gesamtkapazität',
        'Current values · History: used bytes in selected time period · Forecast by PBS.' => 'Werte aktuell · Verlauf: belegte Bytes im gewählten Zeitraum · Prognose von PBS.',
        'No running tasks.' => 'Keine laufenden Tasks.',
        'No completed tasks in the available time period.' => 'Keine abgeschlossenen Tasks im verfügbaren Zeitraum.',
        'End: ' => 'Ende: ',
        'Data notes' => 'Datenhinweise',
        'Time period partially captured' => 'Zeitraum teilweise erfasst',
        'Updated %s' => 'Stand %s',
        'Newest start time first' => 'Neueste Startzeit zuerst',
        '%d of %d tasks · Change the row count in the widget settings.' => '%d von %d Tasks · Zeilenanzahl in den Widget-Einstellungen ändern.',
        'Matches are excluded from counts and details. Empty = no filter. Fields: worker_type, status, worker_id, user, node. Operators: ===, !==, &&, ||, !, and parentheses. Quoted strings are case-sensitive. Example: ' => 'Treffer werden aus Zählung und Details ausgeschlossen. Leer = kein Filter. Felder: worker_type, status, worker_id, user, node. Operatoren: ===, !==, &&, ||, ! und Klammern. Texte in Anführungszeichen; Groß-/Kleinschreibung wird beachtet. Beispiel: ',
        'Maximum 255 bytes allowed.' => 'Maximal 255 Bytes erlaubt.',
        'Unexpected character' => 'Unerwartetes Zeichen',
        '%s at position %d.' => '%s an Position %d.',
        'Closing parenthesis is missing' => 'Schließende Klammer fehlt',
        'Field name expected' => 'Feldname erwartet',
        'Unknown field %s' => 'Unbekanntes Feld %s',
        'Comparison === or !== expected' => 'Vergleich === oder !== erwartet',
        'Text in single or double quotes expected' => 'Text in einfachen oder doppelten Anführungszeichen erwartet',
        'Only quotes and backslashes may be escaped' => 'Nur Anführungszeichen und Backslash dürfen maskiert werden',
        'Closing quote is missing' => 'Schließendes Anführungszeichen fehlt'
    ];

    public static function normalize(?string $locale): string {
        $base = strtolower(explode('-', str_replace('_', '-', trim((string) $locale)), 2)[0]);
        return $base === 'de' ? 'de' : 'en';
    }

    public static function locale(): string {
        $locale = class_exists('\\CWebUser') && isset(\CWebUser::$data['lang'])
            ? (string) \CWebUser::$data['lang']
            : 'en';
        return self::normalize($locale);
    }

    public static function t(string $message): string {
        return self::locale() === 'de' ? (self::DE[$message] ?? $message) : $message;
    }

    public static function tf(string $message, ...$values): string {
        return sprintf(self::t($message), ...$values);
    }

    public static function dateTime(int $timestamp, bool $seconds = false): string {
        return date(self::locale() === 'de'
            ? ($seconds ? 'd.m.Y H:i:s' : 'd.m.Y H:i')
            : ($seconds ? 'Y-m-d H:i:s' : 'Y-m-d H:i'), $timestamp);
    }

    public static function javascript(array $messages): array {
        return array_combine($messages, array_map([self::class, 't'], $messages));
    }
}
