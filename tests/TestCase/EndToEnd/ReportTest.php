<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\EndToEnd;

use FilesystemIterator;
use PieceOfCake2\Panther\Report\Config;
use PieceOfCake2\Panther\Report\Recorder;
use PieceOfCake2\Panther\Report\ReportWriter;
use PieceOfCake2\Panther\Report\Screenshot;
use PieceOfCake2\Panther\Report\TestResult;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;
use PieceOfCake2\Report\Environment;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The screenshots and the PDF of the report (`PANTHER_REPORT_PDF`), with the tests of ReportPage run in a recorder
 * of their own.
 */
class ReportTest extends PantherTestCase
{
    private string $dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->dir = TMP . 'report-' . bin2hex(random_bytes(4));
    }

    public function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                /** @var \SplFileInfo $file */
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    /**
     * Runs tests of ReportPage with a recorder
     *
     * @param array<string> $methods Test methods
     * @return \PieceOfCake2\Panther\Report\Recorder
     */
    private function record(array $methods): Recorder
    {
        $recorder = new Recorder(new Config($this->dir . DS . 'report.pdf'), $this->dir . DS . 'run');
        $previous = Recorder::setCurrent($recorder);
        try {
            foreach ($methods as $method) {
                (new ReportPage($method))->run();
            }
        } finally {
            Recorder::setCurrent($previous);
        }

        return $recorder;
    }

    /**
     * The result of a test of ReportPage
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorder
     * @param string $method Test method
     * @return \PieceOfCake2\Panther\Report\TestResult
     */
    private static function result(Recorder $recorder, string $method): TestResult
    {
        $result = $recorder->result(ReportPage::class . '::' . $method);
        self::assertNotNull($result);

        return $result;
    }

    /**
     * The width and height of an image of the report
     *
     * @param \PieceOfCake2\Panther\Report\Recorder $recorder Recorder
     * @param \PieceOfCake2\Panther\Report\Screenshot $screenshot Screenshot
     * @return array{int, int}
     */
    private static function imageSize(Recorder $recorder, Screenshot $screenshot): array
    {
        $size = getimagesize($recorder->dir() . DS . $screenshot->file);
        self::assertIsArray($size);
        self::assertSame(IMAGETYPE_JPEG, $size[2]);

        return [$size[0], $size[1]];
    }

    public function testAPassedTestGetsTheScreenshotOfItsEnd(): void
    {
        $recorder = $this->record(['testEnd']);

        $result = self::result($recorder, 'testEnd');
        $this->assertSame(TestResult::PASSED, $result->status);
        $this->assertSame('Shows the top page', $result->title);
        $this->assertCount(1, $result->screenshots);
        $screenshot = $result->screenshots[0];
        $this->assertSame(Screenshot::END, $screenshot->type);
        $this->assertNull($screenshot->name);
        $this->assertSame('Viewport', $screenshot->mode);
        $this->assertSame('/', $screenshot->path);
        $this->assertSame('Panther fixture', $screenshot->title);
        $this->assertSame([$screenshot->viewportWidth, $screenshot->viewportHeight], self::imageSize($recorder, $screenshot));
        $this->assertStringContainsString('Chrome', (string)$recorder->browser());
    }

    public function testStepsReplaceTheScreenshotOfTheEnd(): void
    {
        $recorder = $this->record(['testSteps']);

        $result = self::result($recorder, 'testSteps');
        $this->assertSame(TestResult::PASSED, $result->status);
        $steps = array_map(static fn(Screenshot $screenshot): array => [$screenshot->step, $screenshot->name, $screenshot->mode, $screenshot->part, $screenshot->parts], $result->screenshots);
        $this->assertSame([
            [1, 'Top page', 'Viewport', 1, 1],
            [2, 'Red block', 'Element: #target', 1, 1],
            [3, 'Whole page', 'Full page', 1, 1],
            [4, 'A page of the report', 'Fit to page', 1, 1],
            [5, 'A page from the red block', 'Fit to page: #target', 1, 1],
        ], $steps);

        // A full page of 5000 pixels is one image, as wide as the page (the viewport without its scroll bar, 15 pixels
        // on Linux), with where the report can cut it: between the blue and the red blocks
        [$width, $height] = self::imageSize($recorder, $result->screenshots[2]);
        $this->assertSame(5000, $height);
        $this->assertContains(3000, $result->screenshots[2]->breaks);
        // What fits on a page of the report under the title: 1900 pixels from the top of the page, or of the element
        // (as wide as the viewport, with or without the scroll bar)
        foreach ([$result->screenshots[3], $result->screenshots[4]] as $screenshot) {
            [$fitWidth, $fitHeight] = self::imageSize($recorder, $screenshot);
            $this->assertSame(1900, $fitHeight);
            $this->assertGreaterThan($screenshot->viewportWidth - 30, $fitWidth);
            $this->assertLessThanOrEqual($screenshot->viewportWidth, $fitWidth);
        }
        $this->assertLessThanOrEqual($result->screenshots[2]->viewportWidth, $width);
        $this->assertGreaterThan($result->screenshots[2]->viewportWidth - 30, $width);
    }

    public function testAFullPageCanBeCutBetweenItsRows(): void
    {
        $recorder = $this->record(['testRows']);

        $screenshots = self::result($recorder, 'testRows')->screenshots;
        $this->assertCount(1, $screenshots);
        $this->assertSame(4000, self::imageSize($recorder, $screenshots[0])[1]);
        // Between the rows of 400 pixels
        $this->assertSame(range(400, 3600, 400), array_values(array_intersect($screenshots[0]->breaks, range(400, 3600, 400))));
    }

    public function testElementIsScrolledToTheTop(): void
    {
        if (!function_exists('imagecreatefromjpeg')) {
            $this->markTestSkipped('The GD extension is needed to read the colors of the screenshot.');
        }
        $recorder = $this->record(['testSteps']);

        $screenshots = self::result($recorder, 'testSteps')->screenshots;
        // The viewport scrolled to the element, and a page of the report from the element
        foreach ([$screenshots[1], $screenshots[4]] as $screenshot) {
            $image = imagecreatefromjpeg($recorder->dir() . DS . $screenshot->file);
            $this->assertNotFalse($image);
            $rgb = imagecolorat($image, 10, 10);

            // The red block, not the blue one above it
            $this->assertGreaterThan(200, ($rgb >> 16) & 0xff, $screenshot->mode);
            $this->assertLessThan(60, $rgb & 0xff, $screenshot->mode);
        }
    }

    public function testAFailedTestGetsTheScreenshotOfTheFailure(): void
    {
        $recorder = $this->record(['testFails']);

        $result = self::result($recorder, 'testFails');
        $this->assertSame(TestResult::FAILED, $result->status);
        $this->assertStringContainsString('Not on the page', (string)$result->message);
        $this->assertStringStartsWith('tests' . DS . 'TestCase' . DS . 'EndToEnd' . DS . 'ReportPage.php:', (string)$result->location);
        $this->assertCount(1, $result->screenshots);
        $this->assertSame(Screenshot::FAILURE, $result->screenshots[0]->type);
    }

    public function testAScreenshotOfAPhoneIsInCssPixels(): void
    {
        $recorder = $this->record(['testMobile']);

        $screenshot = self::result($recorder, 'testMobile')->screenshots[0];
        $this->assertSame([390, 844], [$screenshot->viewportWidth, $screenshot->viewportHeight]);
        $this->assertSame([390, 844], self::imageSize($recorder, $screenshot));
    }

    public function testASkippedTestGetsNoScreenshot(): void
    {
        $recorder = $this->record(['testSkipped']);

        $result = self::result($recorder, 'testSkipped');
        $this->assertSame(TestResult::SKIPPED, $result->status);
        $this->assertSame('Not ready', $result->message);
        $this->assertSame([], $result->screenshots);
    }

    public function testATestWithoutBrowserGetsNoScreenshot(): void
    {
        $recorder = $this->record(['testWithoutBrowser']);

        $result = self::result($recorder, 'testWithoutBrowser');
        $this->assertSame(TestResult::PASSED, $result->status);
        $this->assertSame([], $result->screenshots);
    }

    public function testWritesThePdf(): void
    {
        $recorder = $this->record(['testEnd', 'testFails']);
        $environment = new Environment();
        $environment->repository = 'pieceofcake2/panther';
        $environment->commit = 'a1b2c3d';

        (new ReportWriter())->write($recorder, $environment);

        $pdf = (string)file_get_contents($this->dir . DS . 'report.pdf');
        $this->assertStringStartsWith('%PDF-', $pdf);
        // The cover, the list and a page for each test
        $this->assertGreaterThanOrEqual(4, preg_match_all('#/Type\s*/Page\b#', $pdf));
        // The screenshots and the HTML are removed
        $this->assertDirectoryDoesNotExist($recorder->dir());
    }
}
