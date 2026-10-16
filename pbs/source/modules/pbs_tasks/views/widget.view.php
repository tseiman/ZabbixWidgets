<?php
use Modules\PbsTasks\Includes\Data;
use Modules\PbsTasks\Includes\I18n;
use Modules\PbsTasks\Includes\TaskList;

$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$icon = static function($state, $title, $zero = false) use ($e) {
    return '<span class="pbs-icon pbs-'.($zero ? 'zero' : $state).'" title="'.$e($title).'" aria-label="'.$e($title).'">'.(['ok' => '✓', 'error' => '×', 'warning' => '!', 'unknown' => '?'][$state] ?? '?').'</span>';
};

ob_start();
echo '<div class="pbs-panel" data-pbs-locale="'.$e($data['locale'] ?? I18n::locale()).'" data-pbs-hostid="'.$e($data['hostid'] ?? '').'" data-pbs-log-action="widget.pbs_tasks.log">';
if ($data['error'] !== null) {
    echo '<div class="pbs-notice">'.$e($data['error']).'</div>';
}
else {
    echo '<div class="pbs-meta">'.$e($data['host']).' · '.$e($data['period']).' · '.$e(I18n::t('Newest start time first')).'</div>';
    $rows = $data['rows'];
    TaskList::sort($rows);
    $shown = array_slice($rows, 0, $data['limit']);
    if (!$shown) {
        echo '<p class="pbs-empty">'.$e(I18n::t('No completed tasks in the available time period.')).'</p>';
    }
    else {
        echo '<table class="pbs-table pbs-chronological"><thead><tr>';
        foreach (['Start time ↓', 'End time', 'User name', 'Description', 'Status'] as $heading) {
            echo '<th>'.$e(I18n::t($heading)).'</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($shown as $task) {
            $task['label'] = TaskList::label($task);
            $task['start_label'] = I18n::dateTime((int) $task['starttime'], true);
            $state = Data::status($task['status'] ?? '');
            $status = $task['status'] ?? I18n::t('Unknown');
            echo '<tr class="pbs-row-'.$e($state).'"><td>'.$e($task['start_label']).'</td><td>'.$e(I18n::dateTime((int) $task['endtime'], true)).'</td><td>'.$e(TaskList::user($task)).'</td><td><button type="button" class="pbs-task-button" data-pbs-title="'.$e($task['label']).'" data-pbs-tasks="'.$e(json_encode([$task], JSON_THROW_ON_ERROR)).'" title="'.$e(I18n::t('Open task log')).'">'.$e($task['label']).'</button></td><td>'.$icon($state, $status).' '.$e($status).'</td></tr>';
        }
        echo '</tbody></table><div class="pbs-meta">'.$e(I18n::tf('%d of %d tasks · Change the row count in the widget settings.', count($shown), count($rows))).'</div>';
    }
    $notes = array_values(array_unique($data['warnings']));
    if ($notes) {
        $partial = count(array_filter($notes, static fn($note) => strpos($note, I18n::t('The time period is only partially captured.')) !== false || strpos($note, strtok(I18n::t('Data gap until %s; statistics are incomplete.'), '%')) !== false)) > 0;
        $summary = $partial ? I18n::t('Time period partially captured') : I18n::t('Data notes');
        echo '<details class="pbs-health"><summary>ⓘ '.$e($summary).(!empty($data['updated']) ? ' · '.$e(I18n::tf('Updated %s', date('H:i', $data['updated']))) : '').'</summary>';
        foreach ($notes as $note) { echo '<p>'.$e($note).'</p>'; }
        echo '</details>';
    }
    elseif (!empty($data['updated'])) {
        echo '<div class="pbs-health">'.$e(I18n::tf('Updated %s', date('H:i', $data['updated']))).'</div>';
    }
}
echo '</div>';
(new CWidgetView($data))->addItem(ob_get_clean())->show();
