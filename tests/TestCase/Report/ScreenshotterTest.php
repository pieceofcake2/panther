<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Screenshotter;

class ScreenshotterTest extends TestCase
{
    /**
     * @return array<string, array{int, array<int>, array<array{int, int}>}>
     */
    public static function partsProvider(): array
    {
        return [
            'fits on a page' => [1200, [0, 600, 1200], [[0, 1200]]],
            'cut at the break nearest to the half' => [1700, [0, 500, 900, 1300, 1700], [[0, 900], [900, 1700]]],
            'rows of 400 pixels' => [4000, range(0, 4000, 400), [[0, 1200], [1200, 2400], [2400, 3200], [3200, 4000]]],
            'no break: parts of the same height' => [5000, [0, 3000, 5000], [[0, 1250], [1250, 2500], [2500, 3750], [3750, 5000]]],
            // A break below half a page would make a part too short
            'too high a break is not used' => [2000, [0, 300, 2000], [[0, 1000], [1000, 2000]]],
        ];
    }

    /**
     * @dataProvider partsProvider
     * @param array<int> $breaks
     * @param array<array{int, int}> $parts
     */
    public function testParts(int $height, array $breaks, array $parts): void
    {
        $this->assertSame($parts, Screenshotter::parts($height, $breaks));
    }

    public function testPartsOfAVeryLongPage(): void
    {
        $parts = Screenshotter::parts(100000, []);

        $this->assertCount(Screenshotter::MAX_PARTS, $parts);
        $this->assertSame(0, $parts[0][0]);
        foreach ($parts as [$top, $bottom]) {
            $this->assertLessThanOrEqual(Screenshotter::PART_HEIGHT, $bottom - $top);
        }
    }
}
