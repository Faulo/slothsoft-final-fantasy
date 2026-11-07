<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Slothsoft\FinalFantasy\Configuration
 * @runTestsInSeparateProcesses
 */
final class ConfigurationTest extends TestCase {
    public function testRomLocationCanBeConfiguredAndCleared(): void {
        Configuration::setRomPath('i', null);
        self::assertNull(Configuration::getRomPath('i'));
        Configuration::setRomPath('i', '/private/Final Fantasy (USA).nes');
        self::assertSame('/private/Final Fantasy (USA).nes', Configuration::getRomPath('i'));
        Configuration::setRomPath('i', null);
        self::assertNull(Configuration::getRomPath('i'));
    }
    
    public function testRejectsEmptyPath(): void {
        $this->expectException(InvalidArgumentException::class);
        Configuration::setRomPath('i', '');
    }
    
    public function testRejectsUnsupportedTitleForRead(): void {
        $this->expectException(InvalidArgumentException::class);
        Configuration::getRomPath('ii');
    }
    
    public function testRejectsUnsupportedTitleForWrite(): void {
        $this->expectException(InvalidArgumentException::class);
        Configuration::setRomPath('ii', '/private/game.nes');
    }
}
