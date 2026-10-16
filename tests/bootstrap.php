<?php
/**
 * Bootstrap for PHPUnit
 *
 * The tests run against the fixture application in tests/test_app (PANTHER_WEB_SERVER_DIR in phpunit.xml.dist).
 *
 * As in pieceofcake2/app, the tests run with APP_ENV=test by default (an APP_ENV from the environment wins), so
 * tests/test_app/config/core.php loads config/.env.test, where DATABASE_URL is the test database. The web
 * server inherits it from $_ENV and $_SERVER (see Symfony Process) and loads the same file.
 */

use Composer\InstalledVersions;

if ((string)getenv('APP_ENV') === '' && empty($_SERVER['APP_ENV'])) {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $_SERVER['APP_ENV'] = 'test';
}

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

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'tests' . DS . 'bootstrap.php';
