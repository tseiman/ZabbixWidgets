<?php
namespace Modules\UniFiTopology;
use Modules\UniFiTopology\Includes\I18n;
use Zabbix\Core\CWidget;
class Widget extends CWidget {
    public function getDefaultName(): string {
        return I18n::t('UniFi topology');
    }
    public function getTranslationStrings(): array {
        return [
            'class.widget.js' => I18n::browserCatalog()
        ];
    }
}
