<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * A failed test saves a screenshot to PANTHER_ERROR_SCREENSHOT_DIR (Panther's ServerExtension and
 * `takeScreenshotIfTestFailed()`, called by `PantherTestCase::tearDown()`).
 */
class ErrorScreenshotTest extends PantherTestCase
{
    public function testFailedTestSavesAScreenshot(): void
    {
        $dir = TMP . 'error-screenshots-' . bin2hex(random_bytes(4));
        $previous = $_SERVER['PANTHER_ERROR_SCREENSHOT_DIR'] ?? null;
        $_SERVER['PANTHER_ERROR_SCREENSHOT_DIR'] = $dir;
        try {
            $result = (new FailingPage('testFails'))->run();
        } finally {
            $_SERVER['PANTHER_ERROR_SCREENSHOT_DIR'] = $previous;
        }

        $this->assertSame(1, $result->failureCount());
        $screenshots = glob($dir . DS . '*.png') ?: [];
        $this->assertCount(1, $screenshots);
        $this->assertStringContainsString('FailingPage', basename($screenshots[0]));
        $this->assertSame("\x89PNG", substr((string)file_get_contents($screenshots[0]), 0, 4));
    }
}
