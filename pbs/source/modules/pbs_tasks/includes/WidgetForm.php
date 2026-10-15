<?php
namespace Modules\PbsTasks\Includes;
use Zabbix\Widgets\{CWidgetForm, CWidgetField};
use Zabbix\Widgets\Fields\{CWidgetFieldMultiSelectHost, CWidgetFieldTimePeriod, CWidgetFieldIntegerBox};
class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        $this->addField((new CWidgetFieldMultiSelectHost('hostid', _('PBS host')))->setMultiple(false)->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK));
        $this->addField((new CWidgetFieldTimePeriod('time_period', _('Time period')))->setDefault([CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(CWidgetField::REFERENCE_DASHBOARD, \CWidgetsData::DATA_TYPE_TIME_PERIOD)])->setDefaultPeriod(['from' => 'now-30d', 'to' => 'now'])->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK));
        $this->addField((new CWidgetFieldIntegerBox('limit', _('Rows'), 1, 1000))->setDefault(50));
        return $this;
    }
}
