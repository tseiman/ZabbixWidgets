<?php
require_once __DIR__.'/../modules/pbs_summary/includes/TaskFilter.php';
use Modules\PbsSummary\Includes\TaskFilter;
function verify($ok, $message) { if (!$ok) { throw new Exception($message); } }
$task = ['worker_type' => 'reader', 'status' => 'connection error: not connected'];
verify((new TaskFilter(TaskFilter::DEFAULT_EXPRESSION))->matches($task), 'Default exact match');
foreach (['connection error: unexpected EOF', 'connection error: not connected ', 'Connection error: not connected', '', null] as $status) { verify(!(new TaskFilter(TaskFilter::DEFAULT_EXPRESSION))->matches(['worker_type' => 'reader', 'status' => $status]), 'Other statuses retained'); }
foreach (['Reader', 'reader-other', 'backup', '', null] as $type) { verify(!(new TaskFilter(TaskFilter::DEFAULT_EXPRESSION))->matches(['worker_type' => $type, 'status' => $task['status']]), 'Other worker types retained'); }
verify(!(new TaskFilter(''))->matches($task) && !(new TaskFilter(" \t\n"))->matches($task), 'Empty disables exclusion');
verify((new TaskFilter('worker_type === "reader"'))->matches($task), 'Double quotes');
verify((new TaskFilter("worker_type === 'reader' || status === 'OK' && node === 'pbs'"))->matches($task), 'AND precedence over OR');
verify(!(new TaskFilter("(worker_type === 'reader' || status === 'OK') && node === 'pbs'"))->matches($task), 'Parentheses');
verify((new TaskFilter("!(status === 'OK') && worker_type !== 'backup'"))->matches($task), 'Negation and inequality');
verify(!(new TaskFilter("status === ''"))->matches([]), 'Missing is not empty string');
verify((new TaskFilter("worker_id === 'a\\'b\\\\c'"))->matches(['worker_id' => "a'b\\c"]), 'Escaped quotes and backslashes');
verify((new TaskFilter('user === "ü" && node === "pbs"'))->matches(['user' => 'ü', 'node' => 'pbs']), 'UTF-8 and remaining allowed fields');
foreach (["status == 'OK'", "status = 'OK'", "unknown === 'x'", "status === 'OK' ||", "status === 'OK' &&", "(status === 'OK'", "status === 'OK')", "status === 'OK'; phpinfo()", "status === 'OK' || system('id')", "status === 'unclosed", "status === 0", "status === 'bad\\n'", "worker_type === 'reader' || invalid === 'x'", str_repeat('!', 256)] as $expression) {
    $rejected = false;
    try { new TaskFilter($expression); } catch (InvalidArgumentException $e) { $rejected = true; }
    verify($rejected, 'Reject invalid expression: '.$expression);
}
echo "Expression tests passed: defaults, exact comparisons, disable, precedence, escaping, missing fields and invalid input.\n";
