<?php
/**
 * Bootstrap for PHPUnit
 *
 * The tests run against the fixture application in tests/test_app (PANTHER_WEB_SERVER_DIR in phpunit.xml.dist).
 */

use Composer\InstalledVersions;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

require_once dirname(__DIR__) . DS . 'vendor' . DS . 'autoload.php';
require_once __DIR__ . DS . 'test_app' . DS . 'config' . DS . 'define.php';

// Keep the cache and logs of the test process out of the repository
if (!defined('TMP')) {
    define('TMP', sys_get_temp_dir() . DS . 'panther_test_' . getmypid() . DS);
}
foreach (['cache/models', 'cache/persistent', 'logs'] as $dir) {
    if (!is_dir(TMP . $dir)) {
        mkdir(TMP . $dir, 0777, true);
    }
}

// The tmp directory of the fixture application, where the router script keeps the cache of the web server
if (!is_dir(__DIR__ . DS . 'test_app' . DS . 'tmp')) {
    mkdir(__DIR__ . DS . 'test_app' . DS . 'tmp', 0777, true);
}

// The SQLite file database of the fixture application, shared by the test process and the web server, which
// inherits the variables of $_ENV (see Symfony Process); see tests/test_app/config/database.php
$_ENV['TEST_APP_DATABASE'] = TMP . 'test_app.sqlite';

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';
