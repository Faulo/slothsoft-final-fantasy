<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use RuntimeException;

/** NES 8x8 planar tiles decoded as class poses or a horizontal menu glyph strip. */
final class NesSpriteSheet {
    public const WIDTH = 128;
    public const HEIGHT = 24;
    
    // Conventional 2C02 RGB approximation. The ROM stores hardware color indices,
    // not RGB values; composite NES colors vary with the display and decoder.
    private const RGB = [
        0x7C7C7C, 0x0000FC, 0x0000BC, 0x4428BC, 0x940084, 0xA80020, 0xA81000, 0x881400,
        0x503000, 0x007800, 0x006800, 0x005800, 0x004058, 0x000000, 0x000000, 0x000000,
        0xBCBCBC, 0x0078F8, 0x0058F8, 0x6844FC, 0xD800CC, 0xE40058, 0xF83800, 0xE45C10,
        0xAC7C00, 0x00B800, 0x00A800, 0x00A844, 0x008888, 0x000000, 0x000000, 0x000000,
        0xF8F8F8, 0x3CBCFC, 0x6888FC, 0x9878F8, 0xF878F8, 0xF85898, 0xF87858, 0xFCA044,
        0xF8B800, 0xB8F818, 0x58D854, 0x58F898, 0x00E8D8, 0x787878, 0x000000, 0x000000,
        0xFCFCFC, 0xA4E4FC, 0xB8B8F8, 0xD8B8F8, 0xF8B8F8, 0xF8A4C0, 0xF0D0B0, 0xFCE0A8,
        0xF8D878, 0xD8F878, 0xB8F8B8, 0xB8F8D8, 0x00FCFC, 0xF8D8F8, 0x000000, 0x000000
    ];
    
    public static function toPng(string $tiles, string $palette, string $poses): string {
        if (strlen($tiles) !== 512 || strlen($palette) !== 4 || strlen($poses) !== 32) {
            throw new InvalidArgumentException('Expected 32 NES tiles, four palette entries and eight pose records.');
        }
        $scanlines = '';
        for ($y = 0; $y < self::HEIGHT; $y++) {
            $scanlines .= "\0"; // PNG filter: none.
            for ($pose = 0; $pose < 8; $pose++) {
                $leftTile = ord($poses[$pose * 4 + intdiv($y, 8)]);
                if ($leftTile > 30) {
                    throw new InvalidArgumentException('A pose references a tile outside the class graphics.');
                }
                for ($x = 0; $x < 16; $x++) {
                    $offset = ($leftTile + intdiv($x, 8)) * 16 + $y % 8;
                    $scanlines .= self::pixel($tiles, $offset, $x % 8);
                }
            }
        }
        return self::encodePng(self::WIDTH, self::HEIGHT, $scanlines, $palette, 0);
    }
    
    public static function toTileStrip(string $tiles, string $palette, int $transparent = 2): string {
        return self::toTileGrid($tiles, $palette, intdiv(strlen($tiles), 16), $transparent);
    }
    
    /** Complete tiles in row-major order, with sprite color zero transparent by default. */
    public static function toTileGrid(string $tiles, string $palette, int $columns, int $transparent = 0): string {
        if ($tiles === '' || strlen($tiles) % 16 !== 0 || strlen($tiles) > 4096
            || strlen($palette) !== 4 || $transparent < 0 || $transparent > 3) {
            throw new InvalidArgumentException('Expected complete NES tiles, four palette entries and a transparency index from 0 to 3.');
        }
        $count = intdiv(strlen($tiles), 16);
        if ($columns < 1 || $columns > $count || $count % $columns !== 0) {
            throw new InvalidArgumentException('Expected a whole number of tile rows.');
        }
        $height = intdiv($count, $columns) * 8;
        $scanlines = '';
        for ($y = 0; $y < $height; $y++) {
            $scanlines .= "\0";
            for ($column = 0; $column < $columns; $column++) {
                $tile = intdiv($y, 8) * $columns + $column;
                for ($x = 0; $x < 8; $x++) {
                    $scanlines .= self::pixel($tiles, $tile * 16 + $y % 8, $x);
                }
            }
        }
        return self::encodePng($columns * 8, $height, $scanlines, $palette, $transparent);
    }
    
    private static function pixel(string $tiles, int $offset, int $x): string {
        $shift = 7 - $x;
        return chr(((ord($tiles[$offset]) >> $shift) & 1)
            | (((ord($tiles[$offset + 8]) >> $shift) & 1) << 1));
    }
    
    /** Encode filtered, eight-bit indexed scanlines using NES hardware colors. */
    public static function encodePng(int $width, int $height, string $scanlines, string $palette, ?int $transparent = null): string {
        if ($width < 1 || $height < 1 || strlen($scanlines) !== ($width + 1) * $height
            || strlen($palette) < 1 || strlen($palette) > 256
            || ($transparent !== null && ($transparent < 0 || $transparent >= strlen($palette)))) {
            throw new InvalidArgumentException('Invalid indexed PNG dimensions or palette.');
        }
        $colors = '';
        for ($index = 0; $index < strlen($palette); $index++) {
            $colors .= substr(pack('N', self::RGB[ord($palette[$index]) & 0x3F]), 1);
        }
        $compressed = gzcompress($scanlines, 9);
        if ($compressed === false) {
            throw new RuntimeException('Could not encode the NES graphics PNG.');
        }
        $alpha = '';
        if ($transparent !== null) {
            $alpha = str_repeat("\xFF", strlen($palette));
            $alpha[$transparent] = "\0";
        }
        return "\x89PNG\r\n\x1A\n"
            . self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 3, 0, 0, 0))
            . self::chunk('PLTE', $colors)
            . ($alpha === '' ? '' : self::chunk('tRNS', $alpha))
            . self::chunk('IDAT', $compressed)
            . self::chunk('IEND', '');
    }
    
    private static function chunk(string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
