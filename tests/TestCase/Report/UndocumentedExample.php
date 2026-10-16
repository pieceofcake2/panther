<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\Report;

use PHPUnit\Framework\TestCase;

class UndocumentedExample extends TestCase
{
    public function testSomething(): void
    {
        $this->addToAssertionCount(1);
    }
}
