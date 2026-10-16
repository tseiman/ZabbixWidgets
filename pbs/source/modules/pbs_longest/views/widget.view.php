<?php
use Modules\PbsLongest\Includes\Data;
use Modules\PbsLongest\Includes\I18n;
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$duration = static function($seconds) {
    $seconds = max(0, (int) $seconds);
    return ($seconds >= 86400 ? intdiv($seconds, 86400).'d ' : '').($seconds >= 3600 ? intdiv($seconds % 86400, 3600).'h ' : '').intdiv($seconds % 3600, 60).'m '.($seconds % 60).'s';
};
$bytes = static function($value) {
    if (!is_numeric($value)) { return '—'; }
    $i = 0;
    while ($value >= 1024 && $i < 5) { $value /= 1024; $i++; }
    return number_format((float) $value, 2, I18n::locale() === 'de' ? ',' : '.', I18n::locale() === 'de' ? '.' : ',').' '.['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'][$i];
};
$icon = static function($state, $title, $zero = false) use ($e) {
    return '<span class="pbs-icon pbs-'.($zero ? 'zero' : $state).'" title="'.$e($title).'" aria-label="'.$e($title).'">'.(['ok' => '✓', 'error' => '×', 'warning' => '!', 'unknown' => '?'][$state] ?? '?').'</span>';
};
$taskbutton = static function($state, $title, $tasks, $text) use ($e, $icon) {
    foreach ($tasks as &$task) { $task['label'] = Data::label($task); $task['start_label'] = I18n::dateTime((int) $task['starttime'], true); }
    unset($task);
    return '<button type="button" class="pbs-task-button" data-pbs-title="'.$e($title).'" data-pbs-tasks="'.$e(json_encode($tasks, JSON_THROW_ON_ERROR)).'" aria-label="'.$e($title).'">'.$icon($state, $title, count($tasks) === 0).' '.$e($text).'</button>';
};
ob_start();
echo '<div class="pbs-panel" data-pbs-locale="'.$e($data['locale'] ?? I18n::locale()).'" data-pbs-hostid="'.$e($data['hostid'] ?? '').'" data-pbs-log-action="widget.pbs_longest.log">';
if ($data['error'] !== null) { echo '<div class="pbs-notice">'.$e($data['error']).'</div>'; }
else {
    echo '<div class="pbs-meta">'.$e($data['host']).($data['period'] ? ' · '.$e($data['period']) : ' · '.$e(I18n::t('Current'))).'</div>';
    if ($data['mode'] === 'summary') {
        $counts = [];
        $groups = [];
        $categories = array_map([I18n::class, 't'], ['Backups', 'Prunes', 'Garbage collections', 'Syncs', 'Verify', 'Tape Backup', 'Tape Restore', 'Other']);
        foreach ($categories as $label) { $counts[$label] = ['error' => 0, 'warning' => 0, 'ok' => 0, 'unknown' => 0, 'running' => 0]; $groups[$label] = ['error' => [], 'warning' => [], 'ok' => []]; }
        foreach ($data['rows'] as $task) { $counts[Data::category($task['worker_type'])][Data::status($task['status'] ?? '')]++; }
        $unknown = [];
        foreach ($data['rows'] as $task) { $state = Data::status($task['status'] ?? ''); if ($state === 'unknown') { $unknown[] = $task; } else { $groups[Data::category($task['worker_type'])][$state][] = $task; } }
        if ($unknown) { echo '<div class="pbs-notice">'.$e(I18n::t('Unknown completion status (not counted as Failed/Warning/OK): ')).$taskbutton('unknown', I18n::t('Unknown'), $unknown, count($unknown)).'</div>'; }
        echo '<table class="pbs-table pbs-summary"><thead><tr><th>'.$e(I18n::t('Task')).'</th><th>'.$e(I18n::t('Failed')).'</th><th>'.$e(I18n::t('Warning')).'</th><th>OK</th></tr></thead><tbody>';
        foreach ($counts as $label => $count) {
            if ($label === I18n::t('Other') && array_sum($count) === 0) { continue; }
            echo '<tr><td>'.$e($label).'</td><td>'.$taskbutton('error', $label.' · '.I18n::t('Failed'), $groups[$label]['error'], $count['error']).'</td><td>'.$taskbutton('warning', $label.' · '.I18n::t('Warning'), $groups[$label]['warning'], $count['warning']).'</td><td>'.$taskbutton('ok', $label.' · OK', $groups[$label]['ok'], $count['ok']).'</td></tr>';
        }
        echo '</tbody></table>';
    }
    elseif ($data['mode'] === 'datastore') {
        echo '<table class="pbs-table"><thead><tr>';
        foreach (['Name', 'Size', 'Used', 'Available', 'Usage %', 'Estimated full', 'History'] as $heading) { echo '<th>'.$e(I18n::t($heading)).'</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($data['rows'] as $store) {
            echo '<tr><td>'.$e($store['store']).'</td>';
            if (!empty($store['error'])) { echo '<td colspan="6">'.$e($store['error']).'</td></tr>'; continue; }
            $pct = isset($store['used'], $store['total']) && $store['total'] > 0 ? 100 * $store['used'] / $store['total'] : null;
            $estimate = $store['estimated-full-date'] ?? null;
            $full = !is_numeric($estimate) ? '—' : ($estimate <= time() ? I18n::t('No growth predicted') : I18n::tf('in %s', $duration($estimate - time())));
            echo '<td>'.$bytes($store['total'] ?? null).'</td><td>'.$bytes($store['used'] ?? null).'</td><td>'.$bytes($store['avail'] ?? null).'</td><td>';
            if ($pct === null) { echo '—'; }
            else { echo '<div class="pbs-bar"><span style="width:'.min(100, max(0, $pct)).'%"></span><b>'.number_format($pct, 2, I18n::locale() === 'de' ? ',' : '.').'%</b></div>'; }
            echo '</td><td>'.$e($full).'</td><td>';
            if (count($store['points']) > 1) {
                $points = [];
                $max = max(1, (float) ($store['total'] ?? max(array_column($store['points'], 1))));
                foreach ($store['points'] as [$clock, $used]) { $points[] = round(120 * ($clock - $data['from']) / max(1, $data['to'] - $data['from']), 2).','.round(28 - 26 * $used / $max, 2); }
                $first = explode(',', $points[0])[0];
                $last = explode(',', $points[count($points) - 1])[0];
                echo '<svg class="pbs-spark" viewBox="0 0 120 30" role="img" aria-label="'.$e(I18n::t('Used bytes in the selected time period, scale zero to total capacity')).'"><title>'.$e(I18n::t('Used storage · scale 0 to total capacity')).'</title><polygon class="pbs-spark-area" points="'.$first.',29 '.implode(' ', $points).' '.$last.',29"/><polyline points="'.implode(' ', $points).'"/></svg>';
            }
            else { echo '—'; }
            echo '</td></tr>';
        }
        echo '</tbody></table><div class="pbs-meta">'.$e(I18n::t('Current values · History: used bytes in selected time period · Forecast by PBS.')).'</div>';
    }
    else {
        if (!$data['rows']) { echo '<p class="pbs-empty">'.$e(I18n::t($data['mode'] === 'running' ? 'No running tasks.' : 'No completed tasks in the available time period.')).'</p>'; }
        else {
            echo '<table class="pbs-table pbs-tasks"><thead><tr><th>'.$e(I18n::t('Task')).'</th>'.($data['mode'] === 'running' ? '<th>'.$e(I18n::t('Start time')).'</th>' : '').'<th>'.$e(I18n::t('Duration')).'</th><th>'.($data['mode'] === 'running' ? '' : $e(I18n::t('Status'))).'</th></tr></thead><tbody>';
            $rows = $data['mode'] === 'longest' ? array_slice($data['rows'], 0, $data['limit']) : $data['rows'];
            foreach ($rows as $task) {
                $running = $data['mode'] === 'running';
                $status = $running ? 'warning' : Data::status($task['status'] ?? '');
                $label = Data::label($task);
                $detail = $task['upid'].' | '.I18n::t('Start time').': '.I18n::dateTime((int) $task['starttime'], true).($running ? '' : ' | '.I18n::t('End: ').I18n::dateTime((int) $task['endtime'], true));
                $task['label'] = $label;
                $task['start_label'] = I18n::dateTime((int) $task['starttime'], true);
                if ($running) { $task['status'] = 'RUNNING'; }
                echo '<tr><td title="'.$e($detail).'">'.$e($label).'</td>'.($running ? '<td>'.$e(I18n::dateTime((int) $task['starttime'], true)).'</td>' : '').'<td><span'.($running ? ' data-pbs-start="'.(int) $task['starttime'].'" data-pbs-observed="'.(int) ($task['_observed_at'] ?? time()).'"' : '').'>'.$duration(($running ? time() : $task['endtime']) - $task['starttime']).'</span></td><td>'.($running ? '' : $icon($status, $task['status'] ?? I18n::t('Unknown')).' ').'<button type="button" class="pbs-task-button" data-pbs-title="'.$e($label).'" data-pbs-tasks="'.$e(json_encode([$task], JSON_THROW_ON_ERROR)).'" aria-label="'.$e(I18n::t('Open task details')).'">❯</button></td></tr>';
            }
            echo '</tbody></table>';
        }
    }
    $notes = array_values(array_unique($data['warnings']));
    if ($notes) {
        $partial = count(array_filter($notes, static fn($note) => strpos($note, I18n::t('The time period is only partially captured.')) !== false || strpos($note, strtok(I18n::t('Data gap until %s; statistics are incomplete.'), '%')) !== false)) > 0;
        $summary = $partial ? I18n::t('Time period partially captured') : I18n::t('Data notes');
        echo '<details class="pbs-health"><summary>ⓘ '.$e($summary).(!empty($data['updated']) ? ' · '.$e(I18n::tf('Updated %s', date('H:i', $data['updated']))) : '').'</summary>';
        foreach ($notes as $note) { echo '<p>'.$e($note).'</p>'; }
        echo '</details>';
    }
    elseif (!empty($data['updated'])) { echo '<div class="pbs-health">'.$e(I18n::tf('Updated %s', date('H:i', $data['updated']))).'</div>'; }
}
echo '</div>';
(new CWidgetView($data))->addItem(ob_get_clean())->show();
