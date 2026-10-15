<?php
namespace Modules\UniFiTopology\Includes;
use Zabbix\Widgets\CWidgetField;
use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectItem;
use Zabbix\Widgets\Fields\CWidgetFieldTextBox;
use Zabbix\Widgets\Fields\CWidgetFieldTextArea;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBox;

class WidgetForm extends CWidgetForm {
    public function addFields(): self {
        return $this
            ->addField((new CWidgetFieldMultiSelectItem('itemid', _('Topologie-Master-Item')))->setMultiple(false)->setFlags(CWidgetField::FLAG_NOT_EMPTY | CWidgetField::FLAG_LABEL_ASTERISK))
            ->addField((new CWidgetFieldTextBox('host_pattern', _('Exakter Zabbix-Hostname mit {name}')))->setDefault('UniFi Network - Site: "Default" - Device: "{name}"'))
            ->addField(new CWidgetFieldTextArea('bindings', _('Optionale Zuordnung: MAC=Host-ID, eine je Zeile')))
            ->addField(new CWidgetFieldTextArea('manual_nodes', _('Zusätzliche Geräte: id | Name | optionale Host-ID')))
            ->addField(new CWidgetFieldTextArea('manual_links', _('Zusätzliche Verbindungen: von | nach | Beschriftung')))
            ->addField((new CWidgetFieldIntegerBox('max_age', _('Topologiedaten maximal alt (Sekunden)'), 60, 86400))->setDefault(300))
            ->addField((new CWidgetFieldCheckBox('show_suppressed', _('Unterdrueckte Probleme einbeziehen')))->setDefault(0));
    }
    public function validate(bool $strict = false): array {
        $errors = parent::validate($strict);
        try {
            ManualTopology::parse($this->getFieldValue('manual_nodes') ?? '', $this->getFieldValue('manual_links') ?? '');
            Topology::parseBindings($this->getFieldValue('bindings') ?? '');
            $pattern = $this->getFieldValue('host_pattern') ?? '';
            if ($pattern !== '' && substr_count($pattern, '{name}') !== 1) {
                $errors[] = 'Das Namensmuster muss genau einmal {name} enthalten oder leer sein.';
            }
        }
        catch (\InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
        }
        return $errors;
    }
}
