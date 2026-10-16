<?php
/**
 * Configuration of the fixture application
 */

use josegonzalez\Dotenv\Loader;

/**
 * Loads config/.env.{APP_ENV}.local and config/.env.{APP_ENV}, as config/core.php of pieceofcake2/app (which
 * also loads config/.env.local, config/.env and .env; the fixture application needs only these). The tests
 * run with APP_ENV=test (tests/bootstrap.php), and the web server inherits it, so both processes load the
 * committed config/.env.test. A variable that is already defined is never overwritten: real environment
 * variables win over the files, and earlier files win over later ones.
 */
$appEnv = (string)env('APP_ENV');
if ($appEnv !== '') {
    foreach ([__DIR__ . DS . ".env.{$appEnv}.local", __DIR__ . DS . ".env.{$appEnv}"] as $dotenvFile) {
        if (!is_file($dotenvFile)) {
            continue;
        }
        $dotenv = new Loader([$dotenvFile]);
        foreach ($dotenv->parse()->toArray() as $key => $value) {
            if (getenv($key) !== false || isset($_ENV[$key]) || isset($_SERVER[$key])) {
                continue;
            }
            // The parser converts some values to booleans, numbers or null; keep them as strings.
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $value = (string)$value;
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
        unset($dotenv, $key, $value);
    }
    unset($dotenvFile);
}
unset($appEnv);

Configure::write('debug', (int)env('DEBUG'));
Configure::write('Error', [
    'handler' => 'ErrorHandler::handleError',
    'level' => E_ALL & ~E_DEPRECATED,
    'trace' => true,
]);
Configure::write('Exception', [
    'handler' => 'ErrorHandler::handleException',
    'renderer' => 'ExceptionRenderer',
    'log' => true,
]);
Configure::write('App.encoding', 'UTF-8');
Configure::write('Session', [
    'defaults' => 'php',
]);
Configure::write('Security.salt', 'panther-fixture-app-salt-0123456789abcdef');
Configure::write('Security.cipherSeed', '76859309657453542496749683645');
Configure::write('Security.useOpenSsl', true);

foreach (['_cake_core_' => 'persistent', '_cake_model_' => 'models'] as $name => $dir) {
    Cache::config($name, [
        'engine' => 'File',
        'prefix' => 'panther_test_app' . $name,
        'path' => CACHE . $dir . DS,
        'serialize' => true,
        'duration' => '+1 hour',
    ]);
}
