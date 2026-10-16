<?php
namespace Modules\UniFiTopology;

use Zabbix\Core\CWidget;

class Widget extends CWidget {
    public function getDefaultName(): string {
        return _('UniFi topology');
    }

    /** Supply JavaScript labels through Zabbix's standard t() translation API. */
    public function getTranslationStrings(): array {
        return [
            'class.widget.js' => [
                'Access point' => _('Access point'),
                'Added manually' => _('Added manually'),
                'Channel' => _('Channel'),
                'Click a device to show mapped clients, links, and current problems.' => _('Click a device to show mapped clients, links, and current problems.'),
                'Click to enlarge' => _('Click to enlarge'),
                'Client' => _('Client'),
                'Clients' => _('Clients'),
                'Close' => _('Close'),
                'Close warning' => _('Close warning'),
                'Connection to the Zabbix frontend failed. Status unknown.' => _('Connection to the Zabbix frontend failed. Status unknown.'),
                'Controller port rates' => _('Controller port rates'),
                'DEMO · Older topology snapshot. All problem states are simulated; no live status.' => _('DEMO · Older topology snapshot. All problem states are simulated; no live status.'),
                'Details' => _('Details'),
                'Enlarge traffic' => _('Enlarge traffic'),
                'Enlarge traffic chart' => _('Enlarge traffic chart'),
                'Fit' => _('Fit'),
                'Link' => _('Link'),
                'Link speed unknown' => _('Link speed unknown'),
                'Maintenance active' => _('Maintenance active'),
                'Manual' => _('Manual'),
                'Manual ID' => _('Manual ID'),
                'Manual link' => _('Manual link'),
                'Monitoring disabled' => _('Monitoring disabled'),
                'Network device' => _('Network device'),
                'Network devices' => _('Network devices'),
                'Network links' => _('Network links'),
                'No active, unambiguously mapped Zabbix host. Status unknown.' => _('No active, unambiguously mapped Zabbix host. Status unknown.'),
                'No clients are mapped directly to this device. Clients behind other switches appear there.' => _('No clients are mapped directly to this device. Clients behind other switches appear there.'),
                'No current status data. The display is stale; check dashboard refresh.' => _('No current status data. The display is stale; check dashboard refresh.'),
                'No data' => _('No data'),
                'No link to another network device exists in the JSON.' => _('No link to another network device exists in the JSON.'),
                'No nodes with type=DEVICE were returned.' => _('No nodes with type=DEVICE were returned.'),
                'No open problems' => _('No open problems'),
                'No open problems visible to you in the selected filter.' => _('No open problems visible to you in the selected filter.'),
                'No topology data received.' => _('No topology data received.'),
                'No traffic data is available for this time period.' => _('No traffic data is available for this time period.'),
                'Port' => _('Port'),
                'Problem' => _('Problem'),
                'Problem status' => _('Problem status'),
                'Problem status is stale.' => _('Problem status is stale.'),
                'Problems' => _('Problems'),
                'RX and TX throughput of' => _('RX and TX throughput of'),
                'STALE' => _('STALE'),
                'Status could not be updated. No current problem assessment is available.' => _('Status could not be updated. No current problem assessment is available.'),
                'Status is stale' => _('Status is stale'),
                'Status unknown' => _('Status unknown'),
                'Topology' => _('Topology'),
                'Total throughput of all unique physical ports' => _('Total throughput of all unique physical ports'),
                'UniFi network overview' => _('UniFi network overview'),
                'Zabbix host' => _('Zabbix host'),
                'Zoom in' => _('Zoom in'),
                'Zoom out' => _('Zoom out'),
                'acknowledged' => _('acknowledged'),
                'excluding suppressed problems' => _('excluding suppressed problems'),
                'from port counter differences' => _('from port counter differences'),
                'hosts mapped' => _('hosts mapped'),
                'including suppressed problems' => _('including suppressed problems'),
                'links' => _('links'),
                'manual' => _('manual'),
                'show clients and problems' => _('show clients and problems'),
                'since' => _('since'),
                'suppressed' => _('suppressed')
            ]
        ];
    }
}
