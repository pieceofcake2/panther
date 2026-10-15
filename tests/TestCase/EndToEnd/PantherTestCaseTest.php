<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use LogicException;
use PHPUnit\Framework\AssertionFailedError;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * The web server, the CakePHP router script and the assertions, against the fixture application in tests/test_app
 */
class PantherTestCaseTest extends PantherTestCase
{
    public function testWebServer(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertTrue(static::isWebServerStarted());
        $this->assertSame('http://127.0.0.1:9080/', $client->getCurrentURL());
        $this->assertPageTitleSame('Panther fixture');
    }

    public function testRouterServesFilesOfTheDocumentRoot(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $stylesheets = $client->executeScript(
            'return Array.from(document.styleSheets).filter(function (s) { return s.cssRules.length > 0; }).length;',
        );
        $this->assertSame(1, $stylesheets);
        $this->assertSelectorAttributeContains('#ajax-result', 'id', 'ajax-result');
    }

    public function testRouterHandsUrlsWithAnExtensionToCakePhp(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/index.html');

        $this->assertPageTitleSame('Panther fixture');
    }

    public function testRouterKeepsTheCacheOfTheWebServerApart(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $cache = dirname(__DIR__, 2) . DS . 'test_app' . DS . 'tmp' . DS . 'cache' . DS . 'panther' . DS;
        $this->assertDirectoryExists($cache . 'models');
        $this->assertNotEmpty(glob($cache . 'persistent' . DS . '*') ?: []);
    }

    public function testCakePhpForm(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/contacts/add');
        $this->assertCheckboxNotChecked('data[Contact][newsletter]');
        $this->assertInputValueSame('data[Contact][topic]', 'general');

        // FormHelper puts a hidden input with the same name before a checkbox: click the checkbox itself
        $crawler->filter('#ContactNewsletter')->click();
        $client->submitForm('Send message', [
            'data[Contact][name]' => 'Jane Doe',
            'data[Contact][email]' => 'not an email',
            'data[Contact][topic]' => 'billing',
        ]);
        // Wait for the page that the post returns
        $client->waitFor('.message.error');

        $this->assertSelectorTextContains('.message.error', 'Please correct the errors below.');
        $this->assertSelectorTextContains('.error-message', 'Enter a valid email address.');
        $this->assertInputValueSame('data[Contact][name]', 'Jane Doe');
        $this->assertInputValueSame('data[Contact][topic]', 'billing');
        $this->assertCheckboxChecked('data[Contact][newsletter]');

        $client->refreshCrawler()->filter('#ContactAgree')->click();
        $client->submitForm('Send message', ['data[Contact][email]' => 'jane@example.com']);
        // The browser follows the redirect after the post; wait for the thanks page
        $client->waitFor('#name');

        $this->assertSame('/contacts/thanks', parse_url((string)$client->getCurrentURL(), PHP_URL_PATH));
        $this->assertSelectorTextSame('#name', 'Jane Doe');
        $this->assertSelectorTextSame('#topic', 'Billing & invoices');
        $this->assertSelectorTextSame('#newsletter', 'yes');
    }

    public function testAssertions(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertPageTitleContains('fixture');
        $this->assertSelectorExists('h1');
        $this->assertSelectorExists('//ul/li/a');
        $this->assertSelectorNotExists('table');
        $this->assertSelectorCount(3, 'ul li');
        $this->assertSelectorTextContains('h1', 'Welcome');
        $this->assertSelectorTextNotContains('h1', 'Goodbye');
        $this->assertSelectorTextSame('h1', 'Welcome to the fixture app');
        $this->assertSelectorIsVisible('h1');
        $this->assertSelectorIsNotVisible('.hidden-text');
        $this->assertSelectorIsEnabled('#load-button');
        $this->assertSelectorIsDisabled('#next-button');
        $this->assertSelectorAttributeContains('#load-button', 'data-state', 'idle');
        $this->assertSelectorAttributeNotContains('#load-button', 'data-state', 'done');
        $this->assertSelectorAttributeContains('#load-button', 'disabled', null);

        $client->clickLink('Remember me');
        $this->assertBrowserHasCookie('remember_me');
        $this->assertBrowserCookieValueSame('remember_me', 'yes');
        $this->assertBrowserNotHasCookie('forget_me');
        $client->getCookieJar()->clear();
        $this->assertBrowserNotHasCookie('remember_me');
    }

    public function testAssertionsWait(): void
    {
        $client = static::createPantherClient();
        $crawler = $client->request('GET', '/');

        $crawler->filter('#load-button')->click();
        $this->assertSelectorWillBeVisible('#spinner');
        $this->assertSelectorAttributeWillContain('#load-button', 'data-state', 'done');
        $this->assertSelectorWillContain('#ajax-result', 'Loaded by AJAX');
        $this->assertSelectorWillNotBeVisible('#spinner');
        $this->assertSelectorWillBeEnabled('#next-button');
        $this->assertSelectorAttributeWillNotContain('#load-button', 'data-state', 'loading');
        $this->assertSelectorWillExist('#ajax-result');

        $client->executeScript('document.getElementById("next-button").disabled = true; document.getElementById("ajax-result").textContent = "";');
        $this->assertSelectorWillBeDisabled('#next-button');
        $this->assertSelectorWillNotContain('#ajax-result', 'Loaded by AJAX');

        $client->executeScript('setTimeout(function () { document.getElementById("spinner").remove(); }, 200);');
        $this->assertSelectorWillNotExist('#spinner');
    }

    public function testAssertionsFail(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $assertions = [
            fn() => $this->assertSelectorExists('table'),
            fn() => $this->assertSelectorNotExists('h1'),
            fn() => $this->assertSelectorCount(1, 'ul li'),
            fn() => $this->assertSelectorTextContains('h1', 'Goodbye'),
            fn() => $this->assertPageTitleSame('Other'),
            fn() => $this->assertSelectorIsVisible('.hidden-text'),
            fn() => $this->assertSelectorIsDisabled('#load-button'),
            fn() => $this->assertBrowserHasCookie('missing'),
        ];
        foreach ($assertions as $i => $assertion) {
            try {
                $assertion();
            } catch (AssertionFailedError $e) {
                continue;
            }
            $this->fail("Assertion #{$i} did not fail.");
        }
        $this->addToAssertionCount(1);
    }

    public function testAdditionalClient(): void
    {
        $first = static::createPantherClient();
        $first->request('GET', '/');
        $second = static::createAdditionalPantherClient();
        $second->request('GET', '/contacts/add');

        $this->assertNotSame($first, $second);
        // The assertions check the client created last
        $this->assertPageTitleSame('Contact us');
        $this->assertSame('Panther fixture', $first->getTitle());

        static::stopWebServer();
        $this->assertFalse(static::isWebServerStarted());
    }

    public function testAssertionsNeedAClient(): void
    {
        static::stopWebServer();

        $this->expectException(LogicException::class);
        $this->assertSelectorExists('h1');
    }
}
