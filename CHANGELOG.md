# CHANGELOG

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
