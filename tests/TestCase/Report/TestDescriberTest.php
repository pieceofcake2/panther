<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\TestDescriber;

class TestDescriberTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null}>
     */
    public static function titleProvider(): array
    {
        return [
            '@testdox' => ['testWithTestdox', 'Sends a message', null],
            'summary of two lines' => ['testWithSummary', 'Shows the errors of the input and keeps it.', "The required fields are checked\nbefore the confirmation."],
            'Japanese summary' => ['testWithJapaneseSummary', '入力の誤りを表示し、直して送信できる', null],
            'method name' => ['testWithoutDocComment', 'Without doc comment', null],
        ];
    }

    /**
     * @dataProvider titleProvider
     */
    public function testTitles(string $method, string $title, ?string $description): void
    {
        $result = (new TestDescriber())->describe(new DescribedExample($method));

        $this->assertSame('Contact form', $result->classTitle);
        $this->assertSame($title, $result->title);
        $this->assertSame($description, $result->description);
        $this->assertNull($result->dataSet);
        $this->assertSame(DescribedExample::class . '::' . $method, $result->name);
    }

    public function testPlaceholdersOfTestdoxShowTheData(): void
    {
        $test = new DescribedExample('testWithPlaceholder', ['/contacts/add'], 'contact form');

        $result = (new TestDescriber())->describe($test);

        $this->assertSame('Opens /contacts/add', $result->title);
        $this->assertSame('contact form', $result->dataSet);
        $this->assertTrue($result->titleHasData);
    }

    public function testDataSets(): void
    {
        $describer = new TestDescriber();

        $named = $describer->describe(new DescribedExample('testWithNamedDataSet', ['/'], 'top page'));
        $numbered = $describer->describe(new DescribedExample('testWithNumberedDataSet', ['/'], 1));

        $this->assertSame('Opens the page', $named->title);
        $this->assertSame('top page', $named->dataSet);
        $this->assertFalse($named->titleHasData);
        $this->assertSame('With numbered data set', $numbered->title);
        $this->assertSame('#1', $numbered->dataSet);
    }

    public function testDescribeName(): void
    {
        $describer = new TestDescriber();

        $named = $describer->describeName(DescribedExample::class . '::testWithNamedDataSet with data set "top page"');
        $numbered = $describer->describeName(DescribedExample::class . '::testWithNumberedDataSet with data set #0');
        $plain = $describer->describeName(DescribedExample::class . '::testWithTestdox');

        $this->assertNotNull($named);
        $this->assertSame('Opens the page', $named->title);
        $this->assertSame('top page', $named->dataSet);
        $this->assertNotNull($numbered);
        $this->assertSame('#0', $numbered->dataSet);
        $this->assertNotNull($plain);
        $this->assertSame('Sends a message', $plain->title);
        $this->assertSame('Contact form', $plain->classTitle);
        $this->assertSame(DescribedExample::class, $plain->className);
        $this->assertSame('testWithTestdox', $plain->method);
        $this->assertNull($describer->describeName('Warning'));
    }

    public function testDescribeTheNameOfAnUnknownTest(): void
    {
        $result = (new TestDescriber())->describeName('App\NoSuchTest::testSomething');

        $this->assertNotNull($result);
        $this->assertSame('No such', $result->classTitle);
        $this->assertSame('Something', $result->title);
        $this->assertNull($result->description);
    }

    public function testClassTitleWithoutDocComment(): void
    {
        $describer = new TestDescriber();

        $this->assertSame('Undocumented example', $describer->classTitle(UndocumentedExample::class));
        $this->assertSame('Test describer', $describer->classTitle(self::class));
    }
}
