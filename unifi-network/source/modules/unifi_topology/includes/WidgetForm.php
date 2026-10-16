<?php
namespace Modules\UniFiTopology\Includes;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectItem;
use Zabbix\Widgets\Fields\CWidgetFieldTextBox;
use Zabbix\Widgets\Fields\CWidgetFieldTextArea;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBox;
use Zabbix\Widgets\Fields\CWidgetFieldTimePeriod;

class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        return $this
            ->addField((new CWidgetFieldMultiSelectItem('itemid', _('Topology master item')))->setMultiple(false)->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK))
            ->addField(
                (new CWidgetFieldTimePeriod('time_period', _('Traffic time period')))
                    ->setDefault([
                        CWidgetField::FOREIGN_REFERENCE_KEY => CWidgetField::createTypedReference(
                            CWidgetField::REFERENCE_DASHBOARD, \CWidgetsData::DATA_TYPE_TIME_PERIOD
                        )
                    ])
                    ->setDefaultPeriod(['from' => 'now-1h', 'to' => 'now'])
                    ->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK)
            )
            ->addField((new CWidgetFieldTextBox('host_pattern', _('Exact Zabbix host name containing {name}')))->setDefault('UniFi Network - Site: "Default" - Device: "{name}"'))
            ->addField(new CWidgetFieldTextArea('bindings', _('Optional mapping: MAC=host ID, one per line')))
            ->addField(new CWidgetFieldTextArea('manual_nodes', _('Additional devices: ID | name | optional host ID')))
            ->addField(new CWidgetFieldTextArea('manual_links', _('Additional links: from | to | label')))
            ->addField((new CWidgetFieldIntegerBox('max_age', _('Maximum topology data age (seconds)'), 60, 86400))->setDefault(300))
            ->addField((new CWidgetFieldCheckBox('show_suppressed', _('Include suppressed problems')))->setDefault(0));
    }
    public function validate(bool $strict = false): array {
        $errors = parent::validate($strict);
        try {
            ManualTopology::parse($this->getFieldValue('manual_nodes') ?? '', $this->getFieldValue('manual_links') ?? '');
            Topology::parseBindings($this->getFieldValue('bindings') ?? '');
            $pattern = $this->getFieldValue('host_pattern') ?? '';
            if ($pattern !== '' && substr_count($pattern, '{name}') !== 1) {
                $errors[] = _('The name pattern must contain {name} exactly once or be empty.');
            }
        }
        catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
        return $errors;
    }
}
