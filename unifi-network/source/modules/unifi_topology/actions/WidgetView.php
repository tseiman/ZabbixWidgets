<?php
namespace Modules\UniFiTopology\Actions;
use CControllerDashboardWidgetView;
use CControllerResponseData;
use Modules\UniFiTopology\Includes\I18n;
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
                : I18n::t('The topology could not be loaded. Check the module version and Zabbix frontend log.');
            if (!($e instanceof \InvalidArgumentException) && !($e instanceof \RuntimeException)) {
                error_log('UniFi Topology: '.get_class($e).': '.$e->getMessage());
            }
        }
        $this->setResponse(new CControllerResponseData([
            'name' => $this->getInput('name', $this->widget->getName()),
            'topology' => $topology,
            'topology_error' => $error,
            'traffic_graph' => [
                'action' => 'widget.unifi_topology.traffic',
                'topology_itemid' => (string) reset($this->fields_values['itemid']),
                'time_period' => $this->fields_values['time_period']
            ],
            'locale' => I18n::locale(),
            'user' => ['debug_mode' => $this->getDebugMode()]
        ]));
    }
}
