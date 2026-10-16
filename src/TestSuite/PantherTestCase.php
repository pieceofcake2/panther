<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\TestSuite;

use CakeTestCase;
use Composer\InstalledVersions;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\IncompleteTest;
use PHPUnit\Framework\SkippedTest;
use PHPUnit\Runner\BaseTestRunner;
use PieceOfCake2\Panther\Report\Recorder;
use PieceOfCake2\Panther\Report\Screenshot;
use PieceOfCake2\Panther\Report\TestResult;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\Panther\PantherTestCaseTrait;
use Symfony\Component\Panther\ServerExtensionLegacy;
use Throwable;

/**
 * Base class for End-to-End tests of a CakePHP 2 application with Symfony Panther.
 *
 * It is a `CakeTestCase` that uses Panther's `PantherTestCaseTrait`, so `$fixtures` work as usual and
 * `static::createPantherClient()` starts the application with PHP's built-in web server and a Chrome browser,
 * as in Symfony's `PantherTestCase`:
 *
 * ```
 * class MessagesTest extends PantherTestCase
 * {
 *     public $fixtures = ['app.message'];
 *
 *     public function testIndex(): void
 *     {
 *         $client = static::createPantherClient();
 *         $client->request('GET', '/messages');
 *
 *         $this->assertSelectorTextContains('#messages', 'Hello');
 *     }
 * }
 * ```
 *
 * On top of Panther, for CakePHP 2:
 *
 * - The web server directory defaults to `webroot/` (or `app/webroot/` for the legacy layout) of the project
 * - The web server router defaults to the CakePHP router script of this package (`resources/router.php`)
 * - The assertions of Panther's `WebTestAssertionsTrait`, without the Symfony FrameworkBundle
 *
 * Every other setting is Panther's: `PANTHER_WEB_SERVER_DIR`, `PANTHER_WEB_SERVER_PORT`,
 * `PANTHER_WEB_SERVER_ROUTER`, `PANTHER_EXTERNAL_BASE_URI`, `PANTHER_ERROR_SCREENSHOT_DIR`, `PANTHER_NO_HEADLESS`,
 * `PANTHER_CHROME_ARGUMENTS` and the others, and the options of `createPantherClient()`.
 */
abstract class PantherTestCase extends CakeTestCase
{
    use PantherTestCaseTrait {
        startWebServer as private startPantherWebServer;
    }
    use WebTestAssertionsTrait;

    public const CHROME = 'chrome';
    public const FIREFOX = 'firefox';
    public const SELENIUM = 'selenium';

    /**
     * The CakePHP router script of the PHP built-in web server
     */
    public const ROUTER = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'router.php';

    /**
     * Takes a screenshot of the browsers when the test failed (`PANTHER_ERROR_SCREENSHOT_DIR`), like Panther.
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();
        $this->takeScreenshotIfTestFailed();
    }

    /**
     * Takes the screenshots of a failed test like Panther, with a file name that can be uploaded as a CI artifact.
     *
     * Panther names the file after the test, and the name of a test with a data provider contains the data set
     * in quotes (`with data set "name"`). `"`, `<`, `>`, `|`, `*`, `?` and line breaks are not allowed in the
     * file names of GitHub Actions artifacts (or on Windows), so they are replaced with `_`.
     *
     * @return void
     */
    public function takeScreenshotIfTestFailed(): void
    {
        $status = $this->getStatus();
        if ($status !== BaseTestRunner::STATUS_ERROR && $status !== BaseTestRunner::STATUS_FAILURE) {
            return;
        }

        ServerExtensionLegacy::takeScreenshots(
            $status === BaseTestRunner::STATUS_ERROR ? 'error' : 'failure',
            static::screenshotName($this->toString()),
        );
    }

    /**
     * Runs the test, and takes the screenshot of its end for the report (`PANTHER_REPORT_PDF`).
     *
     * The screenshot is taken here, before `tearDown()`, which may change the browser (e.g. reset a mobile
     * emulation): the screen of a failed test, or of a passed test that took no screenshot of its own.
     *
     * @return mixed
     * @throws \Throwable
     */
    protected function runTest()
    {
        $recorder = Recorder::current();
        if ($recorder === null) {
            return parent::runTest();
        }

        $result = $recorder->begin($this);
        try {
            $return = parent::runTest();
        } catch (SkippedTest | IncompleteTest $e) {
            throw $e;
        } catch (Throwable $e) {
            // PHPUnit tells the extension the result later, but not for a test run inside another test
            $result->status = $e instanceof AssertionFailedError ? TestResult::FAILED : TestResult::ERROR;
            $recorder->setException($result->name, $e, (new ReflectionClass($this))->getFileName() ?: null);
            $this->takeEndScreenshot($recorder, $result->name, Screenshot::FAILURE);
            throw $e;
        }
        if ($result->screenshots === []) {
            $this->takeEndScreenshot($recorder, $result->name, Screenshot::END);
        }

        return $return;
    }

