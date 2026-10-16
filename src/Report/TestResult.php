<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

/**
 * A test in the report: its names, result and screenshots.
 */
final class TestResult
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const ERROR = 'error';
    public const SKIPPED = 'skipped';
    public const INCOMPLETE = 'incomplete';
    public const RISKY = 'risky';
    public const WARNING = 'warning';

    public string $status = self::PASSED;

    /**
     * The message of PHPUnit (the failure, or the reason of the skip)
     */
    public ?string $message = null;

    /**
     * The file and line of the failure, relative to the project
     */
    public ?string $location = null;

    /**
     * Seconds
     */
    public float $time = 0.0;

    /**
     * @var array<\PieceOfCake2\Panther\Report\Screenshot>
     */
    public array $screenshots = [];

    /**
     * PHPUnit has told the end of the test to the extension (not for a test run inside another test)
     */
    public bool $ended = false;

    /**
     * Constructor
     *
     * @param string $name Name of the test in PHPUnit, e.g. `App\FooTest::testBar with data set "baz"`
     * @param string $className Class of the test
     * @param string $method Method of the test
     * @param string $classTitle Title of the class (`@testdox` of the class, the summary of its doc comment or the class name)
     * @param string $title Title of the test (`@testdox`, the summary of the doc comment or the method name)
     * @param string|null $dataSet Name of the data set of a test with a data provider
     * @param bool $titleHasData The title already shows the data (a `@testdox` with placeholders)
     * @param string|null $description The rest of the doc comment
     */
    public function __construct(
        public string $name,
        public string $className,
        public string $method,
        public string $classTitle,
        public string $title,
        public ?string $dataSet = null,
        public bool $titleHasData = false,
        public ?string $description = null,
    ) {
    }

    /**
     * The test failed or had an error
     *
     * @return bool
     */
    public function isFailure(): bool
    {
        return $this->status === self::FAILED || $this->status === self::ERROR;
    }

    /**
     * The number of the next step of the screenshots
     *
     * @return int
     */
    public function nextStep(): int
    {
        $last = end($this->screenshots);

        return $last === false ? 1 : $last->step + 1;
    }
}
