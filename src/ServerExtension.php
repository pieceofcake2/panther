<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther;

use PHPUnit\Runner\AfterLastTestHook;
use PHPUnit\Runner\AfterTestErrorHook;
use PHPUnit\Runner\AfterTestFailureHook;
use PHPUnit\Runner\AfterTestHook;
use PHPUnit\Runner\BeforeFirstTestHook;
use PHPUnit\Runner\BeforeTestHook;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;
use Symfony\Component\Panther\ServerExtensionLegacy;

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
 */
final class ServerExtension implements BeforeFirstTestHook, BeforeTestHook, AfterTestHook, AfterLastTestHook, AfterTestErrorHook, AfterTestFailureHook
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
    }

    /**
     * @return void
     */
    public function executeAfterLastTest(): void
    {
        PantherTestCase::stopWebServer();
        $this->panther->executeAfterLastTest();
    }
}
