<?php
/**
 * Datasources of the fixture application
 *
 * As in pieceofcake2/app, DATABASE_URL is the database of the current environment and sets both
 * connections. The tests run with APP_ENV=test, so config/core.php loads config/.env.test, where it is an
 * SQLite file database; the web server inherits APP_ENV=test and loads the same file, so its `default`
 * connection is the database of the fixtures.
 */

class DATABASE_CONFIG
{
    /**
     * @var array<string, mixed>
     */
    public $default = [
        'datasource' => 'Database/Sqlite',
        'database' => ':memory:',
    ];

    /**
     * @var array<string, mixed>
     */
    public $test = [
        'datasource' => 'Database/Sqlite',
        'database' => ':memory:',
    ];

    /**
     * Reads both connections from DATABASE_URL.
     */
    public function __construct()
    {
        $this->default['dsn'] = env('DATABASE_URL');
        $this->test['dsn'] = env('DATABASE_URL');
    }
}
