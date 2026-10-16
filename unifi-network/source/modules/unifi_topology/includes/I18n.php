<?php
namespace Modules\UniFiTopology\Includes;

/** Module-local translations; custom widget text must not depend on Zabbix's gettext catalog. */
final class I18n {
    private static ?array $catalog = null;

    public static function language(?string $language = null): string {
        if ($language === null && class_exists('\CWebUser')) {
            $language = (string) (\CWebUser::$data['lang'] ?? 'en');
        }
        $parts = preg_split('/[-_]/', strtolower((string) $language), 2);
        $base = $parts[0] ?? '';
        return $base === 'de' ? 'de' : 'en';
    }

    public static function locale(?string $language = null): string {
        return self::language($language) === 'de' ? 'de-DE' : 'en-US';
    }

    public static function t(string $source, array $parameters = [], ?string $language = null): string {
        $translated = $source;
        if (self::language($language) === 'de') {
            $translated = self::catalog()['de'][$source] ?? $source;
        }
        if ($parameters) {
            $replace = [];
            foreach ($parameters as $name => $value) {
                $replace['{'.$name.'}'] = (string) $value;
            }
            $translated = strtr($translated, $replace);
        }
        return $translated;
    }

    public static function browserCatalog(?string $language = null): array {
        $result = [];
        foreach (self::catalog()['javascript'] as $source) {
            $result[$source] = self::t($source, [], $language);
        }
        return $result;
    }

    private static function catalog(): array {
        if (self::$catalog === null) {
            $json = file_get_contents(__DIR__.'/i18n.json');
            $catalog = $json === false ? null : json_decode($json, true);
            self::$catalog = is_array($catalog) ? $catalog : ['de' => [], 'javascript' => []];
        }
        return self::$catalog;
    }
}
