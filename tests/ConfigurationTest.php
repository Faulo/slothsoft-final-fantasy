<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

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
    
    public function testZipResolvesToStableTemporaryRomWithoutExtractingEntryPaths(): void {
        $contents = "NES\x1A" . str_repeat("\0", 32);
        $archive = $this->zip(['../nested/game.NES' => $contents, 'readme.txt' => 'Fixture']);
        Configuration::setRomPath('i', $archive);
        $path = Configuration::getRomPath('i');
        self::assertNotNull($path);
        self::assertNotSame($archive, $path);
        self::assertSame($contents, file_get_contents($path));
        self::assertSame($path, Configuration::getRomPath('i'));
        self::assertFileDoesNotExist(dirname($archive) . '/../nested/game.NES');
        Configuration::setRomPath('i', '/plain/replacement.nes');
        self::assertSame('/plain/replacement.nes', Configuration::getRomPath('i'));
        Configuration::setRomPath('i', null);
        self::assertNull(Configuration::getRomPath('i'));
    }
    
    /**
     * @dataProvider invalidZipEntries
     * @param array<string, string> $entries
     */
    public function testRejectsUnsupportedZipContents(array $entries): void {
        $archive = $this->zip($entries);
        $this->expectException(InvalidArgumentException::class);
        Configuration::setRomPath('i', $archive);
    }
    
    /** @return list<array{array<string, string>}> */
    public function invalidZipEntries(): array {
        return [
            [['readme.txt' => 'No ROM']],
            [['one.nes' => 'First', 'two.nes' => 'Second']],
            [['large.nes' => str_repeat("\0", 16 + 512 + 0x40001)]]
        ];
    }
    
    public function testInvalidZipDoesNotReplaceExistingConfiguration(): void {
        Configuration::setRomPath('i', '/plain/previous.nes');
        $path = temp_file(__CLASS__) . '.zip';
        file_put_contents($path, 'Not a ZIP');
        try {
            Configuration::setRomPath('i', $path);
            self::fail('Invalid ZIP should be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('/plain/previous.nes', Configuration::getRomPath('i'));
        }
    }
    
    /** @param array<string, string> $entries */
    private function zip(array $entries): string {
        $path = temp_file(__CLASS__) . '.ZIP';
        $zip = new ZipArchive();
        self::assertTrue($zip->open($path, ZipArchive::CREATE));
        foreach ($entries as $name => $contents) {
            self::assertTrue($zip->addFromString($name, $contents));
        }
        self::assertTrue($zip->close());
        return $path;
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
