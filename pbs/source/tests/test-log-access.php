<?php
require_once __DIR__.'/../modules/pbs_tasks/includes/I18n.php';
define('USER_TYPE_ZABBIX_USER', 1);
class CController {
    public array $input = ['hostid' => '123'];
    public int $userType = 1;
    public function getInput($name, $default = null) { return $this->input[$name] ?? $default; }
    public function getUserType() { return $this->userType; }
}
class API {
    public static bool $host = true;
    public static bool $item = true;
    public static function Host() { return new class { public function get($params) { return API::$host ? [['hostid' => '123']] : []; } }; }
    public static function Item() { return new class { public function get($params) { return API::$item ? [['itemid' => '456']] : []; } }; }
}
require __DIR__.'/../modules/pbs_tasks/actions/TaskLog.php';
$controller = new class extends \Modules\PbsTasks\Actions\TaskLog { public function allowed(): bool { return $this->checkPermissions(); } };
function check($ok, $message) { if (!$ok) { throw new Exception($message); } }
check($controller->allowed(), 'Authorized host and item');
API::$host = false; check(!$controller->allowed(), 'Host access denied');
API::$host = true; API::$item = false; check(!$controller->allowed(), 'Item access denied');
API::$item = true; $controller->userType = 0; check(!$controller->allowed(), 'Unauthenticated user denied');
echo "Log endpoint access passed: session user, host and item required.\n";
