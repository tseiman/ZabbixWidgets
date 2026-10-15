<?php
use Modules\PbsSummary\Includes\Data;
require __DIR__.'/test.php';
$cases = [
    ['reader', 'connection error: not connected', null, null],
    ['reader', 'connection error: unexpected EOF', 'Other', 'Failed'],
    ['reader', 'another failure', 'Other', 'Failed'],
    ['reader', 'OK', 'Other', 'OK'],
    ['reader', 'WARNINGS: 2', 'Other', 'Warning'],
    ['reader', null, 'Unknown', null],
    ['Reader', 'connection error: not connected', 'Other', 'Failed'],
    ['reader-extra', 'connection error: not connected', 'Other', 'Failed'],
    ['reader', 'connection error: not connected ', 'Other', 'Failed'],
    ['reader', 'Connection error: not connected', 'Other', 'Failed'],
    ['backup', 'connection error: not connected', 'Backups', 'Failed']
];
foreach (['backup' => 'Backups', 'prune' => 'Prunes', 'garbage_collection' => 'Garbage collections', 'sync' => 'Syncs', 'verifyjob' => 'Verify', 'tape-backup' => 'Tape Backup', 'tape-restore' => 'Tape Restore'] as $type => $category) {
    foreach (['OK' => 'OK', 'WARNINGS: 1' => 'Warning', 'failed' => 'Failed'] as $status => $state) { $cases[] = [$type, $status, $category, $state]; }
}
$tasks = [];
$expected = [];
foreach ($cases as $index => [$type, $status, $category, $state]) {
    $task = ['upid' => 'test-'.$index, 'worker_type' => $type, 'worker_id' => 'test', 'starttime' => $now - 50, 'endtime' => $now - 10];
    if ($status !== null) { $task['status'] = $status; }
    $tasks[] = $task;
    if ($category !== null) { $expected[$state === null ? $category : $category.' · '.$state][] = $task['upid']; }
}
// Exercise the unchanged history/time selection before rendering the actual view.
API::$rows = [];
$outside = $tasks[1]; $outside['upid'] = 'outside'; $outside['endtime'] = $now - 101;
for ($i = 0; $i < 16; $i++) { API::$rows[] = ['itemid' => (string) ($i + 1), 'clock' => $now, 'ns' => 0, 'value' => json_encode(['since' => $now - 1000, 'until' => $now, 'tasks' => $i === 0 ? array_merge($tasks, [$outside]) : []])]; }
$data = ['error' => null, 'host' => 'PBS', 'period' => 'Test period', 'warnings' => [], 'rows' => (new Data('1'))->tasks($now - 100, $now), 'mode' => 'summary'];
$html = $render($data);
preg_match_all('/data-pbs-title="([^"]*)" data-pbs-tasks="([^"]*)"[^>]*>.*?<\/span> (\d+)<\/button>/s', $html, $buttons, PREG_SET_ORDER);
check(count($buttons) === 25, 'All category/status buttons plus unknown remain present');
foreach ($buttons as $button) {
    $title = html_entity_decode($button[1], ENT_QUOTES, 'UTF-8');
    $payload = json_decode(html_entity_decode($button[2], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    check(array_column($payload, 'upid') === ($expected[$title] ?? []), 'Exact task detail membership: '.$title);
    check((int) $button[3] === count($payload), 'Count matches details: '.$title);
}
check(strpos($html, 'test-0&quot;') === false && strpos($html, 'outside') === false, 'Excluded reader and out-of-period task absent');
$data['rows'] = [$tasks[0]];
$html = $render($data);
check(strpos($html, '<td>Other</td>') === false, 'Excluded-only input does not create Other row');
echo "Summary filter regression passed: exact match, EOF, near matches, categories, counts, details and dashboard period.\n";
$data['task_filter'] = new \Modules\PbsSummary\Includes\TaskFilter('');
$html = $render($data);
check(strpos($html, '<td>Other</td>') !== false && strpos($html, 'test-0&quot;') !== false, 'Empty expression disables the filter in counts and details');
$data['rows'] = [$tasks[0], $tasks[1]];
$data['task_filter'] = new \Modules\PbsSummary\Includes\TaskFilter("status === 'connection error: unexpected EOF'");
$html = $render($data);
check(strpos($html, 'test-0&quot;') !== false && strpos($html, 'test-1&quot;') === false, 'Custom expression replaces the default');
echo "Configurable summary rendering passed: disabled filter and custom expression.\n";
