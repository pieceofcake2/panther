# Writing Tests

## A Test Case

Put the tests in `tests/TestCase/EndToEnd/` (`app/Test/Case/EndToEnd/` in the legacy layout) and extend
`PantherTestCase` ([docs/examples/ArticlesTest.php](examples/ArticlesTest.php)):

```php
<?php
declare(strict_types=1);

namespace App\Test\TestCase\EndToEnd;

use ClassRegistry;
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
    public function testIndexShowsTheFixtureRecords(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/articles');

        $this->assertPageTitleContains('Articles');
        $this->assertSelectorTextContains('table', 'First article');
    }

    /**
     * @return void
     */
    public function testAdd(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/articles/add');

        // FormHelper puts a hidden input before each checkbox: click the checkbox itself
        $crawler->filter('#ArticlePublished')->click();
        $client->submitForm('Submit', [
            'data[Article][title]' => 'Written in the browser',
            'data[Article][body]' => 'Hello',
        ]);

        $this->assertSelectorTextContains('.message', 'The article has been saved.');
        $this->assertSame(1, ClassRegistry::init('Article')->find('count', [
            'conditions' => ['Article.title' => 'Written in the browser', 'Article.published' => true],
        ]));
    }
}
```

`static::createPantherClient()` returns the browser. The first call of the run starts the web server and
Chrome; the following calls return the same browser.

## Opening pages

```php
$client = static::createPantherClient();
$crawler = $client->request('GET', '/articles?page=2');   // a path of the application
$crawler = $client->clickLink('Next');
$client->back();

$client->getCurrentURL();                                   // http://127.0.0.1:9080/articles?page=2
$crawler->filter('h1')->text();                             // the visible text
$client->executeScript('return document.title;');
$client->takeScreenshot('tmp/articles.png');
```

