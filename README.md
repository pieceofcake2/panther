# pieceofcake2/panther

[![GitHub License](https://img.shields.io/github/license/pieceofcake2/panther?label=License)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/pieceofcake2/panther?label=Packagist)](https://packagist.org/packages/pieceofcake2/panther)
![PHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/panther/php?logo=php&logoColor=%23FFFFFF&label=PHP&labelColor=%23777BB4&color=%23FFFFFF)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen?label=PHPStan)](https://github.com/pieceofcake2/panther/actions/workflows/CI.yml)
![CakePHP](https://img.shields.io/packagist/dependency-v/pieceofcake2/panther/pieceofcake2/cakephp?logo=cakephp&logoColor=%23FFFFFF&label=CakePHP&labelColor=%23D33C43&color=%23FFFFFF)
[![CI](https://img.shields.io/github/actions/workflow/status/pieceofcake2/panther/CI.yml?label=CI)](https://github.com/pieceofcake2/panther/actions/workflows/CI.yml)
[![Codecov](https://img.shields.io/codecov/c/gh/pieceofcake2/panther?label=Coverage)](https://codecov.io/gh/pieceofcake2/panther)

End-to-End tests for [pieceofcake2/cakephp](https://github.com/pieceofcake2/cakephp) applications with
[Symfony Panther](https://symfony.com/doc/current/testing/end_to_end.html), the way Symfony applications run them.

`PantherTestCase` is a `CakeTestCase` that uses Panther's `PantherTestCaseTrait`: `static::createPantherClient()`
starts your application with PHP's built-in web server and drives it with Chrome, and `$fixtures` work as
usual because the web server uses the test datasource. Everything else is Panther: its client, its
`PANTHER_*` environment variables, its PHPUnit extension and its assertions.

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\EndToEnd;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

class ArticlesTest extends PantherTestCase
{
    /**
     * @var array<string>
     */
    public $fixtures = ['app.article'];

    /**
     * @return void
     */
    public function testIndex(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/articles');

        $this->assertPageTitleContains('Articles');
        $this->assertSelectorTextContains('table', 'First article');
    }
}
```

## Documentation

- Website: https://pieceofcake2.github.io/panther/ (日本語: https://pieceofcake2.github.io/panther/ja/)
- Markdown: [docs/README.md](docs/README.md) (日本語: [docs/ja/README.md](docs/ja/README.md)) (for AI tools and offline reading)

The Markdown documentation is also included in the Composer package, at
`vendor/pieceofcake2/panther/docs/README.md`.

## Requirements

* PHP 8.0+ (Panther 2.1 on PHP 8.0, Panther 2.4 on PHP 8.1+)
* pieceofcake2/cakephp 2.12+
* PHPUnit 9.6
* Google Chrome or Chromium and a matching chromedriver

## Installation

```bash
composer require --dev pieceofcake2/panther dbrekelmans/bdi
vendor/bin/bdi detect drivers
```

It is a library (PSR-4, `PieceOfCake2\Panther\`), not a CakePHP plugin: there is nothing to load with
`CakePlugin::load()`.

## Quick start

1. Register the extension in `phpunit.xml.dist`
   (see [docs/examples/phpunit.xml.dist](docs/examples/phpunit.xml.dist)):

   ```xml
   <php>
       <server name="PANTHER_ERROR_SCREENSHOT_DIR" value="./tmp/error-screenshots"/>
   </php>
   <extensions>
       <extension class="PieceOfCake2\Panther\ServerExtension"/>
   </extensions>
   ```

2. Set `DATABASE_URL` to the test database in `config/.env.test`. The tests run with `APP_ENV=test` by
   default (pieceofcake2/app's `tests/bootstrap.php`), and the web server inherits it, so both load that file, as in Symfony
   (pieceofcake2/app has this setup; see [Test Database](docs/test-database.md)):

   ```dotenv
   # config/.env.test
   DATABASE_URL="mysql://user:password@localhost:3306/test_database_name?encoding=utf8mb4"
   ```

3. Write tests that extend `PieceOfCake2\Panther\TestSuite\PantherTestCase` in `tests/TestCase/EndToEnd/`
   and run them with `vendor/bin/phpunit --testsuite EndToEnd`.

## Reporting Issues

Please open an issue on [GitHub](https://github.com/pieceofcake2/panther/issues).
To report a security vulnerability, use [private vulnerability reporting](https://github.com/pieceofcake2/panther/security/advisories/new).

## Contributing

See the [contribution guidelines](https://github.com/pieceofcake2/.github/blob/master/CONTRIBUTING.md).

## Origin

This package was created by [pieceofcake2](https://github.com/pieceofcake2), which also maintains
it. It is not a fork of another project and is not based on one. It builds on
[Symfony Panther](https://github.com/symfony/panther) as a Composer dependency, and it replaces
pieceofcake2/e2e-test.

## License

The MIT License. See [LICENSE](LICENSE).
