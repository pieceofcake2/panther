# Test Database

## The Test Environment

The web server is a separate PHP process. To read the fixtures, its `default` connection must be the
database the fixtures are loaded into, the one of the `test` datasource. As in Symfony, this comes from the
`test` environment. The `tests/bootstrap.php` of pieceofcake2/app sets `APP_ENV` before CakePHP loads the
`.env` files:

```php
putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';
```

The tests run with `APP_ENV=test`, and the web server that Panther starts inherits the environment of the
test process, so it runs with `APP_ENV=test` too. pieceofcake2/app then loads `config/.env.test` in both
processes (the `.env.{APP_ENV}` file of Symfony). It is committed and sets `DATABASE_URL` to the test
database. As in Symfony, there is one variable: `DATABASE_URL` is the database of the current environment.

```dotenv
# config/.env.test
DATABASE_URL="mysql://user:password@localhost:3306/test_database_name?encoding=utf8mb4"
```

With `APP_ENV=test`, `config/database.php` gives `DATABASE_URL` to both connections, so the `default`
connection of the web server is the database of the fixtures. The `test` connection reads it only when
`config/core.php` loaded the files with `APP_ENV=test`; otherwise `DATABASE_URL` is your development
database, which the fixtures must never touch:

```php
public function __construct()
{
    $this->default['dsn'] = env('DATABASE_URL');
    if (Configure::read('App.env') === 'test') {
        $this->test['dsn'] = env('DATABASE_URL');
    }
}
```

Set your own test database in `config/.env.test.local` (ignored by Git) or as a real environment variable,
which wins over `config/.env.test` (CI sets `DATABASE_URL` this way). The test database must be a database both processes can open: a database
server, or an SQLite file, not `:memory:`. The `'dsn'` key needs pieceofcake2/cakephp 2.12.7 or later.

`PANTHER_APP_ENV` is only needed to give the web server a different `APP_ENV` than the tests; Panther passes
it to the web server as `APP_ENV`.

For an application created from an earlier skeleton, copy the `.env` loading at the top of pieceofcake2/app's
`config/core.php` (with the `App.env` line), `tests/bootstrap.php`, `config/.env.test` and the constructor
above.

## Fixtures Shared With the Web Server

`PantherTestCase` is a `CakeTestCase`: `$fixtures` are loaded into the test datasource before each test,
and the web server reads them because its `default` connection is the same database. What the browser saves
is in the test database too, so the test can check it with a model.

The web server inherits the environment variables of the test process: the ones of your shell, and the ones
in `$_ENV` or `$_SERVER` (Symfony Process does not pass a variable that is only set with `putenv()`). Real
environment variables reach the web server this way and win over `config/.env.test` there as well, for
example `DATABASE_URL` set to `mysql://root:root@127.0.0.1:3306/test_app` or, for an
SQLite file that both processes open, to `sqlite:///tmp/tests/end_to_end.sqlite` (relative to the project
root).
