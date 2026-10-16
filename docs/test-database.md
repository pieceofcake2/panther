# Test Database

## The Test Environment

The web server is a separate PHP process. To read the fixtures, its `default` connection must be the
database the fixtures are loaded into, the one of the `test` datasource. As in Symfony, this comes from the
`test` environment. The tests of pieceofcake2/app (`vendor/bin/phpunit` and `bin/cake test`) run with
`APP_ENV=test` by default: when `APP_ENV` is not set, its `tests/bootstrap.php` sets it before CakePHP loads
the `.env` files:

```php
if ((string)getenv('APP_ENV') === '' && empty($_SERVER['APP_ENV'])) {
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
    $_SERVER['APP_ENV'] = 'test';
}
```

The tests run with `APP_ENV=test`, and the web server that Panther starts inherits the environment of the
test process, so it runs with `APP_ENV=test` too. pieceofcake2/app then loads `config/.env.test` in both
processes (the `.env.{APP_ENV}` file of Symfony). It is committed and sets `DATABASE_URL` to the test
database. As in Symfony, there is one variable: `DATABASE_URL` is the database of the current environment.

```dotenv
# config/.env.test
DATABASE_URL="mysql://user:password@localhost:3306/test_database_name?encoding=utf8mb4"
```

`config/database.php` gives `DATABASE_URL` to both connections, so the `default` connection of the web
server is the database of the fixtures:

```php
public function __construct()
{
    $this->default['dsn'] = env('DATABASE_URL');
    $this->test['dsn'] = env('DATABASE_URL');
}
```

Set your own test database in `config/.env.test.local` (ignored by Git) or as a real environment variable,
which wins over `config/.env.test` (CI sets `DATABASE_URL` this way). Do not export your development
`APP_ENV` or `DATABASE_URL` in the shell where you run the tests: they win over the files too. The test database must be a database both processes can open: a database
server, or an SQLite file, not `:memory:`. The `'dsn'` key needs pieceofcake2/cakephp 2.12.7 or later.

`PANTHER_APP_ENV` is only needed to give the web server a different `APP_ENV` than the tests; Panther passes
it to the web server as `APP_ENV`.

For an application created from an earlier skeleton, copy the `.env` loading at the top of pieceofcake2/app's
`config/core.php`, the `APP_ENV` default of `tests/bootstrap.php` and `bin/cake`, `config/.env.test` and the
constructor above.

## Fixtures Shared With the Web Server

`PantherTestCase` is a `CakeTestCase`: `$fixtures` are loaded into the test datasource before each test,
and the web server reads them because its `default` connection is the same database. What the browser saves
is in the test database too, so the test can check it with a model.

The web server inherits the environment variables of the test process: the ones of your shell, and the ones
in `$_ENV` or `$_SERVER` (Symfony Process does not pass a variable that is only set with `putenv()`). Real
environment variables reach the web server this way and win over `config/.env.test` there as well, for
example `DATABASE_URL` set to `mysql://root:root@127.0.0.1:3306/test_app`.

<a id="relative-paths"></a>

## Relative Paths

The web server runs the application in the working directory of the tests: `PantherTestCase` passes it to
the web server as `PANTHER_WORKING_DIR`, and the [CakePHP router script](configuration.md#the-cakephp-router-script)
changes to it before it runs `index.php`. A relative path is resolved from the project root, where you run
PHPUnit, in both processes, so an SQLite file with a relative path in the committed `config/.env.test` is
the same file for the tests and for the browser:

```dotenv
# config/.env.test
DATABASE_URL="sqlite:///tmp/tests/test.sqlite"
```

`sqlite:///tmp/tests/test.sqlite` (three slashes) is `tmp/tests/test.sqlite` of the project, an absolute path
has four slashes (`sqlite:////var/tmp/test.sqlite`). The directory must exist; SQLite creates the file.

In production, Apache and PHP-FPM run PHP in the directory of `index.php` (`webroot/`), so a relative path of
the application points elsewhere there than in the tests. CakePHP 2 itself opens its files with absolute paths
(`TMP`, `LOGS`); build the paths of your application from these constants too.
