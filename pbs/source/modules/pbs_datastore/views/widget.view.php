<?php
use Modules\PbsDatastore\Includes\Data;
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$duration = static function($seconds) {
    $seconds = max(0, (int) $seconds);
    return ($seconds >= 86400 ? intdiv($seconds, 86400).'d ' : '').($seconds >= 3600 ? intdiv($seconds % 86400, 3600).'h ' : '').intdiv($seconds % 3600, 60).'m '.($seconds % 60).'s';
};
$bytes = static function($value) {
    if (!is_numeric($value)) { return '—'; }
    $i = 0;
    while ($value >= 1024 && $i < 5) { $value /= 1024; $i++; }
    return number_format((float) $value, 2).' '.['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'][$i];
};
$icon = static function($state, $title, $zero = false) use ($e) {
    return '<span class="pbs-icon pbs-'.($zero ? 'zero' : $state).'" title="'.$e($title).'" aria-label="'.$e($title).'">'.(['ok' => '✓', 'error' => '×', 'warning' => '!', 'unknown' => '?'][$state] ?? '?').'</span>';
};
$taskbutton = static function($state, $title, $tasks, $text) use ($e, $icon) {
    foreach ($tasks as &$task) { $task['label'] = Data::label($task); $task['start_label'] = date('Y-m-d H:i:s', $task['starttime']); }
    unset($task);
    return '<button type="button" class="pbs-task-button" data-pbs-title="'.$e($title).'" data-pbs-tasks="'.$e(json_encode($tasks, JSON_THROW_ON_ERROR)).'" aria-label="'.$e($title).'">'.$icon($state, $title, count($tasks) === 0).' '.$e($text).'</button>';
};
ob_start();
echo '<div class="pbs-panel">';
if ($data['error'] !== null) { echo '<div class="pbs-notice">'.$e($data['error']).'</div>'; }
else {
    echo '<div class="pbs-meta">'.$e($data['host']).($data['period'] ? ' · '.$e($data['period']) : ' · Aktuell').'</div>';
    if ($data['mode'] === 'summary') {
        $counts = [];
        $groups = [];
        foreach (['Backups', 'Prunes', 'Garbage collections', 'Syncs', 'Verify', 'Tape Backup', 'Tape Restore', 'Other'] as $label) { $counts[$label] = ['error' => 0, 'warning' => 0, 'ok' => 0, 'unknown' => 0, 'running' => 0]; $groups[$label] = ['error' => [], 'warning' => [], 'ok' => []]; }
        foreach ($data['rows'] as $task) { $counts[Data::category($task['worker_type'])][Data::status($task['status'] ?? '')]++; }
        $unknown = [];
        foreach ($data['rows'] as $task) { $state = Data::status($task['status'] ?? ''); if ($state === 'unknown') { $unknown[] = $task; } else { $groups[Data::category($task['worker_type'])][$state][] = $task; } }
        if ($unknown) { echo '<div class="pbs-notice">Unbekannter Abschlussstatus (nicht in Failed/Warning/OK gezählt): '.$taskbutton('unknown', 'Unknown', $unknown, count($unknown)).'</div>'; }
        echo '<table class="pbs-table pbs-summary"><thead><tr><th>Task</th><th>Failed</th><th>Warning</th><th>OK</th></tr></thead><tbody>';
        foreach ($counts as $label => $count) {
            if ($label === 'Other' && array_sum($count) === 0) { continue; }
            echo '<tr><td>'.$e($label).'</td><td>'.$taskbutton('error', $label.' · Failed', $groups[$label]['error'], $count['error']).'</td><td>'.$taskbutton('warning', $label.' · Warning', $groups[$label]['warning'], $count['warning']).'</td><td>'.$taskbutton('ok', $label.' · OK', $groups[$label]['ok'], $count['ok']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    elseif ($data['mode'] === 'datastore') {
        echo '<table class="pbs-table"><thead><tr><th>Name</th><th>Size</th><th>Used</th><th>Available</th><th>Usage %</th><th>Estimated Full</th><th>History</th></tr></thead><tbody>';
        foreach ($data['rows'] as $store) {
            echo '<tr><td>'.$e($store['store']).'</td>';
            if (!empty($store['error'])) { echo '<td colspan="6">'.$e($store['error']).'</td></tr>'; continue; }
            $pct = isset($store['used'], $store['total']) && $store['total'] > 0 ? 100 * $store['used'] / $store['total'] : null;
            $estimate = $store['estimated-full-date'] ?? null;
            $full = !is_numeric($estimate) ? '—' : ($estimate <= time() ? 'Kein Wachstum prognostiziert' : 'in '.$duration($estimate - time()));
            echo '<td>'.$bytes($store['total'] ?? null).'</td><td>'.$bytes($store['used'] ?? null).'</td><td>'.$bytes($store['avail'] ?? null).'</td><td>';
            if ($pct === null) { echo '—'; }
            else { echo '<div class="pbs-bar"><span style="width:'.min(100, max(0, $pct)).'%"></span><b>'.number_format($pct, 2).'%</b></div>'; }
            echo '</td><td>'.$e($full).'</td><td>';
            if (count($store['points']) > 1) {
                $points = [];
                $max = max(1, (float) ($store['total'] ?? max(array_column($store['points'], 1))));
                foreach ($store['points'] as [$clock, $used]) { $points[] = round(120 * ($clock - $data['from']) / max(1, $data['to'] - $data['from']), 2).','.round(28 - 26 * $used / $max, 2); }
                $first = explode(',', $points[0])[0];
                $last = explode(',', $points[count($points) - 1])[0];
                echo '<svg class="pbs-spark" viewBox="0 0 120 30" role="img" aria-label="Used bytes im gewählten Zeitraum, Skala null bis Gesamtkapazität"><title>Belegter Speicher · Skala 0 bis Gesamtkapazität</title><polygon class="pbs-spark-area" points="'.$first.',29 '.implode(' ', $points).' '.$last.',29"/><polyline points="'.implode(' ', $points).'"/></svg>';
            }
            else { echo '—'; }
            echo '</td></tr>';
        }
        echo '</tbody></table><div class="pbs-meta">Werte aktuell · History: belegte Bytes im gewählten Zeitraum · Prognose von PBS.</div>';
    }
    else {
        if (!$data['rows']) { echo '<p class="pbs-empty">'.($data['mode'] === 'running' ? 'Keine laufenden Tasks.' : 'Keine abgeschlossenen Tasks im verfügbaren Zeitraum.').'</p>'; }
        else {
            echo '<table class="pbs-table pbs-tasks"><thead><tr><th>Task</th>'.($data['mode'] === 'running' ? '<th>Start Time</th>' : '').'<th>Duration</th><th>'.($data['mode'] === 'running' ? '' : 'Status').'</th></tr></thead><tbody>';
            $rows = $data['mode'] === 'longest' ? array_slice($data['rows'], 0, $data['limit']) : $data['rows'];
            foreach ($rows as $task) {
                $running = $data['mode'] === 'running';
                $status = $running ? 'warning' : Data::status($task['status'] ?? '');
                $label = Data::label($task);
                $detail = $task['upid'].' | Start: '.date('Y-m-d H:i:s', $task['starttime']).($running ? '' : ' | Ende: '.date('Y-m-d H:i:s', $task['endtime']));
                $task['label'] = $label;
                $task['start_label'] = date('Y-m-d H:i:s', $task['starttime']);
                if ($running) { $task['status'] = 'RUNNING'; }
                echo '<tr><td title="'.$e($detail).'">'.$e($label).'</td>'.($running ? '<td>'.$e(date('Y-m-d H:i:s', $task['starttime'])).'</td>' : '').'<td><span'.($running ? ' data-pbs-start="'.(int) $task['starttime'].'" data-pbs-observed="'.(int) ($task['_observed_at'] ?? time()).'"' : '').'>'.$duration(($running ? time() : $task['endtime']) - $task['starttime']).'</span></td><td>'.($running ? '' : $icon($status, $task['status'] ?? 'Unbekannt').' ').'<button type="button" class="pbs-task-button" data-pbs-title="'.$e($label).'" data-pbs-tasks="'.$e(json_encode([$task], JSON_THROW_ON_ERROR)).'" aria-label="Task-Details öffnen">❯</button></td></tr>';
            }
            echo '</tbody></table>';
        }
    }
    $notes = array_values(array_unique($data['warnings']));
    if ($notes) {
        $partial = count(array_filter($notes, static fn($note) => strpos($note, 'teilweise') !== false || strpos($note, 'Datenlücke') !== false)) > 0;
        echo '<details class="pbs-health"><summary>ⓘ '.($partial ? 'Zeitraum teilweise erfasst' : 'Datenhinweise').(!empty($data['updated']) ? ' · Stand '.date('H:i', $data['updated']) : '').'</summary>';
        foreach ($notes as $note) { echo '<p>'.$e($note).'</p>'; }
        echo '</details>';
    }
    elseif (!empty($data['updated'])) { echo '<div class="pbs-health">Stand '.date('H:i', $data['updated']).'</div>'; }
}
echo '</div>';
(new CWidgetView($data))->addItem(ob_get_clean())->show();
