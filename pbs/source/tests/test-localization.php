<?php
namespace Zabbix\Core {
    class CWidget {}
}

namespace {
    class CWebUser {
        public static array $data = ['lang' => 'en_GB'];
    }

    function check($ok, string $message): void {
        if (!$ok) {
            throw new \Exception($message);
        }
    }

    $root = __DIR__.'/../modules';
    $modules = [
        'pbs_summary' => 'PbsSummary',
        'pbs_tasks' => 'PbsTasks',
        'pbs_longest' => 'PbsLongest',
        'pbs_running' => 'PbsRunning',
        'pbs_datastore' => 'PbsDatastore'
    ];

    foreach ($modules as $directory => $namespace) {
        $helper = $root.'/'.$directory.'/includes/I18n.php';
        check(is_file($helper), $directory.': module-local I18n helper missing');
        require_once $helper;
        require_once $root.'/'.$directory.'/Widget.php';

        $class = 'Modules\\'.$namespace.'\\Includes\\I18n';
        $widget_class = 'Modules\\'.$namespace.'\\Widget';

        check($class::normalize('de_DE') === 'de', $directory.': de_DE normalization');
        check($class::normalize('de-DE') === 'de', $directory.': de-DE normalization');
        check($class::normalize('de') === 'de', $directory.': de normalization');
        check($class::normalize('en_US') === 'en', $directory.': English normalization');
        check($class::normalize('fr_FR') === 'en', $directory.': unsupported locale fallback');

        CWebUser::$data['lang'] = 'en_GB';
        check($class::locale() === 'en' && $class::t('Close') === 'Close', $directory.': English source fallback');
        CWebUser::$data['lang'] = 'de_DE';
        check($class::locale() === 'de' && $class::t('Close') === 'Schließen', $directory.': German catalog selection');
        CWebUser::$data['lang'] = 'fr_FR';
        check($class::locale() === 'en' && $class::t('Close') === 'Close', $directory.': unsupported user language fallback');

        CWebUser::$data['lang'] = 'de-DE';
        $map = (new $widget_class())->getTranslationStrings()['class.widget.js'] ?? [];
        $javascript = file_get_contents($root.'/'.$directory.'/assets/js/class.widget.js');
        preg_match_all("/\\bt\\('((?:\\\\'|[^'])*)'\\)/", $javascript, $matches);
        check($matches[1] !== [], $directory.': JavaScript must use Zabbix t()');
        foreach (array_unique($matches[1]) as $key) {
            $key = str_replace("\\'", "'", $key);
            check(array_key_exists($key, $map), $directory.': missing JavaScript translation key: '.$key);
        }
        check(($map['Close'] ?? null) === 'Schließen', $directory.': JavaScript German translation map');
    }

    echo "Localization tests passed: English, German, unsupported fallback, locale normalization, and JavaScript map coverage.\n";
}
