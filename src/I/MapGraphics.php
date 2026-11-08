<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;

/** Static map atlas: original tile layouts, with rooms revealed and chests closed. */
final class MapGraphics {
    /** Decode the game's literal/run format, bounded by the destination size. */
    public static function decompress(string $data, int $size): string {
        if ($size < 1 || $size > 4096) {
            throw new InvalidArgumentException('Invalid map size.');
        }
        $result = '';
        $length = strlen($data);
        for ($offset = 0; $offset < $length; $offset++) {
            $value = ord($data[$offset]);
            if ($value === 255) {
                break;
            }
            $count = 1;
            if ($value >= 128) {
                if (++$offset >= $length) {
                    throw new InvalidArgumentException('Truncated map run.');
                }
                $count = ord($data[$offset]) ?: 256;
            }
            $result .= str_repeat(chr($value & 127), $count);
            if (strlen($result) > $size) {
                throw new InvalidArgumentException('Map run exceeds its destination.');
            }
            if (strlen($result) === $size) {
                return $result;
            }
        }
        throw new InvalidArgumentException('Incomplete map layout.');
    }
    
    public static function standard(string $layout, string $tiles, string $quadrants, string $attributes, string $palettes): string {
        if (strlen($palettes) !== 48) {
            throw new InvalidArgumentException('Expected outdoor, sprite and indoor palettes.');
        }
        // Palette 0 reveals room contents. Palette 1 contains room boundaries:
        // retain the outdoor wall highlights while revealing their indoor shadows.
        // Palettes 2 and 3 retain the outdoor terrain and menu/sign colors.
        $palette = substr($palettes, 32, 6) . substr($palettes, 6, 10);
        return self::render(self::decompress($layout, 4096), 64, $tiles, $quadrants, $attributes, $palette);
    }
    
    public static function world(string $layout, string $tiles, string $quadrants, string $attributes, string $palette): string {
        if (strlen($layout) !== 16384) {
            throw new InvalidArgumentException('Expected the overworld layout bank.');
        }
        $map = '';
        for ($row = 0; $row < 256; $row++) {
            $pointer = (ord($layout[$row * 2]) | (ord($layout[$row * 2 + 1]) << 8)) - 0x8000;
            if ($pointer < 512 || $pointer >= strlen($layout)) {
                throw new InvalidArgumentException('Invalid overworld row pointer.');
            }
            $map .= self::decompress(substr($layout, $pointer), 256);
        }
        return self::render($map, 256, $tiles, $quadrants, $attributes, $palette);
    }
    
    private static function render(string $map, int $width, string $tiles, string $quadrants, string $attributes, string $palette): string {
        if (strlen($tiles) !== 4096 || strlen($quadrants) !== 512 || strlen($attributes) !== 128 || strlen($palette) !== 16) {
            throw new InvalidArgumentException('Incomplete map graphics.');
        }
        // Decode each metatile once, then assemble whole scanline fragments.
        $rows = [];
        for ($tile = 0; $tile < 128; $tile++) {
            $base = (ord($attributes[$tile]) & 3) * 4;
            for ($y = 0; $y < 16; $y++) {
                $line = '';
                for ($x = 0; $x < 16; $x++) {
                    $quadrant = intdiv($y, 8) * 2 + intdiv($x, 8);
                    $offset = ord($quadrants[$quadrant * 128 + $tile]) * 16 + $y % 8;
                    $shift = 7 - $x % 8;
                    $pixel = ((ord($tiles[$offset]) >> $shift) & 1) | (((ord($tiles[$offset + 8]) >> $shift) & 1) << 1);
                    $line .= chr($pixel === 0 ? 0 : $base + $pixel);
                }
                $rows[$tile][$y] = $line;
            }
        }
        $scanlines = '';
        $height = intdiv(strlen($map), $width);
        for ($row = 0; $row < $height; $row++) {
            for ($y = 0; $y < 16; $y++) {
                $scanlines .= "\0";
                for ($column = 0; $column < $width; $column++) {
                    $scanlines .= $rows[ord($map[$row * $width + $column])][$y];
                }
            }
        }
        return NesSpriteSheet::encodePng($width * 16, $height * 16, $scanlines, $palette);
    }
}
