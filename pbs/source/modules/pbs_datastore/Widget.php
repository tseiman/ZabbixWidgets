<?php
namespace Modules\PbsDatastore;

use Modules\PbsDatastore\Includes\I18n;

class Widget extends \Zabbix\Core\CWidget {
    public function getDefaultName(): string {
        return I18n::t('PBS Datastore Usage');
    }

    public function getTranslationStrings(): array {
        return [
            'class.widget.js' => I18n::javascript([
                'Close',
            'Data is stale',
            'Snapshot when opened',
            'newest start time first',
            'tasks',
            'Task',
            'Start time ↓',
            'Duration',
            'Status',
            'Show more tasks',
            'Running',
            'Unknown',
            'No tasks in this category.',
            'Open task log',
            'Task log',
            'Reload',
            'More log lines',
            'Loading log …',
            'Refresh the widget; host mapping is missing.',
            'Log request failed (HTTP %d).',
            'Log unavailable.',
            'Invalid response; check the Zabbix login and module activation.',
            'Display limit reached (20,000 lines / 8 million characters). Open the complete log in PBS.',
            '%d of %d lines',
            'Task is running; reload to refresh',
            'No log lines yet',
            'Timed out. Please reload.',
            'No valid response. Check the Zabbix login and module activation.'
            ])
        ];
    }
}
