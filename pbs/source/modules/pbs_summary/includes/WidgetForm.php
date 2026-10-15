<?php
namespace Modules\PbsSummary\Includes;
use Zabbix\Widgets\{CWidgetForm, CWidgetField};
use Zabbix\Widgets\Fields\{CWidgetFieldMultiSelectHost, CWidgetFieldTimePeriod, CWidgetFieldTextBox};
class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        $this->addField((new CWidgetFieldMultiSelectHost('hostid', _('PBS host')))->setMultiple(false)->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK));
        $this->addField((new CWidgetFieldTimePeriod('time_period', _('Time period')))->setDefault([CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(CWidgetField::REFERENCE_DASHBOARD, \CWidgetsData::DATA_TYPE_TIME_PERIOD)])->setDefaultPeriod(['from' => 'now-30d', 'to' => 'now'])->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK));
        $this->addField((new CWidgetFieldTextBox('exclude_expression', _('Tasks ausschließen (Expression)')))->setDefault(TaskFilter::DEFAULT_EXPRESSION)->setMaxLength(TaskFilter::MAX_LENGTH));
        return $this;
    }
    public function validate(bool $strict = false): array {
        $errors = parent::validate($strict);
        if (!$errors) {
            try { new TaskFilter($this->getFieldValue('exclude_expression')); }
            catch (\InvalidArgumentException $e) { $errors[] = 'Tasks ausschließen: '.$e->getMessage(); }
        }
        return $errors;
    }
}
