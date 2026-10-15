<?php
namespace Modules\PbsLongest\Includes;

/** Read only through the session-bound Zabbix API, preserving host permissions. */
class Data {
    public array $warnings = [];
    public ?int $updated = null;
    public ?int $coverageFrom = null;
    private array $items = [];
    public function __construct(string $hostid) {
        $items = \API::Item()->get(['hostids' => [$hostid], 'output' => ['itemid', 'key_', 'value_type', 'state', 'status'], 'search' => ['key_' => 'pbs.'], 'webitems' => true]);
        foreach ($items as $item) { $this->items[$item['key_']] = $item; }
    }
    private function item(string $key): array {
        if (!isset($this->items[$key])) { throw new \RuntimeException('Item fehlt oder ist nicht lesbar: '.$key); }
        $item = $this->items[$key];
        if ($item['status'] != 0 || $item['state'] != 0) { $this->warnings[] = 'Item deaktiviert/nicht unterstützt: '.$key; }
        return $item;
    }
    public function latest(string $key, ?int $before = null): array {
        $item = $this->item($key);
        $rows = \API::History()->get(['itemids' => [$item['itemid']], 'history' => (int) $item['value_type'], 'output' => 'extend', 'time_till' => $before ?? time(), 'sortfield' => ['clock', 'ns'], 'sortorder' => 'DESC', 'limit' => 1]);
        if (!$rows) { throw new \RuntimeException('Keine gespeicherten Werte: '.$key, 1001); }
        return $rows[0];
    }
    public static function decode(string $json): array {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) { throw new \RuntimeException('Ungültige JSON-Daten'); }
        return $data;
    }
    public function tasks(int $from, int $to): array {
        $ids = [];
        for ($i = 0; $i < 16; $i++) { $ids[] = $this->item('pbs.dashboard.tasks.completed['.$i.']')['itemid']; }
        $cursor = time();
        $needed = min($to, $cursor);
        $tasks = [];
        $complete = false;
        for ($step = 0; $step < 96; $step++) {
            try { $head = $this->latest('pbs.dashboard.tasks.completed[0]', $cursor); }
            catch (\RuntimeException $e) { break; }
            $meta = self::decode($head['value']);
            if (!isset($meta['since'], $meta['until']) || $meta['since'] >= $meta['until']) { throw new \RuntimeException('Ungültiges Task-Zeitfenster'); }
            if ($step === 0) { $this->updated = (int) $meta['until']; }
            $this->coverageFrom = (int) $meta['since'];
            if ($step === 0 && time() - $meta['until'] > 900) { $this->warnings[] = 'Task-Daten sind älter als 15 Minuten.'; }
            if ($step > 0 && $meta['until'] < $needed) { $this->warnings[] = 'Datenlücke bis '.date('Y-m-d H:i', $needed).'; Statistik unvollständig.'; }
            if ($meta['since'] <= $to) {
                $rows = \API::History()->get(['itemids' => $ids, 'history' => 4, 'output' => 'extend', 'time_from' => (int) $head['clock'], 'time_till' => (int) $head['clock'], 'limit' => 33]);
                $parts = [];
                foreach ($rows as $row) {
                    if ($row['ns'] != $head['ns']) { continue; }
                    $part = self::decode($row['value']);
                    if (($part['since'] ?? null) !== $meta['since'] || ($part['until'] ?? null) !== $meta['until'] || !isset($part['tasks']) || !is_array($part['tasks'])) { throw new \RuntimeException('Inkonsistenter Task-Snapshot'); }
                    $parts[$row['itemid']] = $part;
                }
                if (count($parts) !== 16) { throw new \RuntimeException('Task-Snapshot unvollständig: nicht alle 16 History-Items vorhanden.'); }
                foreach ($parts as $part) {
                    foreach ($part['tasks'] as $task) {
                        if (!isset($task['upid'], $task['endtime'], $task['starttime'], $task['worker_type'])) { throw new \RuntimeException('Ungültiger Task-Datensatz'); }
                        if ($task['endtime'] >= $from && $task['endtime'] <= $to && !isset($tasks[$task['upid']])) { $tasks[$task['upid']] = $task; }
                    }
                }
            }
            if ($meta['since'] <= $from) { $complete = true; break; }
            $needed = min($needed, (int) $meta['since']);
            $cursor = min((int) $head['clock'] - 1, (int) $meta['since'] + 3600);
        }
        if (!$complete) { $this->warnings[] = 'Zeitraum nur teilweise erfasst.'.($this->coverageFrom !== null ? ' Verfügbare Snapshots reichen bis '.date('d.m.Y H:i', $this->coverageFrom).' zurück.' : ' Noch keine Task-Snapshots vorhanden.').' Der anfängliche Rückblick beträgt standardmäßig 48 Stunden; ältere PBS-Tasks werden dadurch noch nicht übernommen.'; }
        return array_values($tasks);
    }
    public function running(): array {
        $row = $this->latest('pbs.dashboard.tasks.running');
        $data = self::decode($row['value']);
        if (!isset($data['until'], $data['tasks']) || !is_array($data['tasks'])) { throw new \RuntimeException('Ungültige Running-Tasks-Daten'); }
        if (time() - min((int) $row['clock'], (int) $data['until']) > 180) { throw new \RuntimeException('Running-Tasks-Daten sind veraltet (>3 Minuten). Aktueller Zustand unbekannt.'); }
        foreach ($data['tasks'] as &$task) { $task['_observed_at'] = min((int) $row['clock'], (int) $data['until']); }
        unset($task);
        return $data['tasks'];
    }
    public function datastores(int $from, int $to): array {
        $row = $this->latest('pbs.datastore.status');
        if (time() - $row['clock'] > 900) { $this->warnings[] = 'Datastore-Werte sind älter als 15 Minuten.'; }
        $stores = self::decode($row['value']);
        foreach ($stores as &$store) {
            $store['points'] = [];
            if (!isset($store['store'])) { throw new \RuntimeException('Datastore-Name fehlt'); }
            $key = 'pbs.datastore.used['.$store['store'].']';
            try {
                $item = $this->item($key);
                $history = \API::History()->get(['itemids' => [$item['itemid']], 'history' => (int) $item['value_type'], 'output' => ['clock', 'value'], 'time_from' => $from, 'time_till' => $to, 'sortfield' => 'clock', 'sortorder' => 'ASC', 'limit' => 10001]);
                if (count($history) > 10000) {
                    $this->warnings[] = 'Verlauf für '.$store['store'].': Stundenmittelwerte (Trends).';
                    $history = \API::Trend()->get(['itemids' => [$item['itemid']], 'output' => ['clock', 'value_avg'], 'time_from' => $from, 'time_till' => $to, 'limit' => 10001]);
                    usort($history, static fn($a, $b) => $a['clock'] <=> $b['clock']);
                    if (count($history) > 10000) { $this->warnings[] = 'Verlauf zu groß; bitte Zeitraum verkürzen.'; $history = []; }
                }
                $buckets = [];
                foreach ($history as $point) {
                    $index = min(119, (int) floor(120 * ($point['clock'] - $from) / max(1, $to - $from)));
                    $buckets[$index] = [(int) $point['clock'], (float) ($point['value'] ?? $point['value_avg'])];
                }
                $store['points'] = array_values($buckets);
                if (!$history) { $this->warnings[] = 'Kein Verlauf im gewählten Zeitraum: '.$store['store']; }
            }
            catch (\RuntimeException $e) { $this->warnings[] = $e->getMessage(); }
        }
        unset($store);
        return $stores;
    }
    public static function category(string $type): string {
        foreach (['backup' => 'Backups', 'prune' => 'Prunes', 'garbage_collection' => 'Garbage collections', 'sync' => 'Syncs', 'verif' => 'Verify', 'tape-backup' => 'Tape Backup', 'tape-restore' => 'Tape Restore'] as $prefix => $label) {
            if (strpos($type, $prefix) === 0) { return $label; }
        }
        return 'Other';
    }
    public static function label(array $task): string {
        $id = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static fn($m) => chr(hexdec($m[1])), $task['worker_id'] ?? '');
        $type = $task['worker_type'];
        $parts = explode(':', $id, 2);
        if ($type === 'backup' && count($parts) === 2) { return 'Datastore '.$parts[0].' - Backup '.$parts[1]; }
        if ($type === 'garbage_collection') { return 'Datastore '.$id.' - Garbage Collect'; }
        if ($type === 'verifyjob') { return 'Verify Job '.$id.' - Scheduled Verification'; }
        if (strpos($type, 'verif') === 0) { return 'Datastore '.$id.' - Verification'; }
        return self::category($type).' · '.($id ?: $type);
    }
    public static function status(string $status): string {
        if ($status === 'OK') { return 'ok'; }
        if (preg_match('/^WARNINGS:\s*\d+$/', $status)) { return 'warning'; }
        return $status === '' || strtolower($status) === 'unknown' ? 'unknown' : 'error';
    }
}
