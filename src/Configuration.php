<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

/** Host applications supply private game files; the module ships no game binaries. */
final class Configuration {
    /** @var array<string, string> */
    private static array $romPaths = [];
    
    /** Accept a plain ROM or a ZIP containing exactly one .nes file. */
    public static function setRomPath(string $title, ?string $path): void {
        self::validateTitle($title);
        if ($path === null) {
            unset(self::$romPaths[$title]);
        } elseif ($path === '') {
            throw new InvalidArgumentException('The ROM path must not be empty. Use null to clear it.');
        } else {
            self::$romPaths[$title] = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'zip'
                ? self::unpackRom($path) : $path;
        }
    }
    
    /** Return the plain ROM path, using a temporary file for ZIP sources. */
    public static function getRomPath(string $title): ?string {
        self::validateTitle($title);
        return self::$romPaths[$title] ?? null;
    }
    
    private static function unpackRom(string $path): string {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('The ROM ZIP is unreadable or invalid.');
        }
        try {
            $entry = null;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = $zip->getNameIndex($index);
                if ($name !== false && strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'nes') {
                    if ($entry !== null) {
                        throw new InvalidArgumentException('The ROM ZIP must contain exactly one .nes file.');
                    }
                    $entry = $index;
                }
            }
            if ($entry === null) {
                throw new InvalidArgumentException('The ROM ZIP must contain exactly one .nes file.');
            }
            $info = $zip->statIndex($entry);
            // The US iNES file can include a 512-byte trainer, but no CHR ROM.
            if ($info === false || $info['size'] > 16 + 512 + 0x40000) {
                throw new InvalidArgumentException('The ZIP entry exceeds the supported US ROM size.');
            }
            $contents = $zip->getFromIndex($entry);
            if ($contents === false) {
                throw new RuntimeException('Could not read the ROM from its ZIP.');
            }
            // Never use the entry's filename as a destination path.
            $target = temp_file('final-fantasy-rom');
            if (file_put_contents($target, $contents) !== strlen($contents)) {
                throw new RuntimeException('Could not prepare the ROM from its ZIP.');
            }
            return $target;
        } finally {
            $zip->close();
        }
    }
    
    private static function validateTitle(string $title): void {
        if ($title !== 'i') {
            throw new InvalidArgumentException('Only Final Fantasy I is currently supported.');
        }
    }
}
