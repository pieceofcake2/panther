<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Example\Report;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * Contact form
 *
 * The tests of the sample report (docs/report.md), on the fixture application of the package. One of them fails
 * and one is skipped on purpose, to show these results in the report.
 */
class ContactFormTest extends PantherTestCase
{
    /**
     * Sends a message and shows what was sent
     */
    public function testSendsAMessage(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/contacts/add');
        $this->takeReportScreenshot('Form', fitToPage: true);

        $client->getCrawler()->filterXPath('//label[normalize-space(.)="I agree to the terms"]')->click();
        $client->submitForm('Send message', [
            'data[Contact][name]' => 'Jane Doe',
            'data[Contact][email]' => 'jane@example.com',
            'data[Contact][topic]' => 'billing',
            'data[Contact][message]' => 'Where is my invoice?',
        ]);

        $this->assertSelectorWillContain('#name', 'Jane Doe');
        $this->assertSelectorTextContains('#topic', 'Billing & invoices');
        $this->takeReportScreenshot('Thanks', fullPage: true);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'no name' => ['', 'jane@example.com', 'Enter your name.'],
            'invalid email address' => ['Jane Doe', 'jane', 'Enter a valid email address.'],
        ];
    }

    /**
     * Shows the error of an invalid field
     *
     * @dataProvider invalidProvider
     */
    public function testShowsTheErrors(string $name, string $email, string $error): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/contacts/add');

        $client->submitForm('Send message', ['data[Contact][name]' => $name, 'data[Contact][email]' => $email]);

        $this->assertSelectorWillContain('.message.error', 'Please correct the errors below.');
        $this->assertSelectorTextContains('form', $error);
    }

    /**
     * Fits the screen of a phone
     */
    public function testFitsAPhone(): void
    {
        $client = static::createPantherClient();
        $driver = $client->getWebDriver();
        $this->assertInstanceOf(RemoteWebDriver::class, $driver);
        $devTools = new ChromeDevToolsDriver($driver);
        $devTools->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 844, 'deviceScaleFactor' => 3, 'mobile' => true]);
        try {
            $client->request('GET', '/contacts/add');
            $this->assertLessThanOrEqual(390, (int)$client->executeScript('return document.documentElement.scrollWidth;'));
            $this->takeReportScreenshot('Phone');
        } finally {
            $devTools->execute('Emulation.clearDeviceMetricsOverride');
        }
    }

    /**
     * Shows the terms (fails on purpose, to show a failure in the sample report)
     */
    public function testShowsTheTerms(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/contacts/add');

        $this->assertSelectorTextContains('form', 'Read the terms', 'The link to the terms is not on the form');
    }

    /**
     * Attaches a file (skipped on purpose, to show a skipped test in the sample report)
     */
    public function testAttachesAFile(): void
    {
        $this->markTestSkipped('The form has no file field yet.');
    }
}
