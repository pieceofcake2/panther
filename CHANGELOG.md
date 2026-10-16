# CHANGELOG

## Unreleased

- `PantherTestCase` closes the DevTools of Chrome unless `PANTHER_DEVTOOLS` is set, as Panther 2.3+ does. Panther
  before 2.3 (installed on PHP 8.0) opened them docked in the window, which made the page 645 pixels wide instead of
  1200, so the screenshots of the report used the scale of a phone and what fits on a page was cut at 1357 pixels.
  `PANTHER_DEVTOOLS=1` still opens them
- The web server runs the application in the working directory of the tests: `PantherTestCase::startWebServer()`
  passes it to the web server as `PANTHER_WORKING_DIR` (`PantherTestCase::WORKING_DIR`; a value of the `env`
  option wins), and `resources/router.php` changes to it before it runs `index.php`. The built-in web server ran
  PHP in the document root, the tests in the project root, so a relative path such as the SQLite file
  `sqlite:///tmp/tests/test.sqlite` in `config/.env.test` pointed to two different files. A router script of your
  own gets the variable too
- Docs: relative paths in the test database (docs/test-database.md#relative-paths), instead of an absolute SQLite
  path
- Docs: pieceofcake2/app loads its `.env` files with symfony/dotenv as Symfony does, so `config/.env.test.local`
  needs only the variables you change; troubleshooting: a `.env` loader must not write `.env` values over real
  environment variables in `$_ENV`, which the web server gets its environment from first. The fixture application
  loads its `.env` files the same way (symfony/dotenv replaces josegonzalez/dotenv in require-dev)

## 1.0.2

- Test report: with `PANTHER_REPORT_PDF`, `ServerExtension` writes a PDF report of the End-to-End tests after the
  last test, with the results of all the tests, passed or not, and screenshots of the pages (docs/report.md).
  It is printed by a headless Chrome of its own, in English or Japanese (`PANTHER_REPORT_LANGUAGE`, more
  languages with `PANTHER_REPORT_LOCALE_DIR`), with the titles of `@testdox` or the doc comments of the tests,
  and the repository, branch, commit and run of GitHub Actions or of the local Git
- `PantherTestCase::takeReportScreenshot()` takes a screenshot of a step for the report: the viewport, the
  viewport scrolled to an element, what fits on a page of the report, or the whole page, which the report goes on
  with on the next page between two of its rows. A test that takes none gets the screenshot of its end, and a failed
  test the screenshot of its failure, taken before `tearDown()`
- Docs: the test database is `DATABASE_URL` of `config/.env.test`, which the tests and the web server load with
  `APP_ENV=test` (the default of the tests of pieceofcake2/app), instead of `DATABASE_TEST_URL`
- CI example (docs): the job uploads the report as a PDF, not a zip (`archive: false` of actions/upload-artifact),
  and its summary links to it. The examples no longer set `PANTHER_ERROR_SCREENSHOT_DIR`: the report has the
  screenshots of all the tests

## 1.0.1

- `resources/router.php` copies the environment variables of the web server into `$_SERVER` and `$_ENV`, as Apache
  and PHP-FPM do. The PHP built-in web server leaves them out of `$_SERVER`, so a `.env` loader that skips existing
  `$_SERVER` keys replaced the values passed with the `env` option or `PANTHER_APP_ENV` with those of `.env`
- The screenshots of failed tests get file names that can be uploaded as GitHub Actions artifacts: `"`, `<`, `>`,
  `|`, `*`, `?`, `/` and line breaks in the test name (for example the quotes around the data set of a test with a
  data provider) are replaced with `_` (`PantherTestCase::screenshotName()`)
- Troubleshooting: the web server gets values of `.env` instead of the environment when the tests load `.env`
  into `$_ENV`, and Japanese text drawn as boxes in screenshots on machines without CJK fonts
- CI example (docs): the End-to-End summary links the screenshots artifact (`artifact-url` of pieceofcake2/ci-summary
  1.1), and the job installs Japanese fonts

## 1.0.0

- Initial release. It replaces pieceofcake2/e2e-test and is built on Symfony Panther's own test setup
- `TestSuite\PantherTestCase`: a `CakeTestCase` that uses Panther's `PantherTestCaseTrait`
  (`createPantherClient()`, `createAdditionalPantherClient()`, the built-in web server and the `PANTHER_*`
  environment variables) and takes a screenshot of a failed test with `PANTHER_ERROR_SCREENSHOT_DIR`
- The web server directory defaults to `webroot/`, or `app/webroot/` for the legacy layout
- `PANTHER_APP_ENV=test`, which Panther passes to the web server as `APP_ENV`, lets `config/database.php` use the `test`
  datasource, where the fixtures are
- `resources/router.php`: the CakePHP router script of the web server (the default of
  `PANTHER_WEB_SERVER_ROUTER`); it serves the files of the document root, hands every other URL to
  `index.php` and keeps the cache of the web server in `tmp/cache/panther/`
- `TestSuite\WebTestAssertionsTrait`: the assertions of Symfony's and Panther's `WebTestAssertionsTrait` for the
  Panther client, without the Symfony FrameworkBundle
- `ServerExtension`: Panther's PHPUnit extension for `PantherTestCase`
