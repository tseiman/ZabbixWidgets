<?php
namespace Modules\UniFiTopology;
use Zabbix\Core\CWidget;
class Widget extends CWidget {
    public function getDefaultName(): string {
        return _('UniFi Topologie');
    }
}
