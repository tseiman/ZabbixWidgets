<?php
namespace Modules\PbsLongest\Actions;
use Modules\PbsLongest\Includes\LogReader;
class TaskLog extends \CController {
    protected function init(): void {
        $this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
        // This endpoint only reads logs; no PBS mutation is implemented.
        $this->disableCsrfValidation();
    }
    private function respond(array $payload): void {
        $this->setResponse((new \CControllerResponseData(['main_block' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)]))->disableView());
    }
    protected function checkInput(): bool {
        $ok = $this->validateInput(['hostid' => 'required|db hosts.hostid', 'upid' => 'required|string', 'start' => 'int32']);
        if ($ok) {
            try { LogReader::validateTask($this->getInput('upid'), (int) $this->getInput('start', 1)); }
            catch (\InvalidArgumentException $e) { $ok = false; }
        }
        if (!$ok) { $this->respond(['error' => ['messages' => ['Ungültige Log-Anfrage.']]]); }
        return $ok;
    }
    protected function checkPermissions(): bool {
        if ($this->getUserType() < USER_TYPE_ZABBIX_USER) { return false; }
        $hostid = $this->getInput('hostid');
        if (!\API::Host()->get(['hostids' => [$hostid], 'output' => ['hostid']])) { return false; }
        // Use the same session-bound item permissions as the task widgets.
        return (bool) \API::Item()->get(['hostids' => [$hostid], 'output' => ['itemid'], 'filter' => ['key_' => ['pbs.dashboard.tasks.completed[0]', 'pbs.dashboard.tasks.running']], 'webitems' => true]);
    }
    protected function doAction(): void {
        try {
            $config = LogReader::connection((string) $this->getInput('hostid'));
            $result = (new LogReader())->read($config, $this->getInput('upid'), (int) $this->getInput('start', 1));
            $this->respond($result);
        }
        catch (\RuntimeException $e) { $this->respond(['error' => ['messages' => [$e->getMessage()]]]); }
        catch (\Throwable $e) { $this->respond(['error' => ['messages' => ['Log konnte nicht gelesen werden. Serverkonfiguration prüfen.']]]); }
    }
}
