<?php
namespace Modules\PbsTasks\Includes;
class TaskList {
    public static function sort(array &$rows): void {
        usort($rows, static fn($a, $b) => ($b['starttime'] <=> $a['starttime']) ?: (($b['endtime'] <=> $a['endtime']) ?: strcmp($a['upid'], $b['upid'])));
    }
    public static function user(array $task): string {
        if (!empty($task['user'])) { return $task['user']; }
        // PBS encodes the auth ID as the last non-empty UPID component.
        $parts = explode(':', $task['upid'] ?? '');
        return count($parts) === 10 && $parts[0] === 'UPID' && $parts[9] === '' ? $parts[8] : '—';
    }
    public static function label(array $task): string {
        $id = preg_replace_callback('/\\\\x([0-9a-fA-F]{2})/', static fn($m) => chr(hexdec($m[1])), $task['worker_id'] ?? '');
        $parts = explode(':', $id, 2);
        if ($task['worker_type'] === 'reader' && count($parts) === 2) { return I18n::tf('Datastore %s Read Objects %s', $parts[0], $parts[1]); }
        if ($task['worker_type'] === 'prune') { return I18n::tf('Prune on Datastore %s', $id); }
        if ($task['worker_type'] === 'aptupdate') { return I18n::t('Update package database'); }
        return Data::label($task);
    }
}
