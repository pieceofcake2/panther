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
     * Uses the file database, and the datasource in `APP_DATASOURCE` as `default`: PantherTestCase passes it
     * to the web server from `PANTHER_APP_DATASOURCE` (`test` in phpunit.xml.dist).
     */
    public function __construct()
    {
        $file = env('TEST_APP_DATABASE');
        if ($file) {
            $this->default['database'] = $file . '.default';
            $this->test['database'] = $file;
        }
        $datasource = env('APP_DATASOURCE');
        if ($datasource && $datasource !== 'default' && isset($this->{$datasource})) {
            $this->default = $this->{$datasource};
        }
    }
}
