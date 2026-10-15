<?php
/**
 * Datasources of the fixture application
 *
 * Both are SQLite file databases, so the web server and the test process share them (`:memory:` cannot be
 * shared between processes). tests/bootstrap.php sets `TEST_APP_DATABASE` to a file of its own for each
 * run; the web server inherits it.
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
     * Uses the file database, and the `test` datasource as `default` when `APP_ENV` is `test`: Panther passes
     * `PANTHER_APP_ENV` (`test` in phpunit.xml.dist) to the web server as `APP_ENV`.
     */
    public function __construct()
    {
        $file = env('TEST_APP_DATABASE');
        if ($file) {
            $this->default['database'] = $file . '.default';
            $this->test['database'] = $file;
        }
        if (env('APP_ENV') === 'test') {
            $this->default = $this->test;
        }
    }
}
