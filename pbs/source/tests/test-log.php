<?php
require_once __DIR__.'/../modules/pbs_tasks/includes/I18n.php';
require __DIR__.'/../modules/pbs_tasks/includes/LogReader.php';
use Modules\PbsTasks\Includes\LogReader;
function check($ok, $message) { if (!$ok) { throw new Exception($message); } }
$upid = 'UPID:pbs:000000A3:0DB91DCA:00000003:6ABE1E85:reader:disk\\x2dbackup:backup@pbs!pve-backup:';
LogReader::validateTask($upid, 1);
foreach ([['bad',1],[$upid,0],[$upid."\n",1]] as [$id,$offset]) {
    try { LogReader::validateTask($id, $offset); throw new Exception('Invalid request accepted'); } catch (InvalidArgumentException $e) {}
}
$cfg = ['url' => 'https://pbs.example:8007/', 'token_id' => 'monitor@pbs!zabbix', 'token_secret' => 'fake-test-secret'];
check(LogReader::validateConnection($cfg)['url'] === 'https://pbs.example:8007', 'Normalize base URL');
foreach (['http://pbs.example','https://user@pbs.example','https://pbs.example/path','https://pbs.example?secret=x'] as $url) {
    try { LogReader::validateConnection(array_replace($cfg, ['url' => $url])); throw new Exception('Invalid URL accepted'); } catch (RuntimeException $e) {}
}
try { LogReader::validateConnection(array_replace($cfg, ['token_secret' => "x\r\nInjected: y"])); throw new Exception('Header injection accepted'); } catch (RuntimeException $e) {}
$page = LogReader::decode(json_encode(['data' => [['n' => 1, 't' => '<script>text</script>'], ['n' => 2, 't' => 'TASK OK']], 'total' => 3, 'active' => true]), 1);
check($page['next'] === 3 && $page['more'] && $page['active'], 'Next page starts at last line plus one');
$end = LogReader::decode('{"data":[{"n":3,"t":"end"}],"total":3}', $page['next']);
check(!$end['more'] && $end['next'] === 4, 'Final page');
check(!LogReader::decode('{"data":[],"total":0}',1)['more'], 'Empty log');
foreach (['{}','{"data":[{"n":0,"t":"bad"}],"total":1}','{"data":[{"n":1,"t":4}],"total":1}'] as $bad) {
    try { LogReader::decode($bad,1); throw new Exception('Bad response accepted'); } catch (RuntimeException $e) {}
}
echo "Log validation passed: ID, URL, header injection, paging, empty and malformed responses.\n";
