<?php
namespace Modules\PbsRunning\Includes;
use Zabbix\Widgets\{CWidgetForm, CWidgetField};
use Zabbix\Widgets\Fields\{CWidgetFieldMultiSelectHost, CWidgetFieldTimePeriod, CWidgetFieldIntegerBox};
class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        $this->addField((new CWidgetFieldMultiSelectHost('hostid', _('PBS host')))->setMultiple(false)->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK));
        
        
        return $this;
    }
}
