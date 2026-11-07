<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use InvalidArgumentException;

/** Host applications supply private game files; the module ships no game binaries. */
final class Configuration {
    /** @var array<string, string> */
    private static array $romPaths = [];
    
    public static function setRomPath(string $title, ?string $path): void {
        self::validateTitle($title);
        if ($path === null) {
            unset(self::$romPaths[$title]);
        } elseif ($path === '') {
            throw new InvalidArgumentException('The ROM path must not be empty. Use null to clear it.');
        } else {
            self::$romPaths[$title] = $path;
        }
    }
    
    public static function getRomPath(string $title): ?string {
        self::validateTitle($title);
        return self::$romPaths[$title] ?? null;
    }
    
    private static function validateTitle(string $title): void {
        if ($title !== 'i') {
            throw new InvalidArgumentException('Only Final Fantasy I is currently supported.');
        }
    }
}
