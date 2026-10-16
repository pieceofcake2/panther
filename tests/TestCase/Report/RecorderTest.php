<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Config;
use PieceOfCake2\Panther\Report\Recorder;
use PieceOfCake2\Panther\Report\TestResult;
use PieceOfCake2\Panther\ServerExtension;
use PieceOfCake2\Panther\Test\EndToEnd\FailingPage;

class RecorderTest extends TestCase
{
    private ?Recorder $previous = null;

    private string $dir;

    public function setUp(): void
    {
        parent::setUp();
        $this->dir = TMP . 'report-' . bin2hex(random_bytes(4));
        $this->previous = Recorder::setCurrent(new Recorder(new Config('report.pdf'), $this->dir));
    }

    public function tearDown(): void
    {
        Recorder::setCurrent($this->previous);
        if (is_dir($this->dir)) {
            rmdir($this->dir);
        }
        parent::tearDown();
    }

    private static function recorder(): Recorder
    {
        $recorder = Recorder::current();
        self::assertNotNull($recorder);

        return $recorder;
    }

    public function testOnlyTheTestsOfPantherTestCase(): void
    {
        $this->assertTrue(Recorder::isReported(FailingPage::class));
        $this->assertFalse(Recorder::isReported(self::class));
        $this->assertFalse(Recorder::isReported('App\NoSuchTest'));

        $this->assertNull(self::recorder()->result(self::class . '::testOnlyTheTestsOfPantherTestCase'));
        $this->assertNotNull(self::recorder()->result(FailingPage::class . '::testFails'));
    }

    public function testExtensionGivesTheResultsOfPhpUnit(): void
    {
        $extension = new ServerExtension();
        $test = FailingPage::class . '::testFails';

        $extension->executeAfterSkippedTest($test, 'No browser', 0.0);
        $extension->executeAfterTest($test, 0.25);

        $result = self::recorder()->result($test);
        $this->assertNotNull($result);
        $this->assertSame(TestResult::SKIPPED, $result->status);
        $this->assertSame('No browser', $result->message);
        $this->assertSame(0.25, $result->time);
        $this->assertSame('Fails', $result->title);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function statusProvider(): array
    {
        return [
            'passed' => ['executeAfterSuccessfulTest', TestResult::PASSED],
            'failed' => ['executeAfterTestFailure', TestResult::FAILED],
            'error' => ['executeAfterTestError', TestResult::ERROR],
            'skipped' => ['executeAfterSkippedTest', TestResult::SKIPPED],
            'incomplete' => ['executeAfterIncompleteTest', TestResult::INCOMPLETE],
            'risky' => ['executeAfterRiskyTest', TestResult::RISKY],
            'warning' => ['executeAfterTestWarning', TestResult::WARNING],
        ];
    }

    /**
     * @dataProvider statusProvider
     */
    public function testStatuses(string $hook, string $status): void
    {
        $test = FailingPage::class . '::testFails';
        $extension = new ServerExtension();
        if ($hook === 'executeAfterSuccessfulTest') {
            $extension->executeAfterSuccessfulTest($test, 0.1);
        } else {
            $extension->{$hook}($test, 'Message', 0.1);
        }

        $result = self::recorder()->result($test);
        $this->assertNotNull($result);
        $this->assertSame($status, $result->status);
    }

    public function testAFailureIsNotReplacedByALaterWarning(): void
    {
        $test = FailingPage::class . '::testFails';
        $extension = new ServerExtension();

        $extension->executeAfterTestFailure($test, 'Failed', 0.1);
        $extension->executeAfterRiskyTest($test, 'Risky', 0.1);

        $result = self::recorder()->result($test);
        $this->assertNotNull($result);
        $this->assertSame(TestResult::FAILED, $result->status);
        $this->assertSame('Failed', $result->message);
    }

    public function testTheExtensionReportsTheTestsWhoseEndPhpUnitTold(): void
    {
        $ended = FailingPage::class . '::testFails';
        $nested = FailingPage::class . '::testFails with data set #0';
        $names = static fn(Recorder $recorder): array => array_map(static fn(TestResult $result): string => $result->name, $recorder->results());

        $extension = new Recorder(new Config('report.pdf'), $this->dir, true);
        $all = new Recorder(new Config('report.pdf'), $this->dir);
        foreach ([$extension, $all] as $recorder) {
            $recorder->setStatus($ended, TestResult::PASSED);
            $recorder->setTime($ended, 0.1);
            // A test run by a test: PHPUnit tells the extension nothing
            $recorder->setStatus($nested, TestResult::FAILED);
        }

        $this->assertSame([$ended], $names($extension));
        $this->assertSame([$ended, $nested], $names($all));
    }

    public function testException(): void
    {
        $test = FailingPage::class . '::testFails';
        try {
            $this->fail('Not on the page');
        } catch (AssertionFailedError $e) {
            self::recorder()->setException($test, $e);
        }

        $result = self::recorder()->result($test);
        $this->assertNotNull($result);
        $this->assertSame('Not on the page', $result->message);
        $this->assertNotNull($result->location);
        $this->assertStringStartsWith('tests' . DS . 'TestCase' . DS . 'Report' . DS . 'RecorderTest.php:', $result->location);
    }
}
