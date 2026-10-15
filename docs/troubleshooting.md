# Troubleshooting

**`"chromedriver" binary not found`** — Run `vendor/bin/bdi detect drivers`, or install chromedriver with
your package manager.

**`session not created: This version of ChromeDriver only supports Chrome version ...`** — Chrome was updated.
Run `vendor/bin/bdi detect drivers` again.

**`The port 9080 is already in use`** — Another web server, or an earlier run that did not stop, uses the
port. Stop it, or set `PANTHER_WEB_SERVER_PORT`. For chromedriver (`9515`), pass another `port` in
`$managerOptions`.

**`Missing Database Table` in the browser** — The web server does not use the test datasource: check
`PANTHER_APP_ENV` in `phpunit.xml.dist` and the `APP_ENV` lines of `config/database.php`, and
that the test datasource is not an in-memory SQLite database.

**A variable does not reach the web server** — Set it in the shell, or in `$_ENV` or `$_SERVER`; a variable
set only with `putenv()` is not passed.

**The page has no CSS** — The file is not in the document root, or `PANTHER_WEB_SERVER_DIR` points to the
wrong directory.

**Logs of the web server** — CakePHP writes them to the usual `LOGS` directory. Requests and PHP errors of the
built-in web server itself are not shown; start it by hand to see them:
`php -S 127.0.0.1:9080 -t webroot vendor/pieceofcake2/panther/resources/router.php`.
