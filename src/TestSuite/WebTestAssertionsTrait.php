<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\TestSuite;

use Facebook\WebDriver\WebDriverElement;
use LogicException;
use Symfony\Component\Panther\Client;

/**
 * The assertions of Symfony's and Panther's `WebTestAssertionsTrait` for the Panther client, without the
 * Symfony FrameworkBundle.
 *
 * They check the page of the client created last by `createPantherClient()` or `createAdditionalPantherClient()`.
 * Locators are CSS selectors, or XPath expressions that start with `/`, as in Panther.
 *
 * Requires `PantherTestCaseTrait` in the class that uses it (`PantherTestCase` does).
 */
trait WebTestAssertionsTrait
{
    /**
     * @param string $selector
     * @param string $message
     * @return void
     */
    public static function assertSelectorExists(string $selector, string $message = ''): void
    {
        self::assertNotEmpty(self::findElements($selector), $message ?: sprintf('Failed asserting that the page has an element matching "%s".', $selector));
    }

    /**
     * @param string $selector
     * @param string $message
     * @return void
     */
    public static function assertSelectorNotExists(string $selector, string $message = ''): void
    {
        self::assertEmpty(self::findElements($selector), $message ?: sprintf('Failed asserting that the page has no element matching "%s".', $selector));
    }

    /**
     * @param int $expectedCount
     * @param string $selector
     * @param string $message
     * @return void
     */
    public static function assertSelectorCount(int $expectedCount, string $selector, string $message = ''): void
    {
        self::assertCount($expectedCount, self::findElements($selector), $message ?: sprintf('Failed asserting that the page has %d element(s) matching "%s".', $expectedCount, $selector));
    }

    /**
     * Asserts that the first element matching the selector contains a text (the visible text of the element).
     *
     * @param string $selector
     * @param string $text
     * @param string $message
     * @return void
     */
    public static function assertSelectorTextContains(string $selector, string $text, string $message = ''): void
    {
        self::assertStringContainsString($text, self::getText($selector), $message);
    }

    /**
     * @param string $selector
     * @param string $text
     * @param string $message
     * @return void
     */
    public static function assertSelectorTextNotContains(string $selector, string $text, string $message = ''): void
    {
        self::assertStringNotContainsString($text, self::getText($selector), $message);
    }

    /**
     * @param string $selector
     * @param string $text
     * @param string $message
     * @return void
     */
    public static function assertSelectorTextSame(string $selector, string $text, string $message = ''): void
    {
        self::assertSame($text, self::getText($selector), $message);
    }

    /**
     * @param string $expectedTitle
     * @param string $message
     * @return void
     */
    public static function assertPageTitleSame(string $expectedTitle, string $message = ''): void
    {
        self::assertSame($expectedTitle, self::getPantherClient()->getTitle(), $message);
    }

    /**
     * @param string $expectedTitle
     * @param string $message
     * @return void
     */
    public static function assertPageTitleContains(string $expectedTitle, string $message = ''): void
    {
        self::assertStringContainsString($expectedTitle, (string)self::getPantherClient()->getTitle(), $message);
    }