    /**
     * Takes a screenshot of a step for the report (`PANTHER_REPORT_PDF`); it does nothing without the report.
     *
     * ```
     * $client->submitForm('Confirm', $data);
     * $this->takeReportScreenshot('Confirmation');
     * $this->takeReportScreenshot('Errors', element: '.error-message');
     * $this->takeReportScreenshot('Whole page', fullPage: true);
     * ```
     *
     * A test that takes screenshots this way gets no screenshot of its end when it passes.
     *
     * @param string $name Name of the step in the report
     * @param string|null $element CSS selector or XPath of an element to scroll to the top before taking the viewport
     * @param bool $fullPage Takes the whole page (in parts of a page of the report) instead of the viewport
     * @return void
     */
    public function takeReportScreenshot(string $name, ?string $element = null, bool $fullPage = false): void
    {
        $recorder = Recorder::current();
        if ($recorder === null) {
            return;
        }

        $recorder->takeScreenshots($this->toString(), self::clientsOfTest(), $name, null, $element, $fullPage);
    }

    /**
     * Takes the screenshot of the end of the test for the report; a broken browser does not hide the result
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorder
     * @param string $name Name of the test
     * @param string $type `Screenshot::END` or `Screenshot::FAILURE`
     * @return void
     */
    private function takeEndScreenshot(Recorder $recorder, string $name, string $type): void
    {
        $clients = self::clientsOfTest();
        if ($clients === []) {
            return;
        }

        try {
            $recorder->takeScreenshots($name, $clients, null, $type);
        } catch (Throwable $e) {
            fwrite(STDERR, sprintf("\nThe screenshot of %s for the report failed: %s\n", $name, $e->getMessage()));
        }
    }

    /**
     * The clients that the test has used, as for Panther's screenshots of failed tests
     *
     * The browser keeps running from test to test with the extension: a test that does not use it gets no screenshot.
     *
     * @return array<\Symfony\Component\Panther\Client>
     */
    private static function clientsOfTest(): array
    {
        $property = new ReflectionProperty(ServerExtensionLegacy::class, 'registeredClients');
        if (PHP_VERSION_ID < 80100) {
            // Needed on PHP 8.0 only, deprecated on PHP 8.5
            $property->setAccessible(true);
        }
        $clients = $property->getValue();

        return is_array($clients) ? array_values($clients) : [];
    }

    /**
     * The test name used in the file name of a screenshot
     *
     * @param string $test Name of the test, e.g. `App\FooTest::testBar with data set "baz"`
     * @return string
     */
    public static function screenshotName(string $test): string
    {
        return (string)preg_replace('/["<>|*?\r\n\/]/', '_', $test);
    }

    /**
     * Starts the PHP built-in web server with the CakePHP defaults, then as Panther does.
     *
     * @param array<string, mixed> $options See `PantherTestCaseTrait::$defaultOptions`
     * @return void
     */
    public static function startWebServer(array $options = []): void
    {
        if (!isset($options['webServerDir']) && static::$webServerDir === null && !isset($_SERVER['PANTHER_WEB_SERVER_DIR'])) {
            $options['webServerDir'] = static::defaultWebServerDir();
        }
        $options['router'] ??= $_SERVER['PANTHER_WEB_SERVER_ROUTER'] ?? (realpath(static::ROUTER) ?: static::ROUTER);

        self::startPantherWebServer($options);
    }

    /**
     * The web server directory used when neither the `webServerDir` option nor `PANTHER_WEB_SERVER_DIR` is set:
     * `webroot/` of the project, or `app/webroot/` when only that one exists (the legacy layout).
     *
     * @param string|null $projectRoot Default: the Composer root package
     * @return string
     */
    protected static function defaultWebServerDir(?string $projectRoot = null): string
    {
        if ($projectRoot === null) {
            $installPath = (string)InstalledVersions::getRootPackage()['install_path'];
            $projectRoot = realpath($installPath) ?: $installPath;
        }
        $projectRoot = rtrim($projectRoot, '/\\');
        $legacy = $projectRoot . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'webroot';
        if (!is_dir($projectRoot . DIRECTORY_SEPARATOR . 'webroot') && is_dir($legacy)) {
            return $legacy;
        }

        return $projectRoot . DIRECTORY_SEPARATOR . 'webroot';
    }
}
