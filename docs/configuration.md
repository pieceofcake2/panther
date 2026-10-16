# Configuration

The End-to-End tests are configured like Symfony Panther: with `PANTHER_*` environment variables and the
options of `createPantherClient()`.

## phpunit.xml.dist

Register the extension, set the datasource of the web server and add an `EndToEnd` suite
([docs/examples/phpunit.xml.dist](examples/phpunit.xml.dist)):

```xml
<phpunit bootstrap="./tests/bootstrap.php" defaultTestSuite="App">
    <php>
        <!-- The web server uses the test datasource, where the fixtures are -->
        <server name="PANTHER_APP_ENV" value="test"/>
        <!-- Screenshots of the failed End-to-End tests -->
        <server name="PANTHER_ERROR_SCREENSHOT_DIR" value="./tmp/error-screenshots"/>
    </php>
    <extensions>
        <extension class="PieceOfCake2\Panther\ServerExtension"/>
    </extensions>
    <listeners>
        <listener class="CakeFixtureInjector" file="./vendor/pieceofcake2/cakephp/src/Cake/TestSuite/Fixture/CakeFixtureInjector.php"/>
    </listeners>
    <testsuites>
        <testsuite name="App">
            <directory>./tests/TestCase/</directory>
            <exclude>./tests/TestCase/EndToEnd/</exclude>
        </testsuite>
        <testsuite name="EndToEnd">
            <directory>./tests/TestCase/EndToEnd/</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

Like Panther, the package reads its settings from `$_SERVER`, so they are `<server>` elements, as in the
Symfony documentation. A variable of your shell is in `$_SERVER` too, but a `<server>` element replaces it:
put per-machine values such as `PANTHER_NO_HEADLESS=1` in your shell, or in a `phpunit.xml` copy of
`phpunit.xml.dist`.

The extension keeps the web server and the browser running from the first End-to-End test to the last one
of the run. Without it, they stop after each test class, as in Panther. Nothing starts until a test calls
`createPantherClient()`, so a run of the `App` suite starts no browser.

## Local Overrides

Put per-machine values in your shell, or in a `phpunit.xml` copy of `phpunit.xml.dist` (PHPUnit reads
`phpunit.xml` first; keep it out of Git):

```bash
PANTHER_NO_HEADLESS=1 vendor/bin/phpunit --testsuite EndToEnd     # watch the browser
PANTHER_WEB_SERVER_PORT=9180 vendor/bin/phpunit --testsuite EndToEnd
```

## Environment variables

These are Panther's variables, with Panther's meaning. Set them in the shell,
or with `<server>` in `phpunit.xml.dist`.

| Variable | Meaning |
| --- | --- |
| `PANTHER_WEB_SERVER_DIR` | Document root. Default: `webroot/` or `app/webroot/` of the project. Relative paths must start with `./` |
| `PANTHER_WEB_SERVER_PORT` | Port of the web server. Default `9080` |
| `PANTHER_WEB_SERVER_ROUTER` | Router script, relative to the document root. Default: the [CakePHP router script](#the-cakephp-router-script); empty for none |
| `PANTHER_READINESS_PATH` | Path requested to check that the web server has started. Default `/` |
| `PANTHER_EXTERNAL_BASE_URI` | Test an application that is already running at this URL; no web server is started |
| `PANTHER_APP_ENV` | Passed as `APP_ENV` to the web server. Set it to `test` so that `config/database.php` uses the test datasource |
| `PANTHER_ERROR_SCREENSHOT_DIR` | Directory for the screenshots of failed tests, e.g. `./tmp/error-screenshots` |
| `PANTHER_ERROR_SCREENSHOT_ATTACH` | Print the screenshots in the JUnit attachment format |
| `PANTHER_NO_HEADLESS` | Show the browser window |
| `PANTHER_DEVTOOLS` | Open the DevTools of the browser |
| `PANTHER_NO_REDUCED_MOTION` | Do not ask for reduced motion (Panther 2.2+) |
| `PANTHER_NO_SANDBOX` | Disable the sandbox of Chrome (containers, CI) |
| `PANTHER_CHROME_ARGUMENTS` | More Chrome arguments, separated by spaces, e.g. `--lang=ja --window-size=1600,1200` |
| `PANTHER_CHROME_BINARY` | Chrome binary to use |
| `PANTHER_FIREFOX_ARGUMENTS`, `PANTHER_FIREFOX_BINARY` | The same for Firefox |

The `PANTHER_REPORT_*` variables of this package write a PDF report of the tests: see [Test Report](report.md#settings).

## createPantherClient() options

`createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = [])` is Panther's:

- `$options`: `webServerDir`, `hostname` (default `127.0.0.1`), `port`, `router`, `readinessPath`,
  `external_base_uri`, `env` (more environment variables for the web server), `browser` (`static::CHROME`,
  `static::FIREFOX` or `static::SELENIUM`) and `browser_arguments`
- `$kernelOptions`: not used, there is no Symfony kernel
- `$managerOptions`: options of the browser manager, e.g. `port` of chromedriver (default `9515`),
  `capabilities` and `chromedriver_arguments`

The options apply when the browser starts, at the first call of the run; the following calls return the
same browser.

## The web server

`createPantherClient()` starts PHP's built-in web server with Panther's `WebServerManager`, with the options and
variables above, and with these defaults for CakePHP.

### The CakePHP router script

The default router script, `vendor/pieceofcake2/panther/resources/router.php`:

- serves the files of the document root (CSS, JavaScript, images) as they are
- hands every other request to `index.php` of the document root, with the script name CakePHP uses to
  detect the base path, also for URLs that look like files, such as `/articles.json`
- keeps the cache of the web server (`CACHE`) in `tmp/cache/panther/`: the web server uses the test database
  as `default`, and must not use the model cache of your development server for it

For more, copy it into your application and set `PANTHER_WEB_SERVER_ROUTER` to the copy, relative to the
document root, e.g. `../tests/router.php`.

### Modern and legacy layouts

The document root is `webroot/` of the project (pieceofcake2/app), or `app/webroot/` when only that exists
(the classic CakePHP 2 skeleton). The project is the Composer root package. Set `PANTHER_WEB_SERVER_DIR` for
another directory.