    /**
     * Asserts the current value of the input named `$fieldName`, e.g. `data[Article][title]`.
     *
     * @param string $fieldName
     * @param string $expectedValue
     * @param string $message
     * @return void
     */
    public static function assertInputValueSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        self::assertSame($expectedValue, self::findInput($fieldName)->getAttribute('value'), $message);
    }

    /**
     * @param string $fieldName
     * @param string $expectedValue
     * @param string $message
     * @return void
     */
    public static function assertInputValueNotSame(string $fieldName, string $expectedValue, string $message = ''): void
    {
        self::assertNotSame($expectedValue, self::findInput($fieldName)->getAttribute('value'), $message);
    }

    /**
     * @param string $fieldName
     * @param string $message
     * @return void
     */
    public static function assertCheckboxChecked(string $fieldName, string $message = ''): void
    {
        self::assertTrue(self::findInput($fieldName, 'checkbox')->isSelected(), $message ?: sprintf('Failed asserting that the checkbox "%s" is checked.', $fieldName));
    }

    /**
     * @param string $fieldName
     * @param string $message
     * @return void
     */
    public static function assertCheckboxNotChecked(string $fieldName, string $message = ''): void
    {
        self::assertFalse(self::findInput($fieldName, 'checkbox')->isSelected(), $message ?: sprintf('Failed asserting that the checkbox "%s" is not checked.', $fieldName));
    }

    /**
     * @param string $name
     * @param string $path
     * @param string|null $domain
     * @param string $message
     * @return void
     */
    public static function assertBrowserHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        self::assertNotNull(self::getPantherClient()->getCookieJar()->get($name, $path, $domain), $message ?: sprintf('Failed asserting that the browser has the cookie "%s".', $name));
    }

    /**
     * @param string $name
     * @param string $path
     * @param string|null $domain
     * @param string $message
     * @return void
     */
    public static function assertBrowserNotHasCookie(string $name, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        self::assertNull(self::getPantherClient()->getCookieJar()->get($name, $path, $domain), $message ?: sprintf('Failed asserting that the browser does not have the cookie "%s".', $name));
    }

    /**
     * @param string $name
     * @param string $expectedValue
     * @param bool $raw Compare the raw (URL-encoded) value
     * @param string $path
     * @param string|null $domain
     * @param string $message
     * @return void
     */
    public static function assertBrowserCookieValueSame(string $name, string $expectedValue, bool $raw = false, string $path = '/', ?string $domain = null, string $message = ''): void
    {
        $cookie = self::getPantherClient()->getCookieJar()->get($name, $path, $domain);
        self::assertNotNull($cookie, $message ?: sprintf('Failed asserting that the browser has the cookie "%s".', $name));
        self::assertSame($expectedValue, $raw ? $cookie->getRawValue() : $cookie->getValue(), $message);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillExist(string $locator): void
    {
        self::getPantherClient()->waitFor($locator);
        self::assertSelectorExists($locator);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillNotExist(string $locator): void
    {
        self::getPantherClient()->waitForStaleness($locator);
        self::assertSelectorNotExists($locator);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorIsVisible(string $locator): void
    {
        self::assertTrue(self::findElement($locator)->isDisplayed(), 'Failed asserting that element is visible.');
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillBeVisible(string $locator): void
    {
        self::getPantherClient()->waitForVisibility($locator);
        self::assertSelectorIsVisible($locator);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorIsNotVisible(string $locator): void
    {
        self::assertFalse(self::findElement($locator)->isDisplayed(), 'Failed asserting that element is not visible.');
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillNotBeVisible(string $locator): void
    {
        self::getPantherClient()->waitForInvisibility($locator);
        self::assertSelectorIsNotVisible($locator);
    }

    /**
     * @param string $locator
     * @param string $text
     * @return void
     */
    public static function assertSelectorWillContain(string $locator, string $text): void
    {
        self::getPantherClient()->waitForElementToContain($locator, $text);
        self::assertSelectorTextContains($locator, $text);
    }

    /**
     * @param string $locator
     * @param string $text
     * @return void
     */
    public static function assertSelectorWillNotContain(string $locator, string $text): void
    {
        self::getPantherClient()->waitForElementToNotContain($locator, $text);
        self::assertSelectorTextNotContains($locator, $text);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorIsEnabled(string $locator): void
    {
        self::assertTrue(self::findElement($locator)->isEnabled(), 'Failed asserting that element is enabled.');
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillBeEnabled(string $locator): void
    {
        self::getPantherClient()->waitForEnabled($locator);
        self::assertSelectorIsEnabled($locator);
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorIsDisabled(string $locator): void
    {
        self::assertFalse(self::findElement($locator)->isEnabled(), 'Failed asserting that element is disabled.');
    }

    /**
     * @param string $locator
     * @return void
     */
    public static function assertSelectorWillBeDisabled(string $locator): void
    {
        self::getPantherClient()->waitForDisabled($locator);
        self::assertSelectorIsDisabled($locator);
    }

    /**
     * Asserts that an attribute contains a text, or, with `$text = null`, that the attribute is not set.
     *
     * @param string $locator
     * @param string $attribute
     * @param string|null $text
     * @return void
     */
    public static function assertSelectorAttributeContains(string $locator, string $attribute, ?string $text = null): void
    {
        if ($text === null) {
            self::assertNull(self::getAttribute($locator, $attribute));

            return;
        }

        self::assertStringContainsString($text, (string)self::getAttribute($locator, $attribute));
    }

    /**
     * @param string $locator
     * @param string $attribute
     * @param string $text
     * @return void
     */
    public static function assertSelectorAttributeWillContain(string $locator, string $attribute, string $text): void
    {
        self::getPantherClient()->waitForAttributeToContain($locator, $attribute, $text);
        self::assertSelectorAttributeContains($locator, $attribute, $text);
    }

    /**
     * @param string $locator
     * @param string $attribute
     * @param string $text
     * @return void
     */
    public static function assertSelectorAttributeNotContains(string $locator, string $attribute, string $text): void
    {
        self::assertStringNotContainsString($text, (string)self::getAttribute($locator, $attribute));
    }

    /**
     * @param string $locator
     * @param string $attribute
     * @param string $text
     * @return void
     */
    public static function assertSelectorAttributeWillNotContain(string $locator, string $attribute, string $text): void
    {
        self::getPantherClient()->waitForAttributeToNotContain($locator, $attribute, $text);
        self::assertSelectorAttributeNotContains($locator, $attribute, $text);
    }

    /**
     * The client the assertions check: the one created last.
     *
     * @return \Symfony\Component\Panther\Client
     * @throws \LogicException When no client has been created
     */
    private static function getPantherClient(): Client
    {
        if (self::$pantherClient === null) {
            throw new LogicException('Create a client with static::createPantherClient() before using the assertions.');
        }

        return self::$pantherClient;
    }

    /**
     * @param string $locator CSS selector or XPath expression
     * @return array<\Facebook\WebDriver\WebDriverElement>
     */
    private static function findElements(string $locator): array
    {
        $client = self::getPantherClient();

        return $client->findElements($client::createWebDriverByFromLocator($locator));
    }

    /**
     * @param string $locator CSS selector or XPath expression
     * @return \Facebook\WebDriver\WebDriverElement
     */
    private static function findElement(string $locator): WebDriverElement
    {
        $client = self::getPantherClient();

        return $client->findElement($client::createWebDriverByFromLocator($locator));
    }

    /**
     * Finds a form field by its name. CakePHP's `FormHelper` adds a hidden input with the same name before a
     * checkbox, so a visible field wins over a hidden one.
     *
     * @param string $fieldName
     * @param string|null $type `checkbox` to find only checkboxes
     * @return \Facebook\WebDriver\WebDriverElement
     */
    private static function findInput(string $fieldName, ?string $type = null): WebDriverElement
    {
        $name = '[name="' . addcslashes($fieldName, '"\\') . '"]';
        if ($type !== null) {
            return self::findElement(sprintf('input[type="%s"]%s', $type, $name));
        }

        $elements = self::findElements($name . ':not([type="hidden"])');

        return $elements[0] ?? self::findElement($name);
    }

    /**
     * @param string $locator
     * @return string
     */
    private static function getText(string $locator): string
    {
        return self::findElement($locator)->getText();
    }

    /**
     * @param string $locator
     * @param string $attribute
     * @return string|null
     */
    private static function getAttribute(string $locator, string $attribute): ?string
    {
        return self::findElement($locator)->getAttribute($attribute);
    }
}
