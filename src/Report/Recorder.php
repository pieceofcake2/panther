<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestFailure;
use PHPUnit\Util\Filter;
use PieceOfCake2\Panther\TestSuite\PantherTestCase;
use RuntimeException;
use Symfony\Component\Panther\Client;
use Throwable;

/**
 * Records the End-to-End tests of a run for the report: their results and their screenshots.
 *
 * `ServerExtension` starts it before the first test when `PANTHER_REPORT_PDF` is set, gives it the results of
 * PHPUnit and writes the report after the last test. `PantherTestCase` gives it the screenshots. Only the tests
 * of `PantherTestCase` are in the report.
 */
final class Recorder
{
    private static ?self $current = null;

    /**
     * @var array<string, \PieceOfCake2\Panther\Report\TestResult>
     */
    private array $results = [];

    private float $startedAt;

    private ?float $finishedAt = null;

    /**
     * The browser and its version, e.g. `Chrome 131.0.6778.85`
     */
    private ?string $browser = null;

    private bool $headless = false;

    private TestDescriber $describer;

    private Screenshotter $screenshotter;

    /**
     * Constructor
     *
     * @param \PieceOfCake2\Panther\Report\Config $config Settings
     * @param string $dir Directory of the screenshots and the HTML of the report
     * @param bool $endedOnly The report has only the tests whose end PHPUnit told the extension, as PHPUnit counts
     *   them: not the tests that a test runs itself
     */
    public function __construct(private Config $config, private string $dir, private bool $endedOnly = false)
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Cannot create the directory of the report: %s', $dir));
        }
        $this->startedAt = microtime(true);
        $this->describer = new TestDescriber();
        $this->screenshotter = new Screenshotter($config->jpegQuality);
    }

    /**
     * Starts recording a run in a new temporary directory
     *
     * @param \PieceOfCake2\Panther\Report\Config $config Settings
     * @return self
     */
    public static function start(Config $config): self
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'panther-report-' . bin2hex(random_bytes(6));

        return self::$current = new self($config, $dir, true);
    }

    /**
     * The recorder of the run, or null when there is no report
     *
     * @return self|null
     */
    public static function current(): ?self
    {
        return self::$current;
    }

    /**
     * Stops recording
     *
     * @return void
     */
    public static function clear(): void
    {
        self::$current = null;
    }

    /**
     * Replaces the recorder of the run, e.g. to record the tests that a test runs
     *
     * @param self|null $recorder Recorder, or null for no report
     * @return self|null The previous recorder
     */
    public static function setCurrent(?self $recorder): ?self
    {
        $previous = self::$current;
        self::$current = $recorder;

        return $previous;
    }

    /**
     * Adds a result
     *
     * @param \PieceOfCake2\Panther\Report\TestResult $result Result
     * @return void
     */
    public function add(TestResult $result): void
    {
        $this->results[$result->name] = $result;
    }

    /**
     * @return \PieceOfCake2\Panther\Report\Config
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * The results in the order of the run
     *
     * @return array<\PieceOfCake2\Panther\Report\TestResult>
     */
    public function results(): array
    {
        $results = array_values($this->results);

        return $this->endedOnly ? array_values(array_filter($results, static fn(TestResult $result): bool => $result->ended)) : $results;
    }

    /**
     * @return float
     */
    public function startedAt(): float
    {
        return $this->startedAt;
    }

    /**
     * @return float
     */
    public function finishedAt(): float
    {
        return $this->finishedAt ?? microtime(true);
    }

    /**
     * @return string|null
     */
    public function browser(): ?string
    {
        return $this->browser;
    }

    /**
     * @return bool
     */
    public function isHeadless(): bool
    {
        return $this->headless;
    }

    /**
     * Ends the run
     *
     * @return void
     */
    public function finish(): void
    {
        $this->finishedAt ??= microtime(true);
    }

    /**
     * Whether a test of the class is in the report: the tests of `PantherTestCase`
     *
     * @param string $className Class of the test
     * @return bool
     */
    public static function isReported(string $className): bool
    {
        return class_exists($className) && is_subclass_of($className, PantherTestCase::class);
    }

    /**
     * Starts a test: its titles from the test case
     *
     * @param \PHPUnit\Framework\TestCase $test Test
     * @return \PieceOfCake2\Panther\Report\TestResult
     */
    public function begin(TestCase $test): TestResult
    {
        $result = $this->describer->describe($test);
        $previous = $this->results[$result->name] ?? null;
        if ($previous !== null) {
            $result->screenshots = $previous->screenshots;
        }

        return $this->results[$result->name] = $result;
    }

    /**
     * The result of a test, from its name when it has not begun (e.g. skipped before it ran)
     *
     * @param string $name Name of the test in PHPUnit
     * @return \PieceOfCake2\Panther\Report\TestResult|null Null when the test is not in the report
     */
    public function result(string $name): ?TestResult
    {
        if (isset($this->results[$name])) {
            return $this->results[$name];
        }
        $result = $this->describer->describeName($name);
        if ($result === null || !self::isReported($result->className)) {
            return null;
        }

        return $this->results[$name] = $result;
    }

    /**
     * Sets the result of PHPUnit
     *
     * @param string $name Name of the test in PHPUnit
     * @param string $status `TestResult::PASSED`, `FAILED`, ...
     * @param string|null $message Message of PHPUnit
     * @return void
     */
    public function setStatus(string $name, string $status, ?string $message = null): void
    {
        $result = $this->result($name);
        if ($result === null) {
            return;
        }
        // A failure or an error is not replaced by a later warning or risky result of the same test
        if ($result->isFailure() && !in_array($status, [TestResult::FAILED, TestResult::ERROR], true)) {
            return;
        }
        $result->status = $status;
        if ($message !== null && $message !== '' && $result->message === null) {
            $result->message = $message;
        }
    }

    /**
     * Sets the time of a test at its end
     *
     * @param string $name Name of the test in PHPUnit
     * @param float $time Seconds
     * @return void
     */
    public function setTime(string $name, float $time): void
    {
        $result = $this->result($name);
        if ($result !== null) {
            $result->time = $time;
            $result->ended = true;
        }
    }

    /**
     * Records the exception that ended a test: the message as PHPUnit prints it, and the file and line in the test
     *
     * @param string $name Name of the test in PHPUnit
     * @param \Throwable $exception Exception
     * @param string|null $testFile File of the test class: the location is the line of the test in it, not the
     *   one of an assertion of a library
     * @return void
     */
    public function setException(string $name, Throwable $exception, ?string $testFile = null): void
    {
        $result = $this->result($name);
        if ($result === null) {
            return;
        }
        $result->message = rtrim(TestFailure::exceptionToString($exception));
        $result->location = self::location($exception, $testFile);
    }

    /**
     * The file and line of an exception in the test file, or the first one of PHPUnit's filtered trace
     *
     * @param \Throwable $exception Exception
     * @param string|null $testFile File of the test class
     * @return string|null Relative to the current directory
     */
    private static function location(Throwable $exception, ?string $testFile): ?string
    {
        $location = null;
        if ($testFile !== null) {
            $frames = array_merge([['file' => $exception->getFile(), 'line' => $exception->getLine()]], $exception->getTrace());
            foreach ($frames as $frame) {
                if (($frame['file'] ?? null) === $testFile) {
                    $location = $testFile . ':' . ($frame['line'] ?? 0);
                    break;
                }
            }
        }
        if ($location === null) {
            $trace = trim(Filter::getFilteredStacktrace($exception));
            $location = $trace === '' ? null : (strtok($trace, "\n") ?: null);
        }

        $root = rtrim((string)getcwd(), '/\\') . DIRECTORY_SEPARATOR;
        if ($location !== null && str_starts_with($location, $root)) {
            $location = substr($location, strlen($root));
        }

        return $location;
    }

    /**
     * Takes the screenshots of a step of a test with each client
     *
     * @param string $name Name of the test in PHPUnit
     * @param array<\Symfony\Component\Panther\Client> $clients Clients of the test
     * @param string|null $stepName Name of the step
     * @param string|null $type `Screenshot::END`, `Screenshot::FAILURE` or null
     * @param string|null $element CSS selector or XPath of an element to scroll to
     * @param bool $fullPage Takes the whole page
     * @return void
     */
    public function takeScreenshots(string $name, array $clients, ?string $stepName, ?string $type, ?string $element = null, bool $fullPage = false): void
    {
        $result = $this->result($name);
        if ($result === null) {
            return;
        }
        $prefix = sprintf('%04d', array_search($name, array_keys($this->results), true) + 1);
        foreach ($clients as $client) {
            if (!Screenshotter::isRunning($client)) {
                continue;
            }
            $this->detectBrowser($client);
            $screenshots = $this->screenshotter->take($client, $this->dir, $prefix, $result->nextStep(), $stepName, $type, $element, $fullPage);
            array_push($result->screenshots, ...$screenshots);
        }
    }

    /**
     * Records the browser of the first screenshot
     *
     * @param \Symfony\Component\Panther\Client $client Client
     * @return void
     */
    private function detectBrowser(Client $client): void
    {
        if ($this->browser !== null) {
            return;
        }
        $driver = $client->getWebDriver();
        $capabilities = method_exists($driver, 'getCapabilities') ? $driver->getCapabilities() : null;
        if ($capabilities === null) {
            return;
        }
        $names = ['chrome' => 'Chrome', 'chrome-headless-shell' => 'Chrome Headless Shell', 'firefox' => 'Firefox', 'msedge' => 'Microsoft Edge', 'safari' => 'Safari'];
        $browser = (string)$capabilities->getBrowserName();
        $this->browser = trim(($names[$browser] ?? $browser) . ' ' . $capabilities->getVersion());
        $userAgent = (string)$client->executeScript('return navigator.userAgent;');
        $this->headless = str_contains($userAgent, 'Headless') || $browser === 'chrome-headless-shell';
    }
}
