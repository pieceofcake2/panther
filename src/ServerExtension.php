<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther;

use PHPUnit\Runner\AfterIncompleteTestHook;
use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterRiskyTestHook;
use PHPUnit\Runner\AfterSkippedTestHook;
use PHPUnit\Runner\AfterSuccessfulTestHook;
use PHPUnit\Runner\AfterTestErrorHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\AfterTestWarningHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\BeforeTestHook;
use PieceOfCake2\Panther\Report\Config;
use PieceOfCake2\Panther\Report\Recorder;
use PieceOfCake2\Panther\Report\ReportWriter;
use PieceOfCake2\Panther\Report\TestResult;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;
use Symfony\Component\Panther\ServerExtensionLegacy;
use Throwable;

/**
 * The PHPUnit extension of Symfony Panther (`Symfony\Component\Panther\ServerExtension`) for `PantherTestCase`.
 *
 * ```
 * <extensions>
 *     <extension class="PieceOfCake2\Panther\ServerExtension"/>
 * </extensions>
 * ```
 *
 * It does what Panther's extension does (keep the web server and the browser running until the last test,
 * failure screenshots with `PANTHER_ERROR_SCREENSHOT_DIR`, the interactive mode with `--debug`) and also
 * applies the first and the last step to `PantherTestCase`, which has its own web server and browser
 * because it extends `CakeTestCase` instead of Panther's `PantherTestCase`.
 *
 * With `PANTHER_REPORT_PDF`, it records the results and the screenshots of the tests of `PantherTestCase` and
 * writes them to a PDF report after the last test (see `PieceOfCake2\Panther\Report\Config`).
 */
final class ServerExtension implements BeforeFirstTestHook, BeforeTestHook, AfterTestHook, AfterLastTestHook, AfterTestErrorHook, AfterTestFailureHook, AfterSuccessfulTestHook, AfterSkippedTestHook, AfterIncompleteTestHook, AfterRiskyTestHook, AfterTestWarningHook
{
    /**
     * What Panther's extension does, on PHPUnit 9 and on PHPUnit 10+
     */
    private ServerExtensionLegacy $panther;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->panther = new ServerExtensionLegacy();
    }

    /**
     * @return void
     */
    public function executeBeforeFirstTest(): void
    {
        $this->panther->executeBeforeFirstTest();
        PantherTestCase::$stopServerOnTeardown = false;

        $config = Config::fromServer($_SERVER);
        if ($config !== null) {
            Recorder::start($config);
        }
    }

    /**
     * @param string $test
     * @return void
     */
    public function executeBeforeTest(string $test): void
    {
        $this->panther->executeBeforeTest($test);
    }

    /**
     * @param string $test
     * @param float $time
     * @return void
     */
    public function executeAfterTest(string $test, float $time): void
    {
        $this->panther->executeAfterTest($test, $time);
        Recorder::current()?->setTime($test, $time);
    }

    /**
     * @param string $test
     * @param float $time
     * @return void
     */
    public function executeAfterSuccessfulTest(string $test, float $time): void
    {
        Recorder::current()?->setStatus($test, TestResult::PASSED);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterTestError(string $test, string $message, float $time): void
    {
        $this->panther->executeAfterTestError($test, $message, $time);
        Recorder::current()?->setStatus($test, TestResult::ERROR, $message);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterTestFailure(string $test, string $message, float $time): void
    {
        $this->panther->executeAfterTestFailure($test, $message, $time);
        Recorder::current()?->setStatus($test, TestResult::FAILED, $message);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterSkippedTest(string $test, string $message, float $time): void
    {
        Recorder::current()?->setStatus($test, TestResult::SKIPPED, $message);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterIncompleteTest(string $test, string $message, float $time): void
    {
        Recorder::current()?->setStatus($test, TestResult::INCOMPLETE, $message);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterRiskyTest(string $test, string $message, float $time): void
    {
        Recorder::current()?->setStatus($test, TestResult::RISKY, $message);
    }

    /**
     * @param string $test
     * @param string $message
     * @param float $time
     * @return void
     */
    public function executeAfterTestWarning(string $test, string $message, float $time): void
    {
        Recorder::current()?->setStatus($test, TestResult::WARNING, $message);
    }

    /**
     * Stops the web server and the browser, then writes the report
     *
     * @return void
     */
    public function executeAfterLastTest(): void
    {
        PantherTestCase::stopWebServer();
        $this->panther->executeAfterLastTest();

        $recorder = Recorder::current();
        if ($recorder === null) {
            return;
        }
        Recorder::clear();
        try {
            (new ReportWriter())->write($recorder);
            fwrite(STDERR, sprintf("\nEnd-to-End test report: %s\n", $recorder->config()->pdf));
        } catch (Throwable $e) {
            // The report does not change the result of the tests; a CI job can check that the file exists
            fwrite(STDERR, sprintf("\nThe End-to-End test report was not written: %s\n", $e->getMessage()));
        }
    }
}
