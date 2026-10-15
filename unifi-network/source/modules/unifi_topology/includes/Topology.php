<?php
namespace Modules\UniFiTopology\Includes;
use API;
use CSeverityHelper;
use InvalidArgumentException;
use RuntimeException;

/** All calls use the current frontend user's API context; no token or direct SQL. */
class Topology {
    public static function mac($value): string {
        $value = strtolower(str_replace('-', ':', (string) $value));
        if (!preg_match('/^(?:[0-9a-f]{2}:){5}[0-9a-f]{2}$/D', $value)) {
            throw new InvalidArgumentException('Ungueltige oder fehlende MAC-Adresse im Topologie-JSON.');
        }
        return $value;
    }

    public static function parseBindings(string $text): array {
        $bindings = [];
        foreach (preg_split('/\R/', trim($text)) as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            $pair = array_map('trim', explode('=', $line, 2));
            if (count($pair) !== 2 || !preg_match('/^[1-9][0-9]*$/D', $pair[1])) {
                throw new InvalidArgumentException('Zuordnung erwartet MAC=Host-ID, eine Zuordnung pro Zeile.');
            }
            $mac = self::mac($pair[0]);
            if (isset($bindings[$mac])) {
                throw new InvalidArgumentException('Doppelte MAC-Adresse in der Host-Zuordnung.');
            }
            $bindings[$mac] = $pair[1];
        }
        return $bindings;
    }

    private function result($value): array {
        if (!is_array($value)) {
            throw new RuntimeException('Zabbix-Abfrage fehlgeschlagen oder Berechtigung fehlt.');
        }
        return $value;
    }

    public static function normalize(array $raw): array {
        if (!isset($raw['vertices'], $raw['edges']) || !is_array($raw['vertices']) || !is_array($raw['edges'])
                || $raw['vertices'] !== array_values($raw['vertices']) || $raw['edges'] !== array_values($raw['edges'])) {
            throw new InvalidArgumentException('vertices und edges muessen JSON-Listen sein.');
        }
        if (count($raw['vertices']) > 1000 || count($raw['edges']) > 3000) {
            throw new InvalidArgumentException('Topologie zu gross: maximal 1000 Knoten und 3000 Verbindungen.');
        }
        $nodes = [];
        foreach ($raw['vertices'] as $vertex) {
            if (!is_array($vertex)) {
                throw new InvalidArgumentException('Ungueltiger Topologieknoten.');
            }
            $mac = self::mac($vertex['mac'] ?? '');
            if (isset($nodes[$mac])) {
                throw new InvalidArgumentException('Doppelte MAC-Adresse im Topologie-JSON.');
            }
            $nodes[$mac] = [
                'mac' => $mac,
                'name' => (string) ($vertex['name'] ?? $mac),
                'type' => (string) ($vertex['type'] ?? 'UNKNOWN'),
                'model' => (string) ($vertex['model'] ?? ''),
                'wireless' => !empty($vertex['wifiRadios']),
                'hostid' => null,
                'host_name' => null,
                'mapping' => 'Nicht zugeordnet',
                'monitored' => false,
                'maintenance' => false,
                'problems' => []
            ];
        }
        $edges = [];
        $seen = [];
        $missing = 0;
        foreach ($raw['edges'] as $edge) {
            if (!is_array($edge)) {
                throw new InvalidArgumentException('Ungueltige Topologieverbindung.');
            }
            $up = self::mac($edge['uplinkMac'] ?? '');
            $down = self::mac($edge['downlinkMac'] ?? '');
            if (!isset($nodes[$up], $nodes[$down])) {
                $missing++;
                continue;
            }
            if ($up === $down) {
                continue;
            }
            $entry = ['up' => $up, 'down' => $down, 'type' => (string) ($edge['type'] ?? 'UNKNOWN')];
            foreach (['uplinkPortNumber', 'downlinkPortNumber', 'rateMbps', 'essid', 'channel', 'experienceScore'] as $field) {
                $entry[$field] = isset($edge[$field]) && is_scalar($edge[$field]) ? $edge[$field] : null;
            }
            $identity = json_encode($entry);
            if (!isset($seen[$identity])) {
                $edges[] = $entry;
                $seen[$identity] = true;
            }
        }
        return [$nodes, $edges, $missing];
    }

