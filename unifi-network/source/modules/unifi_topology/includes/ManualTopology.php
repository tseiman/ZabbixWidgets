<?php
namespace Modules\UniFiTopology\Includes;
use InvalidArgumentException;

/** Optional, per-widget additions. Never modify the source JSON or Zabbix objects. */
class ManualTopology {
    private static function lines(string $text, int $max): array {
        $lines = [];
        foreach (preg_split('/\R/', $text) as $number => $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) continue;
            if (strlen($line) > 500) throw new InvalidArgumentException('Manuelle Topologie: Zeile zu lang.');
            $lines[$number + 1] = array_map('trim', explode('|', $line));
        }
        if (count($lines) > $max) throw new InvalidArgumentException('Zu viele manuelle Eintraege (maximal '.$max.').');
        return $lines;
    }
    private static function reference(string $text, array $nodes): string {
        if (isset($nodes['manual:'.$text])) return 'manual:'.$text;
        if (preg_match('/^(?:[0-9a-f]{2}[:-]){5}[0-9a-f]{2}$/iD', $text)) return Topology::mac($text);
        throw new InvalidArgumentException('Unbekannte Geraete-ID "'.$text.'". Manuelle ID oder UniFi-MAC verwenden.');
    }
    public static function parse(string $device_text, string $link_text): array {
        $nodes = [];
        $bindings = [];
        foreach (self::lines($device_text, 50) as $line => $parts) {
            if (count($parts) < 2 || count($parts) > 3 || !preg_match('/^[a-z][a-z0-9_-]{0,39}$/D', $parts[0]) || $parts[1] === '' || strlen($parts[1]) > 100) {
                throw new InvalidArgumentException('Manuelles Geraet, Zeile '.$line.': id | Anzeigename | optionale Host-ID erwartet.');
            }
            $id = 'manual:'.$parts[0];
            if (isset($nodes[$id])) throw new InvalidArgumentException('Doppelte manuelle Geraete-ID: '.$parts[0]);
            $hostid = $parts[2] ?? '';
            if ($hostid !== '' && !preg_match('/^[1-9][0-9]{0,19}$/D', $hostid)) {
                throw new InvalidArgumentException('Manuelles Geraet: Host-ID muss eine positive Zahl sein.');
            }
            $nodes[$id] = ['mac' => $id, 'name' => $parts[1], 'type' => 'DEVICE', 'model' => '', 'wireless' => false, 'manual' => true, 'hostid' => null, 'host_name' => null, 'mapping' => 'Manuell, ohne Statusquelle', 'monitored' => false, 'maintenance' => false, 'problems' => []];
            if ($hostid !== '') $bindings[$id] = $hostid;
        }
        $links = [];
        $seen = [];
        foreach (self::lines($link_text, 100) as $line => $parts) {
            if (count($parts) < 2 || count($parts) > 3) {
                throw new InvalidArgumentException('Manuelle Verbindung, Zeile '.$line.': von | nach | optionale Beschriftung erwartet.');
            }
            $up = self::reference($parts[0], $nodes);
            $down = self::reference($parts[1], $nodes);
            if ($up === $down) throw new InvalidArgumentException('Manuelle Verbindung darf nicht auf dasselbe Geraet zeigen.');
            $pair = [$up, $down]; sort($pair);
            $key = implode('|', $pair);
            if (isset($seen[$key])) throw new InvalidArgumentException('Doppelte manuelle Verbindung in Zeile '.$line.'.');
            $seen[$key] = true;
            $label = $parts[2] ?? '';
            if (strlen($label) > 150) throw new InvalidArgumentException('Verbindungsbeschriftung ist zu lang.');
            $links[] = ['up' => $up, 'down' => $down, 'type' => 'MANUAL', 'manual' => true, 'label' => $label, 'uplinkPortNumber' => null, 'downlinkPortNumber' => null, 'rateMbps' => null, 'essid' => null, 'channel' => null, 'experienceScore' => null];
        }
        return [$nodes, $links, $bindings];
    }
    public static function apply(array &$nodes, array &$edges, array &$warnings, array $config): array {
        [$manual_nodes, $manual_links, $bindings] = $config;
        $nodes += $manual_nodes;
        $pairs = [];
        foreach ($edges as $edge) {
            $pair = [$edge['up'], $edge['down']]; sort($pair);
            $pairs[implode('|', $pair)] = true;
        }
        foreach ($manual_links as $edge) {
            if (!isset($nodes[$edge['up']], $nodes[$edge['down']])) {
                $warnings[] = 'Manuelle Verbindung nicht dargestellt: '.$edge['up'].' → '.$edge['down'].'. Ein Endpunkt fehlt im aktuellen JSON.';
                continue;
            }
            if ($nodes[$edge['up']]['type'] !== 'DEVICE' || $nodes[$edge['down']]['type'] !== 'DEVICE') {
                $warnings[] = 'Manuelle Verbindung ausgelassen: Nur Netzwerkgeraete und manuelle Knoten sind erlaubt.';
                continue;
            }
            $pair = [$edge['up'], $edge['down']]; sort($pair);
            if (isset($pairs[implode('|', $pair)])) {
                $warnings[] = 'Automatische UniFi-Verbindung hat Vorrang vor manueller Verbindung: '.$nodes[$edge['up']]['name'].' → '.$nodes[$edge['down']]['name'];
                continue;
            }
            $edges[] = $edge;
        }
        return $bindings;
    }
}