The client is a BrowserKit client and the crawler a DomCrawler crawler whose elements are WebDriver elements:
see [Panther's documentation](https://symfony.com/doc/current/testing/end_to_end.html#usage).

## Forms

`submitForm()` fills the fields of the form that contains the button, by their `name`, and submits it:

```php
$client->submitForm('Log in', [
    'data[User][email]' => 'user@example.com',
    'data[User][password]' => 'secret',
]);
```

Give the full CakePHP names, such as `data[User][email]`; nested arrays are not supported. For a checkbox
of `FormHelper`, which puts a hidden input with the same name before it, click the checkbox before the
submit: `$crawler->filter('#UserRememberMe')->click()`. `SecurityComponent` tokens work, because the
browser submits the form.

## Waiting for JavaScript

```php
$crawler->filter('#load-more')->click();
$client->waitFor('.comment');                          // in the DOM
$client->waitForVisibility('#dialog');
$client->waitForElementToContain('#total', '3 items');

// or as assertions
$this->assertSelectorWillContain('#total', '3 items');
$this->assertSelectorWillNotBeVisible('.spinner');
```

## Cookies and logging in

The browser is kept from one test to the next, with its cookies, as in Panther. A test that must start logged
out clears them first:

```php
$client = static::createPantherClient();
$client->request('GET', '/');
$client->getCookieJar()->clear();
```

Log in through the login form in a helper of your base class, or keep a test logged in on purpose for the
tests that follow.

## Several browsers

`static::createAdditionalPantherClient()` opens another, isolated browser, for example a second user of a
chat. The assertions check the browser created last.

## Mobile viewport

Panther has no device presets, and headless Chrome does not make its window narrower than 500 pixels with
`--window-size`. Emulate a phone with the DevTools protocol of php-webdriver, and reset it at the end
because the browser is kept for the next test:

```php
use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;

$client = static::createPantherClient();
$devTools = new ChromeDevToolsDriver($client->getWebDriver());
$devTools->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 844, 'deviceScaleFactor' => 3, 'mobile' => true]);
try {
    $client->request('GET', '/');
    $this->assertSelectorIsVisible('.menu-toggle');
} finally {
    $devTools->execute('Emulation.clearDeviceMetricsOverride');
}
```

`Emulation.setUserAgentOverride` and `Emulation.setTouchEmulationEnabled` change the User-Agent and the
touch events the same way.

## Assertions

`PantherTestCase` has the assertions of Symfony's and Panther's `WebTestAssertionsTrait`. They check the page
of the browser created last. A locator is a CSS selector, or an XPath expression that starts with `/`.

| Assertion | Checks |
| --- | --- |
| `assertSelectorExists($selector)`, `assertSelectorNotExists($selector)` | An element matches |
| `assertSelectorCount($count, $selector)` | The number of matching elements |
| `assertSelectorTextContains($selector, $text)`, `assertSelectorTextNotContains()`, `assertSelectorTextSame()` | The visible text of the first matching element |
| `assertPageTitleSame($title)`, `assertPageTitleContains($title)` | `<title>` |
| `assertInputValueSame($name, $value)`, `assertInputValueNotSame()` | The current value of a field, by its name, e.g. `data[Article][title]` |
| `assertCheckboxChecked($name)`, `assertCheckboxNotChecked($name)` | A checkbox, by its name |
| `assertBrowserHasCookie($name)`, `assertBrowserNotHasCookie($name)`, `assertBrowserCookieValueSame($name, $value)` | A cookie of the browser |
| `assertSelectorIsVisible($locator)`, `assertSelectorIsNotVisible()` | Visibility of the first matching element |
| `assertSelectorIsEnabled($locator)`, `assertSelectorIsDisabled()` | Whether it is enabled |
| `assertSelectorAttributeContains($locator, $attribute, $text)`, `assertSelectorAttributeNotContains()` | An attribute; `$text = null` checks that the attribute is not set |
| `assertSelectorWillExist()`, `assertSelectorWillNotExist()`, `assertSelectorWillBeVisible()`, `assertSelectorWillNotBeVisible()`, `assertSelectorWillContain()`, `assertSelectorWillNotContain()`, `assertSelectorWillBeEnabled()`, `assertSelectorWillBeDisabled()`, `assertSelectorAttributeWillContain()`, `assertSelectorAttributeWillNotContain()` | The same, after waiting for it (Panther's `waitFor*()`) |

The response assertions of Symfony (`assertResponseIsSuccessful()` ...) do not exist: WebDriver does not
expose the status code. Use `IntegrationTestTrait` for them.

## Error screenshots and the interactive mode

The [test report](report.md) has a screenshot of every test, failed or not. To also save a PNG file for each
failed test, set `PANTHER_ERROR_SCREENSHOT_DIR` (it is not set by default): with it and the extension, a test that
fails or errors after it created a browser saves a screenshot of each browser, named after the date, the kind of
failure and the test:

![Screenshot saved for a failed test](img/failure-screenshot.png)

With `PANTHER_NO_HEADLESS=1` and PHPUnit's `--debug`, the run pauses after a failure until you press Enter,
so you can look at the browser:

```bash
PANTHER_NO_HEADLESS=1 vendor/bin/phpunit --testsuite EndToEnd --debug
```

## API reference

### PantherTestCase

`PieceOfCake2\Panther\TestSuite\PantherTestCase` extends `CakeTestCase` and uses Panther's
`PantherTestCaseTrait` and this package's `WebTestAssertionsTrait`.

| Member | Description |
| --- | --- |
| `static createPantherClient(array $options = [], array $kernelOptions = [], array $managerOptions = [])` | The browser; starts the web server and the browser at the first call (Panther) |
| `static createAdditionalPantherClient()` | Another, isolated browser (Panther) |
| `static createHttpBrowserClient(array $options = [])` | A client without JavaScript, through HTTP (Panther) |
| `static startWebServer(array $options = [])` | Starts the web server with the CakePHP defaults, then as Panther does |
| `static stopWebServer()`, `static isWebServerStarted()` | Panther |
| `static defaultWebServerDir(?string $projectRoot = null)` | `webroot/` or `app/webroot/` of the project (protected) |
| `tearDown()` | `CakeTestCase::tearDown()`, then the screenshot of a failed test |
| `ROUTER` | Path of the CakePHP router script |
| `CHROME`, `FIREFOX`, `SELENIUM` | Values of the `browser` option |

### WebTestAssertionsTrait

`PieceOfCake2\Panther\TestSuite\WebTestAssertionsTrait`: the [assertions](#assertions). Use it in a class
that uses `PantherTestCaseTrait`.

### ServerExtension

`PieceOfCake2\Panther\ServerExtension` does what Panther's `ServerExtension` does: it keeps the web server
and the browser until the last test, takes the screenshots of failed tests and pauses in the interactive mode,
and it stops the web server and the browser of `PantherTestCase` after the last test. Register it instead of
Panther's extension.
