<?php
namespace Modules\UniFiTopology\Actions;

use API;
use CController;
use CControllerResponseData;
use CParser;
use CRangeTimeParser;
use Modules\UniFiTopology\Includes\I18n;
use Modules\UniFiTopology\Includes\Topology;
use Widgets\SvgGraph\Includes\CSvgGraphHelper;
use Widgets\SvgGraph\Includes\CWidgetFieldDataSet;

/** Read-only endpoint producing Zabbix's native SVG graph for one topology device. */
class TrafficGraph extends CController {
    private ?array $topology_item = null;

    protected function init(): void {
        $this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
        $this->disableCsrfValidation();
    }

    private function respond(array $payload): void {
        $this->setResponse((new CControllerResponseData([
            'main_block' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)
        ]))->disableView());
    }

    protected function checkInput(): bool {
        $valid = $this->validateInput([
            'topology_itemid' => 'required|db items.itemid',
            'mac' => 'required|string',
            'rx_source' => 'required|in rate,counter,auto',
            'tx_source' => 'required|in rate,counter,auto',
            'from' => 'required|string',
            'to' => 'required|string',
            'width' => 'required|int32|ge 320|le 1800',
            'height' => 'required|int32|ge 220|le 800'
        ]);

        if ($valid) {
            try {
                Topology::mac($this->getInput('mac'));
                $this->period((string) $this->getInput('from'), (string) $this->getInput('to'));
            }
            catch (\InvalidArgumentException | \RuntimeException $e) {
                $valid = false;
            }
        }

        if (!$valid) {
            $this->respond(['error' => ['messages' => [I18n::t('Invalid traffic graph request.')]]]);
        }

        return $valid;
    }

    protected function checkPermissions(): bool {
        if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
            return false;
        }

        $topology_itemid = (string) $this->getInput('topology_itemid');
        $items = API::Item()->get([
            'output' => ['itemid', 'hostid', 'value_type'],
            'itemids' => [$topology_itemid],
            'webitems' => true
        ]);

        if (!is_array($items) || count($items) !== 1
                || (int) $items[0]['value_type'] !== ITEM_VALUE_TYPE_TEXT) {
            return false;
        }

        $this->topology_item = $items[0];
        return true;
    }

    private function period(string $from, string $to): array {
        $parser = new CRangeTimeParser();
        if ($parser->parse($from) !== CParser::PARSE_SUCCESS) {
            throw new \InvalidArgumentException('from');
        }
        $time_from = $parser->getDateTime(true)->getTimestamp();

        if ($parser->parse($to) !== CParser::PARSE_SUCCESS) {
            throw new \InvalidArgumentException('to');
        }
        $time_to = $parser->getDateTime(false)->getTimestamp();

        if ($time_from >= $time_to) {
            throw new \InvalidArgumentException('period');
        }

        return [$time_from, $time_to];
    }

    private function trafficItems(string $mac): array {
        $requested = [];
        $keys = [];
        foreach (['rx', 'tx'] as $direction) {
            $source = (string) $this->getInput($direction.'_source');
            $requested[$direction] = $source === 'auto' ? ['rate', 'counter'] : [$source];
            foreach ($requested[$direction] as $candidate) {
                $keys[] = sprintf('unifi.device.%s.%s.bps[%s]', $direction, $candidate, $mac);
            }
        }

        $topology_item = $this->topology_item;
        $items = API::Item()->get([
            'output' => ['itemid', 'key_', 'value_type', 'status', 'state'],
            'hostids' => [$topology_item['hostid']],
            'filter' => ['key_' => $keys],
            'webitems' => true
        ]);
        if (!is_array($items)) {
            return [];
        }

        $by_key = [];
        foreach ($items as $item) {
            $key = (string) $item['key_'];
            if (!in_array($key, $keys, true)
                    || (int) $item['status'] !== ITEM_STATUS_ACTIVE
                    || (int) $item['state'] !== ITEM_STATE_NORMAL
                    || !in_array((int) $item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64], true)) {
                continue;
            }
            $by_key[$key][] = (string) $item['itemid'];
        }

        $resolved = [];
        foreach ($requested as $direction => $sources) {
            foreach ($sources as $source) {
                $key = sprintf('unifi.device.%s.%s.bps[%s]', $direction, $source, $mac);
                if (count($by_key[$key] ?? []) === 1) {
                    $resolved[$direction] = $by_key[$key][0];
                    break;
                }
            }
        }
        return $resolved;
    }

    private function ensureGraphHelper(): void {
        if (class_exists(CSvgGraphHelper::class) && class_exists(CWidgetFieldDataSet::class)) {
            return;
        }

        $includes = dirname(__DIR__, 3).'/widgets/svggraph/includes';
        $data_set = $includes.'/CWidgetFieldDataSet.php';
        $helper = $includes.'/CSvgGraphHelper.php';
        if (!is_file($data_set) || !is_file($helper)) {
            throw new \RuntimeException(I18n::t('The native Zabbix graph component is unavailable.'));
        }
        require_once $data_set;
        require_once $helper;
        if (!class_exists(CSvgGraphHelper::class) || !class_exists(CWidgetFieldDataSet::class)) {
            throw new \RuntimeException(I18n::t('The native Zabbix graph component is unavailable.'));
        }
    }

    protected function doAction(): void {
        try {
            $mac = Topology::mac($this->getInput('mac'));
            [$time_from, $time_to] = $this->period(
                (string) $this->getInput('from'),
                (string) $this->getInput('to')
            );
            $items = $this->trafficItems($mac);
            if (!$items) {
                throw new \RuntimeException(I18n::t('No readable traffic items were found for this device.'));
            }

            $this->ensureGraphHelper();
            $itemids = [];
            $colors = [];
            foreach (['rx' => '2F80ED', 'tx' => '2C9975'] as $direction => $color) {
                if (isset($items[$direction])) {
                    $itemids[] = $items[$direction];
                    $colors[] = $color;
                }
            }

            $graph_data = [
                'data_sets' => [[
                    'dataset_type' => CWidgetFieldDataSet::DATASET_TYPE_SINGLE_ITEM,
                    'hosts' => [],
                    'items' => [],
                    'itemids' => $itemids,
                    'references' => [],
                    'color' => $colors,
                    'color_palette' => 0,
                    'type' => SVG_GRAPH_TYPE_LINE,
                    'stacked' => SVG_GRAPH_STACKED_OFF,
                    'width' => SVG_GRAPH_DEFAULT_WIDTH,
                    'pointsize' => SVG_GRAPH_DEFAULT_POINTSIZE,
                    'transparency' => SVG_GRAPH_DEFAULT_TRANSPARENCY,
                    'fill' => SVG_GRAPH_DEFAULT_FILL,
                    'axisy' => GRAPH_YAXIS_SIDE_LEFT,
                    'timeshift' => '',
                    'missingdatafunc' => SVG_GRAPH_MISSING_DATA_NONE,
                    'aggregate_function' => AGGREGATE_NONE,
                    'aggregate_interval' => GRAPH_AGGREGATE_DEFAULT_INTERVAL,
                    'aggregate_grouping' => GRAPH_AGGREGATE_BY_ITEM,
                    'approximation' => APPROXIMATION_AVG,
                    'data_set_label' => '',
                    'override_hostid' => []
                ]],
                'data_source' => SVG_GRAPH_DATA_SOURCE_AUTO,
                'fix_time_period' => false,
                'displaying' => [
                    'show_simple_triggers' => false,
                    'show_working_time' => false,
                    'show_percentile_left' => false,
                    'percentile_left_value' => null,
                    'show_percentile_right' => false,
                    'percentile_right_value' => null
                ],
                'time_period' => ['time_from' => $time_from, 'time_to' => $time_to],
                'axes' => [
                    'show_left_y_axis' => true,
                    'left_y_scale' => SVG_GRAPH_AXIS_SCALE_LINEAR,
                    'left_y_min' => null,
                    'left_y_max' => null,
                    'left_y_units' => null,
                    'show_right_y_axis' => false,
                    'right_y_scale' => SVG_GRAPH_AXIS_SCALE_LINEAR,
                    'right_y_min' => null,
                    'right_y_max' => null,
                    'right_y_units' => null,
                    'show_x_axis' => true
                ],
                'legend' => [
                    'show_legend' => false,
                    'legend_columns' => 1,
                    'legend_lines' => 1,
                    'legend_lines_mode' => 0,
                    'legend_statistic' => false,
                    'show_aggregation' => false
                ],
                'problems' => [
                    'show_problems' => false,
                    'graph_item_problems' => false,
                    'problemhosts' => '',
                    'severities' => [],
                    'problem_name' => '',
                    'evaltype' => 0,
                    'tags' => []
                ],
                'overrides' => [],
                'templateid' => '',
                'override_hostid' => ''
            ];

            $svg_options = CSvgGraphHelper::get(
                $graph_data,
                (int) $this->getInput('width'),
                (int) $this->getInput('height')
            );
            if (!empty($svg_options['errors'])) {
                throw new \RuntimeException(implode(' ', $svg_options['errors']));
            }

            $svg_options['data'] = zbx_array_merge($svg_options['data'], [
                'sbox' => true,
                'show_problems' => false,
                'show_simple_triggers' => false,
                'time_period' => [
                    'from' => (string) $this->getInput('from'),
                    'to' => (string) $this->getInput('to')
                ],
                'hint_max_rows' => ZBX_WIDGET_ROWS
            ]);

            $this->respond([
                'svg' => (string) $svg_options['svg'],
                'svg_options' => ['data' => $svg_options['data']]
            ]);
        }
        catch (\RuntimeException | \InvalidArgumentException $e) {
            $this->respond(['error' => ['messages' => [$e->getMessage()]]]);
        }
        catch (\Throwable $e) {
            error_log('UniFi Topology traffic graph: '.get_class($e).': '.$e->getMessage());
            $this->respond(['error' => ['messages' => [I18n::t('The traffic graph could not be generated.')]]]);
        }
    }
}
