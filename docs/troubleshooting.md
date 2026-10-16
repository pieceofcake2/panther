# Troubleshooting

**`"chromedriver" binary not found`** — Run `vendor/bin/bdi detect drivers`, or install chromedriver with
your package manager.

**`session not created: This version of ChromeDriver only supports Chrome version ...`** — Chrome was updated.
Run `vendor/bin/bdi detect drivers` again.

**`The port 9080 is already in use`** — Another web server, or an earlier run that did not stop, uses the
port. Stop it, or set `PANTHER_WEB_SERVER_PORT`. For chromedriver (`9515`), pass another `port` in
`$managerOptions`.

**`Missing Database Table` in the browser** — The web server does not use the test database: check
that the tests run with `APP_ENV=test` (no other `APP_ENV` in the environment), that `DATABASE_URL` in
`config/.env.test` or `config/.env.test.local` (or in the real environment) is the test database, and that the test database is not an
in-memory SQLite database.

**The browser and the tests use different files (for example two SQLite databases)** — A relative path is
resolved from the working directory. The CakePHP router script runs the web server in the working directory
of the tests ([Relative Paths](test-database.md#relative-paths)); a router script of your own must change to
`PANTHER_WORKING_DIR` too, and without a router script (`PANTHER_WEB_SERVER_ROUTER` empty) the web server runs
in the document root. Run PHPUnit from the project root, or use absolute paths.

**A variable does not reach the web server** — Set it in the shell, or in `$_ENV` or `$_SERVER`; a variable
set only with `putenv()` is not passed.

**The web server gets a value from `.env` instead of the environment** — The environment of the web server is
built from `$_ENV` first, then from `getenv()`. A `.env` loader that writes to `$_ENV` without checking the real
environment variables (for example josegonzalez/dotenv's `toEnv()`), when PHP does not fill `$_ENV` itself
(`variables_order` without `E`, the default and on the GitHub runners), gives the web server the `.env` values
even where the shell sets other ones, such as the URL of the test database in CI. Do not write `.env` values to
`$_ENV` that way: symfony/dotenv's `bootEnv()`, which pieceofcake2/app uses, copies the real variables into
`$_ENV` first and keeps them. Otherwise pass such values with the `env` option:
`static::createPantherClient(['env' => ['DATABASE_URL' => (string)env('DATABASE_URL')]])`.

**Japanese text is drawn as boxes in the screenshots** — The machine has no Japanese (CJK) font, as on the GitHub
runners. Install one, e.g. `sudo apt-get install -y fonts-noto-cjk` (see [CI](ci.md)).

**The page has no CSS** — The file is not in the document root, or `PANTHER_WEB_SERVER_DIR` points to the
wrong directory.

**Logs of the web server** — CakePHP writes them to the usual `LOGS` directory. Requests and PHP errors of the
built-in web server itself are not shown; start it by hand to see them:
`PANTHER_WORKING_DIR=$PWD php -S 127.0.0.1:9080 -t webroot vendor/pieceofcake2/panther/resources/router.php`.
