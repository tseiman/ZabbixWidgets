<?php
namespace Modules\UniFiTopology\Includes;

use API;
use CParser;
use CRangeTimeParser;
use RuntimeException;

/** Bounded, read-only history loader for the per-device traffic series. */
class Traffic {
    private const MAX_POINTS = 320;
    private const KEY_PATTERN = '/^unifi\.device\.(rx|tx)\.(rate|counter)\.bps\[((?:[0-9a-f]{2}:){5}[0-9a-f]{2})\]$/D';

    private static function result($value): array {
        if (!is_array($value)) {
            throw new RuntimeException(_('Traffic query failed or permission is missing.'));
        }

        return $value;
    }

    private static function parsePeriod(array $period): array {
        if (!isset($period['from'], $period['to'])) {
            throw new RuntimeException(_('Dashboard time period for traffic charts is missing.'));
        }

        $parser = new CRangeTimeParser();
        if ($parser->parse((string) $period['from']) !== CParser::PARSE_SUCCESS) {
            throw new RuntimeException(_('The start of the dashboard time period is invalid.'));
        }
        $from = $parser->getDateTime(true)->getTimestamp();

        if ($parser->parse((string) $period['to']) !== CParser::PARSE_SUCCESS) {
            throw new RuntimeException(_('The end of the dashboard time period is invalid.'));
        }
        $to = $parser->getDateTime(false)->getTimestamp();

        if ($from >= $to) {
            throw new RuntimeException(_('The dashboard time period for traffic charts is empty.'));
        }

        return [$from, $to];
    }

    private static function storageSeconds(array $item, string $field): int {
        $value = (string) ($item[$field] ?? '0');

        if ($value !== '0' && $field === 'history'
                && \CHousekeepingHelper::get(\CHousekeepingHelper::HK_HISTORY_GLOBAL)) {
            $value = (string) \CHousekeepingHelper::get(\CHousekeepingHelper::HK_HISTORY);
        }
        elseif ($value !== '0' && $field === 'trends'
                && \CHousekeepingHelper::get(\CHousekeepingHelper::HK_TRENDS_GLOBAL)) {
            $value = (string) \CHousekeepingHelper::get(\CHousekeepingHelper::HK_TRENDS);
        }

        $seconds = \timeUnitToSeconds($value);
        return $seconds === null ? 0 : (int) $seconds;
    }

    private static function source(array $item, int $from, int $to): string {
        $history = self::storageSeconds($item, 'history');
        $trends = self::storageSeconds($item, 'trends');
        $period = $to - $from;

        // Match the native SVG graph's intent: use detailed history while it is
        // retained and useful at this pixel width, otherwise use hourly trends.
        if ($trends === 0 || ($history > 0 && time() - $history < $from
                && $period / self::MAX_POINTS <= ZBX_MAX_TREND_DIFF / ZBX_GRAPH_MAX_SKIP_CELL)) {
            return 'history';
        }

        return 'trends';
    }

    private static function points(array $result): array {
        $points = [];
        foreach ($result['data'] ?? [] as $point) {
            if (!isset($point['clock'], $point['avg']) || !is_numeric($point['avg'])) {
                continue;
            }
            $points[] = [(int) $point['clock'], max(0.0, (float) $point['avg'])];
        }
        usort($points, static fn(array $a, array $b): int => $a[0] <=> $b[0]);

        return $points;
    }

    private static function choose(array $series): ?array {
        $direct = $series['rate'] ?? [];
        $fallback = $series['counter'] ?? [];

        if (!$direct && !$fallback) {
            return null;
        }

        // Prefer controller-provided rates unless the fallback has materially
        // better coverage (for example directly after upgrading the template).
        if ($direct && (!$fallback || count($direct) >= count($fallback) * 0.8)) {
            return ['source' => 'direct', 'points' => $direct, 'latest' => $direct[count($direct) - 1][1]];
        }

        return ['source' => 'counter', 'points' => $fallback,
            'latest' => $fallback[count($fallback) - 1][1]];
    }

    /**
     * Attach traffic data to topology nodes and return the resolved time range.
     * Missing items are a supported upgrade state and do not fail the widget.
     */
    public static function load(array &$nodes, string $hostid, array $period): array {
        [$from, $to] = self::parsePeriod($period);

        $items = self::result(API::Item()->get([
            'output' => ['itemid', 'key_', 'value_type', 'status', 'state', 'history', 'trends'],
            'hostids' => [$hostid],
            'search' => ['key_' => 'unifi.device.'],
            'startSearch' => true,
            'webitems' => true
        ]));

        $metrics = [];
        $identity = [];
        foreach ($items as $item) {
            if ((int) $item['status'] !== ITEM_STATUS_ACTIVE || (int) $item['state'] !== ITEM_STATE_NORMAL
                    || !in_array((int) $item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64], true)
                    || !preg_match(self::KEY_PATTERN, strtolower((string) $item['key_']), $match)) {
                continue;
            }

            $itemid = (string) $item['itemid'];
            $metrics[$itemid] = [
                'itemid' => $itemid,
                'value_type' => (int) $item['value_type'],
                'source' => self::source($item, $from, $to)
            ];
            $identity[$itemid] = ['direction' => $match[1], 'kind' => $match[2], 'mac' => $match[3]];
        }

        foreach ($nodes as &$node) {
            $node['traffic'] = null;
        }
        unset($node);

        if (!$metrics) {
            return ['from' => $from, 'to' => $to, 'items' => 0];
        }

        $results = self::result(\Manager::History()->getGraphAggregationByWidth(
            $metrics, $from, $to, self::MAX_POINTS
        ));
        $by_device = [];
        foreach ($identity as $itemid => $item_identity) {
            if (!isset($results[$itemid])) {
                continue;
            }
            $points = self::points($results[$itemid]);
            if ($points) {
                $by_device[$item_identity['mac']][$item_identity['direction']][$item_identity['kind']] = $points;
            }
        }

        foreach ($nodes as &$node) {
            $mac = strtolower((string) ($node['mac'] ?? ''));
            if (empty($node['managed']) || !isset($by_device[$mac])) {
                continue;
            }
            $rx = self::choose($by_device[$mac]['rx'] ?? []);
            $tx = self::choose($by_device[$mac]['tx'] ?? []);
            if ($rx !== null || $tx !== null) {
                $node['traffic'] = ['rx' => $rx, 'tx' => $tx];
            }
        }
        unset($node);

        return ['from' => $from, 'to' => $to, 'items' => count($metrics)];
    }
}
