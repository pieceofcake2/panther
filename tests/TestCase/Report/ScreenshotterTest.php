<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;
use PieceOfCake2\Panther\Report\Screenshotter;

class ScreenshotterTest extends TestCase
{
    public function testScale(): void
    {
        $this->assertSame(0.1, Screenshotter::scale(1200));
        $this->assertSame(0.14, Screenshotter::scale(390));
        $this->assertSame(0.1, Screenshotter::scale(0));
    }
}
