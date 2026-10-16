<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Screenshotter;

class ScreenshotterTest extends TestCase
{
    /**
     * Parts of at most 1900 pixels for the first one (under the title of the test) and 2300 for the next ones
     *
     * @return array<string, array{int, array<int>, array<array{int, int}>}>
     */
    public static function partsProvider(): array
    {
        return [
            'fits under the title' => [1900, [0, 600, 1900], [[0, 1900]]],
            'cut at the lowest break' => [2500, [0, 500, 900, 1300, 1700, 2100, 2500], [[0, 1700], [1700, 2500]]],
            'rows of 400 pixels' => [6000, range(0, 6000, 400), [[0, 1600], [1600, 3600], [3600, 5600], [5600, 6000]]],
            'no break: at the most height' => [5000, [0, 5000], [[0, 1900], [1900, 4200], [4200, 5000]]],
            // A break in the first half would make a part too short
            'too high a break is not used' => [2500, [0, 300, 2500], [[0, 1900], [1900, 2500]]],
        ];
    }

    /**
     * @dataProvider partsProvider
     * @param array<int> $breaks
     * @param array<array{int, int}> $parts
     */
    public function testParts(int $height, array $breaks, array $parts): void
    {
        $this->assertSame($parts, Screenshotter::parts($height, $breaks, 1900, 2300));
    }

    public function testPartsOfAVeryLongPage(): void
    {
        $parts = Screenshotter::parts(100000, [], 1900, 2300);

        $this->assertCount(Screenshotter::MAX_PARTS, $parts);
        $this->assertSame([0, 1900], $parts[0]);
        $this->assertSame([1900, 4200], $parts[1]);
    }

    public function testScale(): void
    {
        $this->assertSame(0.1, Screenshotter::scale(1200));
        $this->assertSame(0.14, Screenshotter::scale(390));
        $this->assertSame(0.1, Screenshotter::scale(0));
    }
}
