<?php
declare(strict_types=1);

namespace PieceOfCake2\Panther\Test\TestSuite;

use PieceOfCake2\Panther\TestSuite\PantherTestCase;

/**
 * Exposes the protected methods
 */
abstract class TestPantherTestCase extends PantherTestCase
{
    /**
     * @param string|null $projectRoot
     * @return string
     */
    public static function webServerDirOf(?string $projectRoot): string
    {
        return static::defaultWebServerDir($projectRoot);
    }
}
