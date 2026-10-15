# Test Database

## The database.php Switch

The web server is a separate PHP process. To read the fixtures, it must use the datasource the fixtures are
loaded into, `test`. `PANTHER_APP_DATASOURCE=test` gives the web server the environment variable
`APP_DATASOURCE=test`; use that datasource as `default` in the constructor of `DATABASE_CONFIG`:

```php
public function __construct()
{
    $datasource = env('APP_DATASOURCE');
    if ($datasource && $datasource !== 'default' && isset($this->{$datasource})) {
        $this->default = $this->{$datasource};
    }
}
```

pieceofcake2/app's `config/database.php.default` has these lines. The test datasource must be a database
both processes can open: a database server, or an SQLite file, not `:memory:`.

## Fixtures Shared With the Web Server

`PantherTestCase` is a `CakeTestCase`: `$fixtures` are loaded into the test datasource before each test,
and the web server reads them through `APP_DATASOURCE`. What the browser saves is in the test datasource
too, so the test can check it with a model.

The web server inherits the environment variables of the test process: the ones of your shell, and the ones
in `$_ENV` or `$_SERVER` (Symfony Process does not pass a variable that is only set with `putenv()`). The
`DB_TEST_*` variables of pieceofcake2/app reach the web server this way.
