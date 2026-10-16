<?php
require_once __DIR__.'/../modules/pbs_tasks/includes/I18n.php';
require __DIR__.'/test.php';
require __DIR__.'/../modules/pbs_tasks/includes/Data.php';
require __DIR__.'/../modules/pbs_tasks/includes/TaskList.php';
use Modules\PbsTasks\Includes\TaskList;
$upid = 'UPID:pbs:000000A3:0DB91DCA:00000003:6ABE1E85:reader:disk\\x2dbackup:backup@pbs!pve-backup:';
check(TaskList::user(['upid' => $upid]) === 'backup@pbs!pve-backup', 'User from existing PBS UPID');
check(TaskList::user(['upid' => 'bad']) === '—', 'Unknown user fallback');
check(TaskList::user(['upid' => $upid, 'user' => 'root@pam']) === 'root@pam', 'Explicit user');
$rows = [
    ['upid' => 'old', 'starttime' => 100, 'endtime' => 900, 'worker_type' => 'backup', 'worker_id' => 'store:vm/100', 'status' => 'OK'],
    ['upid' => $upid, 'starttime' => 300, 'endtime' => 320, 'worker_type' => 'reader', 'worker_id' => 'store:host/test', 'status' => 'connection error: not connected'],
    ['upid' => 'middle', 'starttime' => 200, 'endtime' => 800, 'worker_type' => 'backup', 'worker_id' => '<script>bad</script>', 'status' => 'failed']
];
TaskList::sort($rows);
check(array_column($rows, 'starttime') === [300, 200, 100], 'Sort by start, not duration or completion');
$data = ['mode' => 'tasks', 'error' => null, 'host' => 'PBS', 'hostid' => '123', 'rows' => $rows, 'period' => 'Test', 'limit' => 2, 'warnings' => []];
$renderList = static function($data) { ob_start(); require __DIR__.'/../modules/pbs_tasks/views/widget.view.php'; return ob_get_clean(); };
$html = $renderList($data);
check(strpos($html, '2 of 3 tasks') !== false, 'Explicit visible limit');
check(strpos($html, 'Read Objects') !== false && strpos($html, 'connection error: not connected') !== false, 'Summary filter does not affect new list');
check(strpos($html, 'backup@pbs!pve-backup') !== false, 'User column');
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Task labels escaped');
check(strpos($html, 'data-pbs-hostid="123"') !== false, 'Host for on-demand log');
check(strpos($html, 'widget.pbs_tasks.log') !== false, 'Own log endpoint');
echo "Task list passed: ordering, user, limit, escaping, no Summary filter, log context.\n";
