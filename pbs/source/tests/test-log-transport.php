<?php
namespace {
    if (!function_exists('curl_init')) { function curl_init() {} }
    foreach (['CURLOPT_HTTPHEADER','CURLOPT_CONNECTTIMEOUT','CURLOPT_TIMEOUT','CURLOPT_FOLLOWLOCATION','CURLOPT_SSL_VERIFYPEER','CURLOPT_SSL_VERIFYHOST','CURLOPT_PROTOCOLS','CURLOPT_WRITEFUNCTION','CURLOPT_CAINFO','CURLPROTO_HTTPS','CURLINFO_HTTP_CODE'] as $i => $name) { if (!defined($name)) { define($name, $i + 1); } }
}
namespace Modules\PbsTasks\Includes {
    class FakeCurl { public static string $url; public static array $options; public static int $status = 200; public static string $body = '{"data":[{"n":501,"t":"hello"}],"total":501}'; }
    function curl_init($url) { FakeCurl::$url = $url; return 1; }
    function curl_setopt_array($handle,$options) { FakeCurl::$options = $options; }
    function curl_setopt($handle,$key,$value) { FakeCurl::$options[$key] = $value; }
    function curl_exec($handle) { return (FakeCurl::$options[CURLOPT_WRITEFUNCTION])(1,FakeCurl::$body) > 0; }
    function curl_getinfo($handle,$key) { return FakeCurl::$status; }
    function curl_errno($handle) { return 0; }
    function curl_close($handle) {}
}
namespace {
    require __DIR__.'/../modules/pbs_tasks/includes/LogReader.php';
    use Modules\PbsTasks\Includes\{LogReader,FakeCurl};
    function check($ok,$message) { if (!$ok) { throw new \Exception($message); } }
    $cfg = ['url' => 'https://pbs.example:8007', 'token_id' => 'monitor@pbs!zabbix', 'token_secret' => 'test-secret'];
    $upid = 'UPID:pbs:000000A3:0DB91DCA:00000003:6ABE1E85:reader:disk\\x2dbackup:backup@pbs!pve-backup:';
    $reader = new LogReader(); $result = $reader->read($cfg,$upid,501);
    check(strpos(FakeCurl::$url,rawurlencode($upid).'/log?start=501&limit=500&test-status=true') !== false, 'Encoded task and paging request');
    check(FakeCurl::$options[CURLOPT_SSL_VERIFYPEER] && FakeCurl::$options[CURLOPT_SSL_VERIFYHOST] === 2 && !FakeCurl::$options[CURLOPT_FOLLOWLOCATION], 'TLS validation and no redirects');
    check($result['lines'][0]['n'] === 501 && !$result['more'], 'Decoded transport result');
    foreach ([401,403,404,500] as $status) {
        FakeCurl::$status = $status;
        try { $reader->read($cfg,$upid,1); throw new \Exception('HTTP error accepted'); } catch (\RuntimeException $e) { check(strpos($e->getMessage(),'test-secret') === false, 'No secret in error'); }
    }
    FakeCurl::$status = 200; FakeCurl::$body = str_repeat('x',LogReader::MAX_BYTES + 1);
    try { $reader->read($cfg,$upid,1); throw new \Exception('Oversized response accepted'); } catch (\RuntimeException $e) { check(strpos($e->getMessage(),'2 MiB') !== false,'Response bound'); }
    echo "Log transport passed with mock cURL: encoded URL, paging, TLS, HTTP failures, response size bound.\n";
}
