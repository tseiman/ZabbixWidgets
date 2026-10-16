<?php
namespace Modules\UniFiTopology\Actions;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\UniFiTopology\Includes\Topology;

class WidgetView extends CControllerDashboardWidgetView {
    protected function doAction(): void {
        $topology = null;
        $error = null;
        try {
            $topology = (new Topology())->load($this->fields_values);
        }
        catch (\Throwable $e) {
            $error = $e instanceof \InvalidArgumentException || $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Topologie konnte nicht geladen werden. Modulversion und Zabbix-Frontend-Protokoll pruefen.';
            if (!($e instanceof \InvalidArgumentException) && !($e instanceof \RuntimeException)) {
                error_log('UniFi Topology: '.get_class($e).': '.$e->getMessage());
            }
        }
        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'topology' => $topology,
            'topology_error' => $error,
            'user' => ['debug_mode' => $this->getDebugMode()]
        ]));
    }
}
