<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use RuntimeException;
use Slothsoft\Core\FileSystem;
use Slothsoft\Savegame\Node\ArchiveParser\ArchiveExtractorInterface;
use SplFileInfo;

/** Container decoding only; game tables and pointers are described by rom.xml. */
final class NesArchiveExtractor implements ArchiveExtractorInterface {
    public const US_PRG_SHA256 = 'fa456d852372173ea31b192459ba1a2026f779df67793327ba6e132476c1d034';
    
    public function extractArchive(SplFileInfo $archivePath, SplFileInfo $targetDirectory): bool {
        $contents = file_get_contents((string) $archivePath);
        if ($contents === false) {
            throw new RuntimeException('Could not read the configured Final Fantasy ROM.');
        }
        $prg = self::extractPrg($contents);
        FileSystem::ensureDirectory((string) $targetDirectory);
        return file_put_contents($targetDirectory . '/1', $prg) === strlen($prg);
    }
    
    public static function extractPrg(string $contents): string {
        if (strlen($contents) < 16 || ! str_starts_with($contents, "NES\x1A")) {
            throw new InvalidArgumentException('Expected an iNES Final Fantasy US ROM.');
        }
        $flags6 = ord($contents[6]);
        $mapper = ($flags6 >> 4) | (ord($contents[7]) & 0xF0);
        $headerSize = 16 + (($flags6 & 4) !== 0 ? 512 : 0);
        if (ord($contents[4]) !== 16 || ord($contents[5]) !== 0 || $mapper !== 1
            || strlen($contents) !== $headerSize + 0x40000) {
            throw new InvalidArgumentException('Only the original 256 KiB US Final Fantasy ROM is supported.');
        }
        $prg = substr($contents, $headerSize);
        if (hash('sha256', $prg) !== self::US_PRG_SHA256) {
            throw new InvalidArgumentException('This ROM does not match the original US release of Final Fantasy.');
        }
        return $prg;
    }
}
