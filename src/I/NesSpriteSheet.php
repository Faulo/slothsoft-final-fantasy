<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use RuntimeException;

/** NES 8x8 planar tiles arranged into eight 16x24 battle poses, left to right. */
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
        $colors = '';
        for ($index = 0; $index < 4; $index++) {
            $colors .= substr(pack('N', self::RGB[ord($palette[$index]) & 0x3F]), 1);
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
                    $shift = 7 - $x % 8;
                    $pixel = ((ord($tiles[$offset]) >> $shift) & 1)
                        | (((ord($tiles[$offset + 8]) >> $shift) & 1) << 1);
                    $scanlines .= chr($pixel);
                }
            }
        }
        $compressed = gzcompress($scanlines, 9);
        if ($compressed === false) {
            throw new RuntimeException('Could not encode the class sprite PNG.');
        }
        // Indexed PNG keeps the game's four colors and makes sprite color zero transparent.
        return "\x89PNG\r\n\x1A\n"
            . self::chunk('IHDR', pack('NNCCCCC', self::WIDTH, self::HEIGHT, 8, 3, 0, 0, 0))
            . self::chunk('PLTE', $colors)
            . self::chunk('tRNS', "\0\xFF\xFF\xFF")
            . self::chunk('IDAT', $compressed)
            . self::chunk('IEND', '');
    }
    
    private static function chunk(string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }
}
