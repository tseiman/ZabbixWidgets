<?php
namespace Modules\PbsTasks\Actions;
use Modules\PbsTasks\Includes\Data;
class WidgetView extends \CControllerDashboardWidgetView {
    protected function doAction(): void {
        $data = ['name' => $this->getInput('name', $this->widget->getDefaultName()), 'mode' => 'tasks', 'rows' => [], 'running' => [], 'warnings' => [], 'error' => null, 'period' => '', 'from' => 0, 'to' => time(), 'limit' => (int) ($this->fields_values['limit'] ?? 50), 'user' => ['debug_mode' => $this->getDebugMode()]];
        try {
            $hostids = $this->fields_values['hostid'] ?? [];
            if (count($hostids) !== 1) { throw new \RuntimeException('Bitte genau einen PBS-Host auswählen.'); }
            $host = \API::Host()->get(['hostids' => $hostids, 'output' => ['hostid', 'name']]);
            if (!$host) { throw new \RuntimeException('Host nicht vorhanden oder keine Berechtigung.'); }
            $data['host'] = $host[0]['name'];
            $data['hostid'] = (string) $hostids[0];
            $reader = new Data((string) $hostids[0]);
            if ($data['mode'] !== 'running') {
                $period = $this->fields_values['time_period'];
                $parser = new \CRangeTimeParser();
                if ($parser->parse($period['from']) != \CParser::PARSE_SUCCESS) { throw new \RuntimeException('Ungültiger Zeitraum'); }
                $data['from'] = $parser->getDateTime(true)->getTimestamp();
                if ($parser->parse($period['to']) != \CParser::PARSE_SUCCESS) { throw new \RuntimeException('Ungültiger Zeitraum'); }
                $data['to'] = $parser->getDateTime(false)->getTimestamp();
                if ($data['from'] > $data['to'] || $data['to'] - $data['from'] > 90 * 86400) { throw new \RuntimeException('Bitte einen Zeitraum von maximal 90 Tagen wählen.'); }
                $data['period'] = date('d.m.Y H:i', $data['from']).' – '.date('d.m.Y H:i', $data['to']);
            }
            if ($data['mode'] === 'running') { $data['rows'] = $reader->running(); }
            elseif ($data['mode'] === 'datastore') { $data['rows'] = $reader->datastores($data['from'], $data['to']); }
            else {
                $data['rows'] = $reader->tasks($data['from'], $data['to']);
                \Modules\PbsTasks\Includes\TaskList::sort($data['rows']);
            }
            $data['warnings'] = array_values(array_unique($reader->warnings));
            $data['updated'] = $reader->updated;
        }
        catch (\Throwable $e) { $data['error'] = $e->getMessage(); $data['waiting'] = $e->getCode() === 1001; }
        $this->setResponse(new \CControllerResponseData($data));
    }
}
