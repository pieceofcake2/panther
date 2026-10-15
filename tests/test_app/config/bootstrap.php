<?php
/**
 * Bootstrap of the fixture application
 */

Cache::config('default', ['engine' => 'File']);

Configure::write('Dispatcher.filters', [
    'AssetDispatcher',
]);

CakeLog::config('debug', [
    'engine' => 'File',
    'types' => ['notice', 'info', 'debug'],
    'file' => 'debug',
]);
CakeLog::config('error', [
    'engine' => 'File',
    'types' => ['warning', 'error', 'critical', 'alert', 'emergency'],
    'file' => 'error',
]);
