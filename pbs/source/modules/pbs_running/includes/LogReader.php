<?php
namespace Modules\PbsRunning\Includes;
class LogReader {
    public const PAGE_SIZE = 500;
    public const MAX_BYTES = 2097152;
    public static function validateTask(string $upid, int $start): void {
        if (strlen($upid) > 2048 || !preg_match('/\AUPID:[^:\s\/]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[0-9A-Fa-f]+:[^:\s\/]+:[^:\r\n]*:[^:\s]+:\z/D', $upid) || $start < 1 || $start > 2147483000) {
            throw new \InvalidArgumentException('Ungültige Task-ID oder Zeilennummer.');
        }
    }
    public static function connection(string $hostid): array {
        $path = '/etc/zabbix/pbs-dashboard.json';
        if (!is_readable($path)) { throw new \RuntimeException('PBS-Log-Verbindung noch nicht eingerichtet. Einrichtung siehe README.md im Paket.'); }
        $raw = @file_get_contents($path);
        $all = json_decode($raw ?: '', true);
        if (!is_array($all) || !isset($all['hosts'][$hostid]) || !is_array($all['hosts'][$hostid])) { throw new \RuntimeException('Für diesen Zabbix-Host ist keine PBS-Log-Verbindung eingerichtet.'); }
        return self::validateConnection($all['hosts'][$hostid]);
    }
    public static function validateConnection(array $config): array {
        $url = is_string($config['url'] ?? null) ? parse_url($config['url']) : false;
        if (!$url || ($url['scheme'] ?? '') !== 'https' || empty($url['host']) || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['path'] ?? '', ['', '/'], true)) { throw new \RuntimeException('PBS-Konfiguration: HTTPS-Basisadresse ohne Pfad oder Zugangsdaten erforderlich.'); }
        foreach (['token_id','token_secret'] as $key) {
            if (!is_string($config[$key] ?? null) || $config[$key] === '' || preg_match('/[\r\n\x00]/', $config[$key])) { throw new \RuntimeException('PBS-Konfiguration: Token-ID oder Secret fehlt/ist ungültig.'); }
        }
        if (isset($config['ca_file']) && (!is_string($config['ca_file']) || !is_readable($config['ca_file']))) { throw new \RuntimeException('PBS-Konfiguration: CA-Datei ist nicht lesbar.'); }
        $config['url'] = rtrim($config['url'], '/');
        return $config;
    }
    public static function decode(string $body, int $start): array {
        $result = json_decode($body, true);
        if (!is_array($result) || !isset($result['data'], $result['total']) || !is_array($result['data']) || !is_int($result['total']) || $result['total'] < 0 || count($result['data']) > self::PAGE_SIZE) { throw new \RuntimeException('PBS lieferte keine gültigen Log-Daten.'); }
        $lines = []; $last = $start - 1;
        foreach ($result['data'] as $line) {
            if (!is_array($line) || !is_int($line['n'] ?? null) || $line['n'] <= $last || $line['n'] > $result['total'] || !is_string($line['t'] ?? null)) { throw new \RuntimeException('PBS lieferte ungültige Log-Zeilen.'); }
            $last = $line['n']; $lines[] = ['n' => $last, 't' => $line['t']];
        }
        return ['lines' => $lines, 'total' => $result['total'], 'next' => $last + 1, 'more' => (bool) $lines && $last < $result['total'], 'active' => (bool) ($result['active'] ?? false)];
    }
    public function read(array $config, string $upid, int $start): array {
        self::validateTask($upid, $start);
        $config = self::validateConnection($config);
        if (!function_exists('curl_init')) { throw new \RuntimeException('Die PHP-cURL-Erweiterung fehlt im Zabbix-Webfrontend.'); }
        $url = $config['url'].'/api2/json/nodes/localhost/tasks/'.rawurlencode($upid).'/log?start='.$start.'&limit='.self::PAGE_SIZE.'&test-status=true';
        $curl = curl_init($url); $body = ''; $tooLarge = false;
        curl_setopt_array($curl, [CURLOPT_HTTPHEADER => ['Authorization: PBSAPIToken='.$config['token_id'].':'.$config['token_secret']], CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_WRITEFUNCTION => static function($handle, $chunk) use (&$body, &$tooLarge) {
            if (strlen($body) + strlen($chunk) > self::MAX_BYTES) { $tooLarge = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        }]);
        if (!empty($config['ca_file'])) { curl_setopt($curl, CURLOPT_CAINFO, $config['ca_file']); }
        $ok = curl_exec($curl); $code = curl_getinfo($curl, CURLINFO_HTTP_CODE); $error = curl_errno($curl); curl_close($curl);
        if ($tooLarge) { throw new \RuntimeException('Log-Seite überschreitet 2 MiB. Bitte das Log direkt in PBS öffnen.'); }
        if ($ok === false) { throw new \RuntimeException('PBS nicht erreichbar oder TLS-Prüfung fehlgeschlagen (cURL '.$error.').'); }
        if ($code !== 200) { throw new \RuntimeException('PBS-Log nicht verfügbar (HTTP '.$code.'). Berechtigungen und PBS-Log-Aufbewahrung prüfen.'); }
        return self::decode($body, $start);
    }
}
