<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MenuFontTest extends TestCase {
    public function testFontContainsOriginalPixelsAndValidChecksums(): void {
        $tiles = str_repeat("\0", 2048);
        // Tile 80: one foreground pixel in the upper left, plus a background pixel.
        $tiles[0] = "\x80";
        $tiles[8] = "\xC0";
        $font = MenuFont::toTrueType($tiles);
        self::assertSame("\0\1\0\0", substr($font, 0, 4));
        self::assertSame(0xB1B0AFBA, array_sum(unpack('N*', $font)) & 0xFFFFFFFF);
        $tables = [];
        for ($offset = 12; $offset < 172; $offset += 16) {
            $entry = unpack('a4tag/Nchecksum/Noffset/Nlength', substr($font, $offset, 16));
            $data = substr($font, $entry['offset'], $entry['length']);
            $tables[$entry['tag']] = $data;
            if ($entry['tag'] === 'head') {
                $data = substr_replace($data, str_repeat("\0", 4), 8, 4);
            }
            $data .= str_repeat("\0", (4 - strlen($data) % 4) % 4);
            self::assertSame($entry['checksum'], array_sum(unpack('N*', $data)) & 0xFFFFFFFF, $entry['tag']);
        }
        self::assertSame(54, strlen($tables['head']));
        self::assertSame(36, strlen($tables['hhea']));
        self::assertSame(32, strlen($tables['maxp']));
        self::assertSame(78, strlen($tables['OS/2']));
        self::assertSame(32, strlen($tables['post']));
        self::assertSame([1, 0, 896, 128, 1024], array_values(unpack('n5', substr($tables['glyf'], 0, 10))));
        self::assertSame([0, 0, 128, 0], array_values(unpack('n4', substr($tables['glyf'], 18, 8))));
        self::assertSame([896, 128, 0, 65408], array_values(unpack('n4', substr($tables['glyf'], 26, 8))));
        $map = TextCodec::getFontCharacterMap();
        self::assertSame(0x8A, $map[ord('A')]);
        self::assertSame(0xA4, $map[ord('a')]);
        self::assertSame(0xD9, $map[0xE0D9]);
        self::assertSame(0xFF, $map[ord(' ')]);
        self::assertCount(128, array_filter(array_keys($map), static fn(int $code): bool => $code >= 0xE080));
        $tiles[0] = "\0";
        self::assertNotSame($font, MenuFont::toTrueType($tiles));
    }
    
    public function testRejectsIncompleteTiles(): void {
        $this->expectException(InvalidArgumentException::class);
        MenuFont::toTrueType('');
    }
}
