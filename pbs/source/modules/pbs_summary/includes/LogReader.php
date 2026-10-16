<?php
namespace Modules\PbsSummary\Includes;
class LogReader {
    public const PAGE_SIZE = 500;
    public const MAX_BYTES = 2097152;
    public static function validateTask(string $upid, int $start): void {
        if (strlen($upid) > 2048 || !preg_match('/\AUPID:[^:\s\/]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[^:\s\/]+:[^:\r\n]*:[^:\s]+:\z/D', $upid) || $start < 1 || $start > 2147483000) {
            throw new \InvalidArgumentException(I18n::t('Invalid task ID or line number.'));
        }
    }
    public static function connection(string $hostid): array {
        $path = '/etc/zabbix/pbs-dashboard.json';
        if (!is_readable($path)) { throw new \RuntimeException(I18n::t('The PBS log connection is not configured yet. See the package README.md for setup.')); }
        $raw = @file_get_contents($path);
        $all = json_decode($raw ?: '', true);
        if (!is_array($all) || !isset($all['hosts'][$hostid]) || !is_array($all['hosts'][$hostid])) { throw new \RuntimeException(I18n::t('No PBS log connection is configured for this Zabbix host.')); }
        return self::validateConnection($all['hosts'][$hostid]);
    }
    public static function validateConnection(array $config): array {
        $url = is_string($config['url'] ?? null) ? parse_url($config['url']) : false;
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['path'] ?? '', ['', '/'], true)) { throw new \RuntimeException(I18n::t('PBS configuration: an HTTPS base URL without a path or credentials is required.')); }
        foreach (['token_id','token_secret'] as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '' || preg_match('/[\r\n\x00]/', $config[$key])) { throw new \RuntimeException(I18n::t('PBS configuration: token ID or secret is missing or invalid.')); }
        }
        if (isset($config['ca_file']) && (!is_string($config['ca_file']) || !is_readable($config['ca_file']))) { throw new \RuntimeException(I18n::t('PBS configuration: CA file is unreadable.')); }
        $config['url'] = rtrim($config['url'], '/');
        return $config;
    }
    public static function decode(string $body, int $start): array {
        $result = json_decode($body, true);
        if (!is_array($result) || !isset($result['data'], $result['total']) || !is_array($result['data']) || !is_int($result['total']) || $result['total'] < 0 || count($result['data']) > self::PAGE_SIZE) { throw new \RuntimeException(I18n::t('PBS returned invalid log data.')); }
        $lines = []; $last = $start - 1;
        foreach ($result['data'] as $line) {
            if (!is_array($line) || !is_int($line['n'] ?? null) || $line['n'] <= $last || $line['n'] > $result['total'] || !is_string($line['t'] ?? null)) { throw new \RuntimeException(I18n::t('PBS returned invalid log lines.')); }
            $last = $line['n']; $lines[] = ['n' => $last, 't' => $line['t']];
        }
        return ['lines' => $lines, 'total' => $result['total'], 'next' => $last + 1, 'more' => (bool) $lines && $last < $result['total'], 'active' => (bool) ($result['active'] ?? false)];
    }
    public function read(array $config, string $upid, int $start): array {
        self::validateTask($upid, $start);
        $config = self::validateConnection($config);
        if (!function_exists('curl_init')) { throw new \RuntimeException(I18n::t('The PHP cURL extension is missing from the Zabbix frontend.')); }
        $url = $config['url'].'/api2/json/nodes/localhost/tasks/'.rawurlencode($upid).'/log?start='.$start.'&limit='.self::PAGE_SIZE.'&test-status=true';
        $curl = curl_init($url); $body = ''; $tooLarge = false;
        curl_setopt_array($curl, [CURLOPT_HTTPHEADER => ['Authorization: PBSAPIToken='.$config['token_id'].':'.$config['token_secret']], CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_WRITEFUNCTION => static function($handle, $chunk) use (&$body, &$tooLarge) {
            if (strlen($body) + strlen($chunk) > self::MAX_BYTES) { $tooLarge = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        }]);
        if (!empty($config['ca_file'])) { curl_setopt($curl, CURLOPT_CAINFO, $config['ca_file']); }
        $ok = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_errno($curl); curl_close($curl);
        if ($tooLarge) { throw new \RuntimeException(I18n::t('The log page exceeds 2 MiB. Open the log directly in PBS.')); }
        if ($ok === false) { throw new \RuntimeException(I18n::tf('PBS is unreachable or TLS verification failed (cURL %d).', $error)); }
        if ($code !== 200) { throw new \RuntimeException(I18n::tf('PBS log unavailable (HTTP %d). Check permissions and PBS log retention.', $code)); }
        return self::decode($body, $start);
    }
}
