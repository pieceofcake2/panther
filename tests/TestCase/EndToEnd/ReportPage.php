<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * Pages of the fixture application for the report, run by ReportTest. PHPUnit does not collect them: the file
 * name does not end with Test.php.
 */
class ReportPage extends PantherTestCase
{
    /**
     * The page of the fixture application, made taller than the viewport: a blue block, then a red one
     */
    private const TALL_PAGE = 'document.body.innerHTML = \'<div style="height: 3000px; background: #0000ff"></div><div id="target" style="height: 2000px; background: #ff0000"></div>\';'
        . 'document.body.style.margin = "0";';

    /**
     * Shows the top page
     */
    public function testEnd(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Welcome');
    }

    /**
     * Takes the screenshots of its steps
     */
    public function testSteps(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');
        $this->takeReportScreenshot('Top page');

        $client->executeScript(self::TALL_PAGE);
        $this->takeReportScreenshot('Red block', '#target');
        $this->takeReportScreenshot('Whole page', null, true);
        $this->takeReportScreenshot('A page of the report', null, false, true);
        $this->takeReportScreenshot('A page from the red block', '#target', false, true);

        $this->assertSelectorExists('#target');
    }

    /**
     * Fails on the top page
     */
    public function testFails(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->assertSelectorTextContains('h1', 'Not on the page');
    }

    /**
     * Shows the top page on a phone (3 pixels per CSS pixel)
     */
    public function testMobile(): void
    {
        $client = static::createPantherClient();
        $driver = $client->getWebDriver();
        $this->assertInstanceOf(RemoteWebDriver::class, $driver);
        $devTools = new ChromeDevToolsDriver($driver);
        $devTools->execute('Emulation.setDeviceMetricsOverride', ['width' => 390, 'height' => 844, 'deviceScaleFactor' => 3, 'mobile' => true]);
        try {
            $client->request('GET', '/');
            $this->assertSame(390, (int)$client->executeScript('return window.innerWidth;'));
            $this->takeReportScreenshot('Phone');
        } finally {
            $devTools->execute('Emulation.clearDeviceMetricsOverride');
        }
    }

    /**
     * Is skipped after it opened a page
     */
    public function testSkipped(): void
    {
        $client = static::createPantherClient();
        $client->request('GET', '/');

        $this->markTestSkipped('Not ready');
    }

    /**
     * Uses no browser
     */
    public function testWithoutBrowser(): void
    {
        $this->addToAssertionCount(1);
    }
}
