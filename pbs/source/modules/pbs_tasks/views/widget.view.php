<?php
use Modules\PbsTasks\Includes\Data;
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
echo '<div class="pbs-panel" data-pbs-hostid="'.$e($data['hostid'] ?? '').'" data-pbs-log-action="widget.pbs_tasks.log">';
if ($data['error'] !== null) { echo '<div class="pbs-notice">'.$e($data['error']).'</div>'; }
else {
    echo '<div class="pbs-meta">'.$e($data['host']).' · '.$e($data['period']).' · Neueste Startzeit zuerst</div>';
    $rows = $data['rows'];
    \Modules\PbsTasks\Includes\TaskList::sort($rows);
    $shown = array_slice($rows, 0, $data['limit']);
    if (!$shown) { echo '<p class="pbs-empty">Keine abgeschlossenen Tasks im verfügbaren Zeitraum.</p>'; }
    else {
        echo '<table class="pbs-table pbs-chronological"><thead><tr><th>Start Time ↓</th><th>End Time</th><th>User name</th><th>Description</th><th>Status</th></tr></thead><tbody>';
        foreach ($shown as $task) {
            $task['label'] = \Modules\PbsTasks\Includes\TaskList::label($task);
            $task['start_label'] = date('Y-m-d H:i:s', $task['starttime']);
            $state = Data::status($task['status'] ?? '');
            echo '<tr class="pbs-row-'.$e($state).'"><td>'.$e($task['start_label']).'</td><td>'.$e(date('Y-m-d H:i:s', $task['endtime'])).'</td><td>'.$e(\Modules\PbsTasks\Includes\TaskList::user($task)).'</td><td><button type="button" class="pbs-task-button" data-pbs-title="'.$e($task['label']).'" data-pbs-tasks="'.$e(json_encode([$task], JSON_THROW_ON_ERROR)).'" title="Task-Log öffnen">'.$e($task['label']).'</button></td><td>'.$icon($state, $task['status'] ?? 'Unbekannt').' '.$e($task['status'] ?? 'Unbekannt').'</td></tr>';
        }
        echo '</tbody></table><div class="pbs-meta">'.count($shown).' von '.count($rows).' Tasks · Zeilenanzahl in den Widget-Einstellungen ändern.</div>';
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
