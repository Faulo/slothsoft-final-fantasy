<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MenuFontTest extends TestCase {
    use UnpackHelper;
    public function testFontContainsOriginalPixelsAndValidChecksums(): void {
        $tiles = str_repeat("\0", 2048);
        // Tile 80: one foreground pixel in the upper left, plus a background pixel.
        $tiles[0] = "\x80";
        $tiles[8] = "\xC0";
        $font = MenuFont::toTrueType($tiles);
        self::assertSame("\0\1\0\0", substr($font, 0, 4));
        self::assertSame(0xB1B0AFBA, array_sum(self::unpack('N*', $font)) & 0xFFFFFFFF);
        $tables = [];
        for ($offset = 12; $offset < 172; $offset += 16) {
            $tag = substr($font, $offset, 4);
            $entry = self::unpack('Nchecksum/Noffset/Nlength', substr($font, $offset + 4, 12));
            $data = substr($font, $entry['offset'], $entry['length']);
            $tables[$tag] = $data;
            if ($tag === 'head') {
                $data = substr_replace($data, str_repeat("\0", 4), 8, 4);
            }
            $data .= str_repeat("\0", (4 - strlen($data) % 4) % 4);
            self::assertSame($entry['checksum'], array_sum(self::unpack('N*', $data)) & 0xFFFFFFFF, $tag);
        }
        self::assertSame(54, strlen($tables['head']));
        self::assertSame(36, strlen($tables['hhea']));
        self::assertSame(32, strlen($tables['maxp']));
        self::assertSame(78, strlen($tables['OS/2']));
        self::assertSame(32, strlen($tables['post']));
        self::assertSame([1, 0, 896, 128, 1024], array_values(self::unpack('n5', substr($tables['glyf'], 0, 10))));
        self::assertSame([0, 0, 128, 0], array_values(self::unpack('n4', substr($tables['glyf'], 18, 8))));
        self::assertSame([896, 128, 0, 65408], array_values(self::unpack('n4', substr($tables['glyf'], 26, 8))));
        $map = TextCodec::getFontCharacterMap();
        self::assertSame(0x8A, $map[ord('A')]);
        self::assertSame(0xA4, $map[ord('a')]);
        self::assertSame(0xD9, $map[0xE0D9]);
        self::assertSame(0xFF, $map[ord(' ')]);
        self::assertCount(128, array_filter(array_keys($map), static fn(int $code): bool => $code >= 0xE080 && $code <= 0xE0FF));
        self::assertSame(0xFFFF, self::unpack('n', substr($tables['OS/2'], 66, 2))[1]);
        $cmap = $tables['cmap'];
        self::assertSame(4, self::unpack('n', substr($cmap, 2, 2))[1]);
        $records = [];
        for ($offset = 4; $offset < 36; $offset += 8) {
            $record = self::unpack('nplatform/nencoding/Noffset', substr($cmap, $offset, 8));
            $records[$record['platform'] . ':' . $record['encoding']] = $record['offset'];
        }
        self::assertSame(['0:3', '0:4', '3:1', '3:10'], array_keys($records));
        self::assertSame($records['0:3'], $records['3:1']);
        self::assertSame($records['0:4'], $records['3:10']);
        $bmp = self::readCmap4(substr($cmap, $records['3:1']));
        $full = self::readCmap12(substr($cmap, $records['3:10']));
        foreach ($map as $code => $tile) {
            self::assertSame($tile - 0x7F, $full[$code], sprintf('U+%04X', $code));
            if ($code < 0xFFFF) {
                self::assertSame($full[$code], $bmp[$code]);
            }
        }
        self::assertSame(0, $bmp[0xFFFF]);
        // Supplementary code points must never be truncated into BMP mappings.
        self::assertArrayNotHasKey(0xF9BA, $bmp);
        $tiles[0] = "\0";
        self::assertNotSame($font, MenuFont::toTrueType($tiles));
    }
    
    public function testSelectedUnicodeIconsMapToTheirOriginalTiles(): void {
        $expected = [
            0x1F5E1 => 0xD4, 0x1F528 => 0xD5, 0x1F52A => 0xD6,
            0x1FA93 => 0xD7, 0x1FA84 => 0xD8, 0x1F962 => 0xD9,
            0x1F9BA => 0xDA, 0x26E8 => 0xDB, 0x1FA96 => 0xDC,
            0x1F9E4 => 0xDD, 0x1F48D => 0xDE, 0x1F455 => 0xDF, 0x1F9F4 => 0xE1
        ];
        $map = TextCodec::getFontCharacterMap();
        foreach ($expected as $code => $tile) {
            self::assertSame($tile, $map[$code]);
            self::assertSame($map[0xE000 + $tile], $map[$code]);
        }
    }
    
    /** @return array<int, int> */
    private static function readCmap12(string $data): array {
        $header = self::unpack('nformat/nreserved/Nlength/Nlanguage/Ncount', $data);
        self::assertSame(12, $header['format']);
        self::assertSame(strlen($data), $header['length']);
        $map = [];
        for ($index = 0; $index < $header['count']; $index++) {
            $group = self::unpack('Nstart/Nend/Nglyph', substr($data, 16 + 12 * $index, 12));
            for ($code = $group['start']; $code <= $group['end']; $code++) {
                $map[$code] = $group['glyph'] + $code - $group['start'];
            }
        }
        return $map;
    }
    
    /** @return array<int, int> */
    private static function readCmap4(string $data): array {
        $header = self::unpack('nformat/nlength/nlanguage/nsegCountX2', $data);
        self::assertSame(4, $header['format']);
        $count = intdiv($header['segCountX2'], 2);
        $map = [];
        for ($index = 0; $index < $count; $index++) {
            $end = self::unpack('n', substr($data, 14 + 2 * $index, 2))[1];
            $start = self::unpack('n', substr($data, 16 + 2 * $count + 2 * $index, 2))[1];
            $delta = self::unpack('n', substr($data, 16 + 4 * $count + 2 * $index, 2))[1];
            $range = self::unpack('n', substr($data, 16 + 6 * $count + 2 * $index, 2))[1];
            self::assertSame(0, $range);
            for ($code = $start; $code <= $end; $code++) {
                $map[$code] = ($code + $delta) & 0xFFFF;
            }
        }
        return $map;
    }
    
    public function testRejectsIncompleteTiles(): void {
        $this->expectException(InvalidArgumentException::class);
        MenuFont::toTrueType('');
    }
}
