# CHANGELOG

## 1.0.0 (unreleased)

- Initial release. It replaces pieceofcake2/e2e-test and is built on Symfony Panther's own test setup
- `TestSuite\PantherTestCase`: a `CakeTestCase` that uses Panther's `PantherTestCaseTrait`
  (`createPantherClient()`, `createAdditionalPantherClient()`, the built-in web server and the `PANTHER_*`
  environment variables) and takes a screenshot of a failed test with `PANTHER_ERROR_SCREENSHOT_DIR`
- The web server directory defaults to `webroot/`, or `app/webroot/` for the legacy layout
- `PANTHER_APP_DATASOURCE` passes `APP_DATASOURCE` to the web server, like `PANTHER_APP_ENV` passes `APP_ENV`,
  so that it uses the datasource of the fixtures
- `resources/router.php`: the CakePHP router script of the web server (the default of
  `PANTHER_WEB_SERVER_ROUTER`); it serves the files of the document root, hands every other URL to
  `index.php` and keeps the cache of the web server in `tmp/cache/panther/`
- `TestSuite\WebTestAssertionsTrait`: the assertions of Symfony's and Panther's `WebTestAssertionsTrait` for the
  Panther client, without the Symfony FrameworkBundle
- `ServerExtension`: Panther's PHPUnit extension for `PantherTestCase`
