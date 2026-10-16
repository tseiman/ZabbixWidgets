<?php
require_once __DIR__.'/../modules/pbs_summary/includes/I18n.php';
require __DIR__.'/../modules/pbs_summary/includes/Data.php';
require __DIR__.'/../modules/pbs_summary/includes/TaskFilter.php';
use Modules\PbsSummary\Includes\Data;
function check($ok, $message) { if (!$ok) { throw new Exception($message); } }
class API {
    public static array $items = [];
    public static array $rows = [];
    public static function Item() { return new class { public function get($params) { return API::$items; } }; }
    public static function History() { return new class { public function get($params) {
        $rows = array_values(array_filter(API::$rows, static function($r) use ($params) { return in_array($r['itemid'], $params['itemids']) && $r['clock'] <= ($params['time_till'] ?? PHP_INT_MAX) && $r['clock'] >= ($params['time_from'] ?? 0); }));
        usort($rows, static fn($a, $b) => $b['clock'] <=> $a['clock']);
        return array_slice($rows, 0, $params['limit'] ?? count($rows));
    } }; }
}
$now = time();
for ($i = 0; $i < 16; $i++) { API::$items[] = ['itemid' => (string) ($i + 1), 'key_' => 'pbs.dashboard.tasks.completed['.$i.']', 'value_type' => 4, 'state' => 0, 'status' => 0]; }
API::$items[] = ['itemid' => '17', 'key_' => 'pbs.dashboard.tasks.running', 'value_type' => 4, 'state' => 0, 'status' => 0];
$make = static fn($id, $end) => ['upid' => $id, 'worker_type' => 'backup', 'worker_id' => 'store:vm/111', 'starttime' => $end - 400000, 'endtime' => $end, 'status' => 'OK'];
$a = $make('a', $now - 7200);
$b = $make('b', $now - 170000);
$c = $make('c', $now - 260000);
foreach ([[$now, [$a, $b]], [$now - 169200, [$b, $c]]] as [$until, $tasks]) {
    for ($i = 0; $i < 16; $i++) { API::$rows[] = ['itemid' => (string) ($i + 1), 'clock' => $until, 'ns' => 0, 'value' => json_encode(['version' => 1, 'since' => $until - 172800, 'until' => $until, 'tasks' => $i === 0 ? $tasks : []])]; }
}
$reader = new Data('1');
$result = $reader->tasks($now - 270000, $now);
check(count($result) === 3, 'Deduplication across overlapping snapshots');
check(count($reader->warnings) === 0, 'No false coverage gaps');
check(count((new Data('1'))->tasks($now - 170000, $now - 170000)) === 1, 'Inclusive completion boundary');
$reader = new Data('1'); $reader->tasks($now - 500000, $now);
check(count($reader->warnings) > 0, 'Missing historical coverage is visible');
$saved = API::$rows; array_pop(API::$rows);
try { (new Data('1'))->tasks($now - 270000, $now); throw new Exception('Missing shard accepted'); } catch (RuntimeException $e) { check(strpos($e->getMessage(), 'incomplete') !== false, 'Missing shard is rejected'); }
API::$rows = $saved;
API::$rows[] = ['itemid' => '17', 'clock' => $now - 200, 'ns' => 0, 'value' => json_encode(['until' => $now - 200, 'tasks' => []])];
try { (new Data('1'))->running(); throw new Exception('Stale running result accepted'); } catch (RuntimeException $e) { check(strpos($e->getMessage(), 'stale') !== false, 'Stale running is rejected'); }
check(Data::status('WARNINGS: 2') === 'warning', 'PBS warning status');
check(Data::status('OK') === 'ok' && Data::status('aborted') === 'error' && Data::status('unknown') === 'unknown', 'Statuses');
check(Data::category('verification') === 'Verify', 'Verify variants');
check(Data::label($a) === 'Datastore store - Backup vm/111', 'PBS-style label');
class CWidgetView {
    private $body;
    public function __construct($data) {}
    public function addItem($body) { $this->body = $body; return $this; }
    public function show() { echo $this->body; }
}
$a['worker_id'] = '<script>alert(1)</script>';
$data = ['error' => null, 'host' => '<PBS>', 'period' => 'Test', 'warnings' => [], 'rows' => [$a], 'running' => [['worker_type' => 'backup']], 'mode' => 'summary'];
ob_start(); require __DIR__.'/../modules/pbs_summary/views/widget.view.php'; $html = ob_get_clean();
check(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'Task data escaped');
check(strpos($html, 'data-pbs-tasks=') !== false, 'Task dialog payload present');
check(strpos($html, 'Warning / Running') === false, 'Running tasks excluded from summary');
echo "PHP tests passed: coverage, deduplication, boundaries, incomplete shards, staleness, status, escaping, popup payload.\n";
$render = static function($data) { ob_start(); require __DIR__.'/../modules/pbs_summary/views/widget.view.php'; return ob_get_clean(); };
$data['warnings'] = ['The time period is only partially captured. Test'];
$html = $render($data);
check(strpos($html, 'class="pbs-notice"') === false, 'No warning banners for coverage');
check(strpos($html, '</table>') < strpos($html, '<details class="pbs-health">'), 'Coverage details appear after table');
check(strpos($html, 'Time period partially captured') !== false, 'Partial coverage remains visible when collapsed');
$data['mode'] = 'running'; $data['rows'] = []; $data['warnings'] = [];
$html = $render($data);
check(strpos($html, 'No running tasks.') !== false && strpos($html, 'pbs-notice') === false, 'Successful empty running list is neutral');
$data['error'] = 'No stored values: pbs.dashboard.tasks.running'; $data['waiting'] = true;
$html = $render($data);
check(strpos($html, 'No stored values:') !== false && strpos($html, 'No running tasks.') === false && strpos($html, 'pbs-notice') !== false, 'Missing measurement keeps warning as requested');
echo "UI regression passed: compact footer, neutral empty state, missing-data warning preserved.\n";
