<?php
/**
 * Front controller of the fixture application (modern layout)
 */

use Composer\InstalledVersions;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

require_once dirname(__DIR__) . DS . 'config' . DS . 'define.php';

// For the built-in server without a router script
if (PHP_SAPI === 'cli-server') {
    if ($_SERVER['PHP_SELF'] !== '/' . basename(__FILE__) && file_exists(WWW_ROOT . $_SERVER['PHP_SELF'])) {
        return false;
    }
    $_SERVER['PHP_SELF'] = '/' . basename(__FILE__);
}

require_once VENDORS . 'autoload.php';

require_once InstalledVersions::getInstallPath('pieceofcake2/cakephp') . DS . 'src' . DS . 'Cake' . DS . 'bootstrap.php';

$dispatcher = new Dispatcher();
$dispatcher->dispatch(
    new CakeRequest(),
    new CakeResponse(),
);
