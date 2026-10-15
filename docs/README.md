# pieceofcake2/panther

End-to-End tests for [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) applications with
[Symfony Panther](https://symfony.com/doc/current/testing/end_to_end.html), Chrome and PHPUnit 9.6, set up the
way Symfony applications run them.

- [Writing Tests](writing-tests.md)
- [Configuration](configuration.md)
- [Test Database](test-database.md)
- [CI with GitHub Actions](ci.md)
- [Troubleshooting](troubleshooting.md)

## What it is

[Panther](https://symfony.com/doc/current/testing/end_to_end.html) is Symfony's library for End-to-End tests:
its `PantherTestCase` starts the application with PHP's built-in web server and drives it with a real Chrome
(or Firefox) through the WebDriver protocol. This package is the same thing for a CakePHP 2 application:

| Panther | This package |
| --- | --- |
| `Symfony\Component\Panther\PantherTestCase` | `PieceOfCake2\Panther\TestSuite\PantherTestCase`, a `CakeTestCase` that uses Panther's `PantherTestCaseTrait` |
| `Symfony\Component\Panther\ServerExtension` | `PieceOfCake2\Panther\ServerExtension`, the same extension for `PantherTestCase` |
| The assertions of `WebTestAssertionsTrait` | `PieceOfCake2\Panther\TestSuite\WebTestAssertionsTrait`, the same assertions without the Symfony FrameworkBundle |
| `PANTHER_WEB_SERVER_DIR` defaults to `public/` | defaults to `webroot/` (or `app/webroot/`) |
| `PANTHER_WEB_SERVER_ROUTER` defaults to none | defaults to the [CakePHP router script](configuration.md#the-cakephp-router-script) |
| `PANTHER_APP_ENV` sets `APP_ENV` of the web server | `PANTHER_APP_DATASOURCE` sets `APP_DATASOURCE` of the web server |

Everything else is Panther's own: the `Client` (`request()`, `clickLink()`, `submitForm()`, `waitFor()`,
`takeScreenshot()` ...), `createPantherClient()` and its options, and the `PANTHER_*` environment variables.
The [Symfony documentation](https://symfony.com/doc/current/testing/end_to_end.html) applies as it is.

End-to-End tests are slower than the tests of controllers with `ControllerTestCase` or
`IntegrationTestTrait`: use them for what needs a browser, such as JavaScript, the layout, and flows across
several pages.

## Requirements

- PHP 8.0+ (Panther 2.1 on PHP 8.0, Panther 2.4 on PHP 8.1+)
- pieceofcake2/cakephp 2.12+
- PHPUnit 9.6
- Google Chrome or Chromium, and a chromedriver of the same major version

## Installation

```bash
composer require --dev pieceofcake2/panther dbrekelmans/bdi
vendor/bin/bdi detect drivers
```

`vendor/bin/bdi detect drivers` downloads the chromedriver that matches your Chrome to `drivers/`. Panther
looks for `chromedriver` in the `PATH`, then in `drivers/` and `vendor/bin/`, so a chromedriver of your
package manager (`brew install chromedriver`, `apt-get install chromium-chromedriver`) works as well.
Add `/drivers/` to `.gitignore`.

## Quick Start

1. Register the extension and set the datasource of the web server in `phpunit.xml.dist`
   ([Configuration](configuration.md#phpunitxmldist)):

   ```xml
   <php>
       <server name="PANTHER_APP_DATASOURCE" value="test"/>
       <server name="PANTHER_ERROR_SCREENSHOT_DIR" value="./tmp/error-screenshots"/>
   </php>
   <extensions>
       <extension class="PieceOfCake2\Panther\ServerExtension"/>
   </extensions>
   ```

2. Make `config/database.php` use that datasource as `default` in the web server
   ([Test Database](test-database.md#the-databasephp-switch)).
3. Write tests that extend `PieceOfCake2\Panther\TestSuite\PantherTestCase` in `tests/TestCase/EndToEnd/`
   ([Writing Tests](writing-tests.md)).
4. Run them:

   ```bash
   vendor/bin/phpunit                          # the App suite only (defaultTestSuite)
   vendor/bin/phpunit --testsuite EndToEnd     # the End-to-End tests
   vendor/bin/phpunit --testsuite App,EndToEnd # everything
   ```

   Delete `defaultTestSuite="App"` to make `vendor/bin/phpunit` run everything, as in a Symfony application.

pieceofcake2/app is being updated to this configuration.

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains
it. It is not a fork of another project and is not based on one. It builds on
[Symfony Panther](https://github.com/symfony/panther) as a Composer dependency, and it replaces
pieceofcake2/e2e-test.

## License

The MIT License. See [LICENSE](../LICENSE).
