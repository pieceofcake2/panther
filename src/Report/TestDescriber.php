<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Report;

use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Test;
use PHPUnit\Util\TestDox\NamePrettifier;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * The titles of a test in the report, from its `@testdox` (as PHPUnit's `--testdox`), the summary of its doc
 * comment, or its name:
 *
 * - Class: `@testdox` of the class, the summary of the doc comment of the class, or the class name as a sentence
 *   (without its namespace and `Test`)
 * - Test: `@testdox` of the method (its `$parameter` placeholders are replaced with the data as in PHPUnit),
 *   the summary of the doc comment of the method, or the method name as a sentence
 * - Description: the rest of the doc comment of the method, before the tags
 */
final class TestDescriber
{
    private NamePrettifier $prettifier;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->prettifier = new NamePrettifier();
    }

    /**
     * Describes a running test
     *
     * @param \PHPUnit\Framework\TestCase $test Test
     * @return \PieceOfCake2\Panther\Report\TestResult
     */
    public function describe(TestCase $test): TestResult
    {
        $className = get_class($test);
        $method = $test->getName(false);
        $dataSet = $test->usesDataProvider() ? self::dataSetName($test->dataName()) : null;
        [$title, $titleHasData] = $this->methodTitle($className, $method);
        if ($titleHasData) {
            $title = $this->prettifier->prettifyTestCase($test);
        }

        return new TestResult(
            $test->toString(),
            $className,
            $method,
            $this->classTitle($className),
            $title,
            $dataSet,
            $titleHasData,
            self::description(self::methodDocComment($className, $method)),
        );
    }

    /**
     * Describes a test by its name, for a test that did not run (e.g. skipped before `runTest()`)
     *
     * @param string $name Name of the test in PHPUnit, e.g. `App\FooTest::testBar with data set "baz"`
     * @return \PieceOfCake2\Panther\Report\TestResult|null Null when the name is not the one of a test method
     */
    public function describeName(string $name): ?TestResult
    {
        if (!preg_match('/\A(?<class>[^:\s]+)::(?<method>[^\s(]+)(?: with data set (?:"(?<name>.*)"|#(?<number>\d+)))?/s', $name, $matches)) {
            return null;
        }
        $className = $matches['class'];
        $method = $matches['method'];
        $dataSet = null;
        if (($matches['name'] ?? '') !== '') {
            $dataSet = $matches['name'];
        } elseif (($matches['number'] ?? '') !== '') {
            $dataSet = self::dataSetName((int)$matches['number']);
        }
        [$title] = $this->methodTitle($className, $method);

        return new TestResult(
            $name,
            $className,
            $method,
            $this->classTitle($className),
            $title,
            $dataSet,
            false,
            self::description(self::methodDocComment($className, $method)),
        );
    }

    /**
     * The title of a test class
     *
     * @param string $className Class
     * @return string
     */
    public function classTitle(string $className): string
    {
        $annotations = self::annotations($className, null);
        if (isset($annotations['class']['testdox'][0])) {
            return (string)$annotations['class']['testdox'][0];
        }

        try {
            $summary = self::summary((string)(new ReflectionClass($className))->getDocComment());
        } catch (ReflectionException) {
            $summary = null;
        }

        // PHPUnit's prettifyTestClass() adds the namespace: the class name as a sentence instead
        $shortName = (string)preg_replace('/Test\z/', '', substr((string)strrchr('\\' . $className, '\\'), 1));

        return $summary ?? $this->prettifier->prettifyTestMethod('test' . $shortName);
    }

    /**
     * The title of a test method, and whether it shows the data of the data set itself
     *
     * @param string $className Class
     * @param string $method Method
     * @return array{string, bool}
     */
    private function methodTitle(string $className, string $method): array
    {
        $annotations = self::annotations($className, $method);
        if (isset($annotations['method']['testdox'][0])) {
            $testdox = (string)$annotations['method']['testdox'][0];

            return [$testdox, str_contains($testdox, '$')];
        }

        $summary = self::summary(self::methodDocComment($className, $method));

        return [$summary ?? $this->prettifier->prettifyTestMethod($method), false];
    }

    /**
     * The name of a data set: the key of the data provider, or `#<number>` for a list
     *
     * @param string|int $name `TestCase::dataName()`
     * @return string
     */
    private static function dataSetName(string|int $name): string
    {
        return is_int($name) ? '#' . $name : $name;
    }

    /**
     * The annotations of a class and a method
     *
     * @param string $className Class
     * @param string|null $method Method
     * @return array<string, array<string, array<int, string>>>
     */
    private static function annotations(string $className, ?string $method): array
    {
        if (!class_exists($className)) {
            return [];
        }

        try {
            return Test::parseTestMethodAnnotations($className, $method);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * The doc comment of a method
     *
     * @param string $className Class
     * @param string $method Method
     * @return string
     */
    private static function methodDocComment(string $className, string $method): string
    {
        try {
            return (string)(new ReflectionMethod($className, $method))->getDocComment();
        } catch (ReflectionException) {
            return '';
        }
    }

    /**
     * The lines of the text of a doc comment, before its tags
     *
     * @param string $docComment Doc comment
     * @return array<string>
     */
    private static function lines(string $docComment): array
    {
        $lines = [];
        // With the u modifier: without it, \R and \s match the byte 0x85 inside multibyte characters
        foreach (preg_split('/\R/u', $docComment) ?: [] as $line) {
            $line = (string)preg_replace('#\A\s*(/\*\*|\*/|\*)\s?#u', '', $line);
            $line = (string)preg_replace('#\s*\*/\z#u', '', $line);
            if (str_starts_with(ltrim($line), '@')) {
                break;
            }
            $lines[] = rtrim($line);
        }

        return $lines;
    }

    /**
     * The summary of a doc comment: its first paragraph on one line
     *
     * @param string $docComment Doc comment
     * @return string|null
     */
    private static function summary(string $docComment): ?string
    {
        $summary = '';
        foreach (self::lines($docComment) as $line) {
            $line = trim($line);
            if ($line === '') {
                if ($summary !== '') {
                    break;
                }
                continue;
            }
            $summary = self::join($summary, $line);
        }

        return $summary === '' ? null : $summary;
    }

    /**
     * Joins two lines of a paragraph: with a space between words, without one in Japanese or Chinese
     *
     * @param string $text Text
     * @param string $line Next line
     * @return string
     */
    private static function join(string $text, string $line): string
    {
        if ($text === '') {
            return $line;
        }
        $wide = '[\x{3000}-\x{30FF}\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}\x{FF00}-\x{FFEF}]';
        $noSpace = preg_match('/' . $wide . '\z/u', $text) || preg_match('/\A' . $wide . '/u', $line);

        return $text . ($noSpace ? '' : ' ') . $line;
    }

    /**
     * The text of a doc comment after the summary (its first paragraph), before its tags
     *
     * @param string $docComment Doc comment
     * @return string|null
     */
    private static function description(string $docComment): ?string
    {
        $lines = self::lines($docComment);
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }
        while ($lines !== [] && trim($lines[0]) !== '') {
            array_shift($lines);
        }
        $text = trim(implode("\n", $lines));

        return $text === '' ? null : $text;
    }
}
