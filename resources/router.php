<?php
/**
 * CakePHP router script for PHP's built-in web server
 *
 * `PantherTestCase` uses it by default; `PANTHER_WEB_SERVER_ROUTER` replaces it. Like the router script of
 * the Symfony documentation, it:
 *
 * - serves the files of the document root (CSS, JavaScript, images) as they are
 * - hands every other request to `index.php` of the document root, with the script name CakePHP uses to
 *   detect the base path, also for URLs with a file extension such as `/articles.json`
 *
 * and, because the web server uses the test datasource as `default`, it keeps its cache (`CACHE`) in
 * `tmp/cache/panther/`, so that the model cache of your development server is not used for the test database.
 *
 * The built-in web server does not put the environment variables into `$_SERVER` (Apache and PHP-FPM do), so
 * a `.env` loader that skips existing `$_SERVER` keys would replace the values Panther passes to the web
 * server (`env` option, `PANTHER_APP_ENV`) with the ones of `.env`. The router copies them into `$_SERVER`
 * and `$_ENV` first, as a web server does.
 */

$pantherDocumentRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\');
$pantherFile = realpath($pantherDocumentRoot . rawurldecode((string)parse_url((string)$_SERVER['REQUEST_URI'], PHP_URL_PATH)));
if (
    $pantherFile !== false
    && is_file($pantherFile)
    && str_starts_with($pantherFile, (string)realpath($pantherDocumentRoot) . DIRECTORY_SEPARATOR)
    && strtolower(pathinfo($pantherFile, PATHINFO_EXTENSION)) !== 'php'
) {
    return false;
}

foreach (getenv() as $pantherName => $pantherValue) {
    $_SERVER[$pantherName] ??= $pantherValue;
    $_ENV[$pantherName] ??= $pantherValue;
}

$pantherTmp = dirname($pantherDocumentRoot) . DIRECTORY_SEPARATOR . 'tmp';
if (!defined('CACHE') && is_dir($pantherTmp)) {
    $pantherCache = $pantherTmp . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'panther' . DIRECTORY_SEPARATOR;
    foreach (['models', 'persistent', 'views'] as $pantherDir) {
        if (!is_dir($pantherCache . $pantherDir)) {
            mkdir($pantherCache . $pantherDir, 0777, true);
        }
    }
    define('CACHE', $pantherCache);
}

$_SERVER['SCRIPT_FILENAME'] = $pantherDocumentRoot . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

unset($pantherDocumentRoot, $pantherFile, $pantherName, $pantherValue, $pantherTmp, $pantherCache, $pantherDir);

return require $_SERVER['SCRIPT_FILENAME'];
