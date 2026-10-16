<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;

/**
 * Tests with titles in their doc comments, for TestDescriberTest. PHPUnit does not collect them: the file name
 * does not end with Test.php.
 *
 * @testdox Contact form
 */
class DescribedExample extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function pageProvider(): array
    {
        return ['top page' => ['/'], 'contact form' => ['/contacts/add']];
    }

    /**
     * @return array<array{string}>
     */
    public static function listProvider(): array
    {
        return [['/'], ['/contacts/add']];
    }

    /**
     * @testdox Sends a message
     */
    public function testWithTestdox(): void
    {
        $this->addToAssertionCount(1);
    }

    /**
     * Shows the errors of the input
     * and keeps it.
     *
     * The required fields are checked
     * before the confirmation.
     *
     * @return void
     */
    public function testWithSummary(): void
    {
        $this->addToAssertionCount(1);
    }

    /**
     * 入力の誤りを表示し、
     * 直して送信できる
     */
    public function testWithJapaneseSummary(): void
    {
        $this->addToAssertionCount(1);
    }

    public function testWithoutDocComment(): void
    {
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider pageProvider
     * @testdox Opens $path
     */
    public function testWithPlaceholder(string $path): void
    {
        $this->assertNotSame('', $path);
    }

    /**
     * Opens the page
     *
     * @dataProvider pageProvider
     */
    public function testWithNamedDataSet(string $path): void
    {
        $this->assertNotSame('', $path);
    }

    /**
     * @dataProvider listProvider
     */
    public function testWithNumberedDataSet(string $path): void
    {
        $this->assertNotSame('', $path);
    }
}
