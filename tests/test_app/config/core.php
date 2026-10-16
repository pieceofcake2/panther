<?php
/**
 * Configuration of the fixture application
 */

use Symfony\Component\Dotenv\Dotenv;

/**
 * Loads config/.env, config/.env.local, config/.env.{APP_ENV} and config/.env.{APP_ENV}.local, as
 * config/core.php of pieceofcake2/app (Symfony's model). The tests run with APP_ENV=test
 * (tests/bootstrap.php), and the web server inherits it, so both processes load the committed
 * config/.env.test. Real environment variables win over the files.
 */
(new Dotenv())->usePutenv()->loadEnv(CONFIG . '.env');

Configure::write('debug', env('APP_DEBUG') ?? 0);
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