    public function load(array $fields): array {
        $itemids = $fields['itemid'] ?? [];
        if (count($itemids) !== 1) {
            throw new InvalidArgumentException('Bitte das Item UniFi topology: Raw JSON auswaehlen.');
        }
        $items = $this->result(API::Item()->get([
            'output' => ['itemid', 'hostid', 'value_type', 'key_', 'status', 'state'],
            'itemids' => $itemids,
            'webitems' => true
        ]));
        if (count($items) !== 1 || (int) $items[0]['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
            throw new RuntimeException('Text-Item nicht vorhanden oder keine Leseberechtigung.');
        }
        $records = $this->result(API::History()->get([
            'output' => ['clock', 'value'],
            'history' => ITEM_VALUE_TYPE_TEXT,
            'itemids' => [$items[0]['itemid']],
            'sortfield' => ['clock', 'ns'],
            'sortorder' => 'DESC',
            'limit' => 1
        ]));
        if (!$records) {
            throw new RuntimeException('Noch keine Topologiedaten gespeichert.');
        }
        $raw = json_decode($records[0]['value'], true);
        if (!is_array($raw) || json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Topologie-JSON ungueltig oder abgeschnitten.');
        }
        [$nodes, $edges, $missing] = self::normalize($raw);
        $warnings = [];
        $age = time() - (int) $records[0]['clock'];
        $max_age = (int) ($fields['max_age'] ?? 300);
        $stale = $age > $max_age || $age < -60 || (int) $items[0]['status'] !== 0 || (int) $items[0]['state'] !== 0;
        if ($stale) {
            $warnings[] = 'Topologiedaten veraltet, Zeitstempel unplausibel oder Quell-Item nicht aktiv/unterstuetzt. Verbindungen sind der letzte gespeicherte Stand.';
        }
        if (!empty($raw['has_unknown_switch'])) {
            $warnings[] = 'UniFi meldet einen unbekannten Switch. Zwischenliegende Geraete koennen fehlen; Verbindungen sind keine Garantie fuer direkte Kabel.';
        }
        if ($missing) {
            $warnings[] = $missing.' Verbindungen ohne vollstaendige Knoten wurden ausgelassen.';
        }
        $manual = ManualTopology::parse($fields['manual_nodes'] ?? '', $fields['manual_links'] ?? '');
        $bindings = self::parseBindings($fields['bindings'] ?? '') + ManualTopology::apply($nodes, $edges, $warnings, $manual);
        $pattern = $fields['host_pattern'] ?? '';
        if ($pattern !== '' && substr_count($pattern, '{name}') !== 1) {
            throw new InvalidArgumentException('Namensmuster muss genau einmal {name} enthalten.');
        }
        $names = [];
        foreach ($nodes as $mac => $node) {
            if ($node['type'] === 'DEVICE' && empty($node['manual']) && !isset($bindings[$mac]) && $pattern !== '') {
                $names[$mac] = str_replace('{name}', $node['name'], $pattern);
            }
        }
        $hosts = [];
        $options = ['output' => ['hostid', 'host', 'name', 'status', 'maintenance_status']];
        if ($names) {
            foreach (['host', 'name'] as $field) {
                $rows = $this->result(API::Host()->get($options + ['filter' => [$field => array_values(array_unique($names))]]));
                foreach ($rows as $host) {
                    $hosts[(string) $host['hostid']] = $host;
                }
            }
        }
        if ($bindings) {
            $rows = $this->result(API::Host()->get($options + ['hostids' => array_values(array_unique($bindings))]));
            foreach ($rows as $host) {
                $hosts[(string) $host['hostid']] = $host;
            }
        }
        $host_nodes = [];
        foreach ($nodes as $mac => &$node) {
            $candidates = [];
            if (isset($bindings[$mac])) {
                if (isset($hosts[$bindings[$mac]])) {
                    $candidates[] = $bindings[$mac];
                }
                $node['mapping'] = !empty($node['manual']) ? 'Manuelles Gerät mit expliziter Host-ID' : 'Explizite MAC/Host-ID-Zuordnung';
            }
            elseif (isset($names[$mac])) {
                foreach ($hosts as $id => $host) {
                    if ($host['host'] === $names[$mac] || $host['name'] === $names[$mac]) {
                        $candidates[] = (string) $id;
                    }
                }
                $node['mapping'] = 'Exaktes Namensmuster';
            }
            if (count($candidates) === 1) {
                $id = $candidates[0];
                $node['hostid'] = $id;
                $node['host_name'] = $hosts[$id]['name'];
                $node['monitored'] = (int) $hosts[$id]['status'] === 0;
                $node['maintenance'] = (int) $hosts[$id]['maintenance_status'] === 1;
                $host_nodes[$id][] = $mac;
            }
            else {
                $node['mapping'] = count($candidates) > 1 ? 'Mehrdeutige Host-Zuordnung' : 'Kein lesbarer Host zugeordnet';
            }
        }
        unset($node);
        // Never attach one host to multiple topology nodes automatically.
        foreach ($host_nodes as $id => $macs) {
            if (count($macs) > 1) {
                foreach ($macs as $mac) {
                    $nodes[$mac]['hostid'] = null;
                    $nodes[$mac]['monitored'] = false;
                    $nodes[$mac]['mapping'] = 'Host mehrfach zugeordnet; Zuordnung korrigieren';
                }
                unset($host_nodes[$id]);
            }
        }
        if ($host_nodes) {
            $params = [
                'output' => ['eventid', 'objectid', 'name', 'severity', 'clock', 'acknowledged', 'suppressed'],
                'hostids' => array_keys($host_nodes),
                'source' => EVENT_SOURCE_TRIGGERS,
                'object' => EVENT_OBJECT_TRIGGER,
                'recent' => false,
                'sortfield' => ['eventid'],
                'sortorder' => 'DESC',
                'limit' => 2001
            ];
            if (empty($fields['show_suppressed'])) {
                $params['suppressed'] = false;
            }
            $problems = $this->result(API::Problem()->get($params));
            if (count($problems) > 2000) {
                throw new RuntimeException('Mehr als 2000 offene Probleme; keine unvollstaendige Statusanzeige moeglich.');
            }
            if ($problems) {
                $triggers = $this->result(API::Trigger()->get([
                    'output' => ['triggerid'],
                    'triggerids' => array_values(array_unique(array_column($problems, 'objectid'))),
                    'selectHosts' => ['hostid']
                ]));
                $trigger_hosts = [];
                foreach ($triggers as $trigger) {
                    $trigger_hosts[(string) $trigger['triggerid']] = array_column($trigger['hosts'], 'hostid');
                }
                foreach ($problems as $problem) {
                    $triggerid = (string) $problem['objectid'];
                    if (!isset($trigger_hosts[$triggerid])) {
                        throw new RuntimeException('Problem konnte keinem lesbaren Trigger zugeordnet werden. Bitte erneut laden.');
                    }
                    foreach ($trigger_hosts[$triggerid] as $hostid) {
                        foreach ($host_nodes[$hostid] ?? [] as $mac) {
                            $nodes[$mac]['problems'][] = [
                                'eventid' => (string) $problem['eventid'],
                                'triggerid' => $triggerid,
                                'name' => $problem['name'],
                                'severity' => (int) $problem['severity'],
                                'clock' => (int) $problem['clock'],
                                'acknowledged' => (bool) $problem['acknowledged'],
                                'suppressed' => (bool) $problem['suppressed']
                            ];
                        }
                    }
                }
            }
        }
        $severities = [];
        for ($i = 0; $i <= 5; $i++) {
            $severities[] = ['name' => CSeverityHelper::getName($i), 'color' => '#'.CSeverityHelper::getColor($i)];
        }
        $traffic_period = Traffic::load(
            $nodes,
            (string) $items[0]['hostid'],
            $fields['time_period'] ?? ['from' => 'now-1h', 'to' => 'now']
        );
        if ($traffic_period['items'] === 0) {
            $warnings[] = 'Keine Traffic-Items gefunden. Das aktualisierte UniFi-Template importieren und die Discovery abwarten.';
        }
        return [
            'nodes' => array_values($nodes),
            'edges' => $edges,
            'warnings' => $warnings,
            'severities' => $severities,
            'clock' => (int) $records[0]['clock'],
            'checked_at' => time(),
            'max_age' => $max_age,
            'stale' => $stale,
            'show_suppressed' => !empty($fields['show_suppressed']),
            'traffic_period' => ['from' => $traffic_period['from'], 'to' => $traffic_period['to']]
        ];
    }
}
