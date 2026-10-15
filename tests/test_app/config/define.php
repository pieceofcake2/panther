<?php
/**
 * Paths of the fixture application (modern layout)
 */

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}

if (!defined('APP_DIR')) {
    define('APP_DIR', 'src');
}

// Before the console bootstrap of the tests defines it from the current directory
if (!defined('APP')) {
    define('APP', ROOT . DS . APP_DIR . DS);
}

if (!defined('WEBROOT_DIR')) {
    define('WEBROOT_DIR', 'webroot');
}

if (!defined('WWW_ROOT')) {
    define('WWW_ROOT', ROOT . DS . WEBROOT_DIR . DS);
}

if (!defined('VENDORS')) {
    define('VENDORS', dirname(ROOT, 2) . DS . 'vendor' . DS);
}
