<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NesSpriteSheetTest extends TestCase {
    public function testPngPreservesBitplanesTileOrderPoseRowsPaletteAndTransparency(): void {
        $tiles = str_repeat("\0", 512);
        $tiles[0] = "\xA0"; // first tile, pixels 0 and 2: low bit.
        $tiles[8] = "\x60"; // pixels 1 and 2: high bit.
        $tiles[16] = "\x80"; // second tile, leftmost pixel.
        $tiles[32 + 7 + 8] = "\x01"; // third tile, bottom-right pixel, high bit.
        $poses = str_repeat("\0\x02\x04\0", 8);
        $poses[4] = "\x02"; // second pose starts at third tile, independently of first pose.
        $png = NesSpriteSheet::toPng($tiles, "\x0F\x16\x30\x36", $poses);
        $info = getimagesizefromstring($png);
        self::assertIsArray($info);
        self::assertSame([128, 24, IMAGETYPE_PNG], array_slice($info, 0, 3));
        $chunks = [];
        for ($offset = 8; $offset < strlen($png);) {
            $length = unpack('Nlength', substr($png, $offset, 4))['length'];
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $length);
            self::assertSame(pack('N', crc32($type . $data)), substr($png, $offset + 8 + $length, 4));
            $chunks[$type] = $data;
            $offset += $length + 12;
        }
        self::assertSame("\0\0\0\xF8\x38\0\xFC\xFC\xFC\xF0\xD0\xB0", $chunks['PLTE']);
        self::assertSame("\0\xFF\xFF\xFF", $chunks['tRNS']);
        $pixels = gzuncompress($chunks['IDAT']);
        self::assertIsString($pixels);
        self::assertSame(129 * 24, strlen($pixels));
        self::assertSame("\0\x01\x02\x03\0", substr($pixels, 0, 5));
        self::assertSame("\x01", $pixels[9]); // adjacent tile, not adjacent bitplane.
        self::assertSame("\x02", $pixels[15 * 129 + 1 + 7]);
        self::assertSame("\x02", $pixels[7 * 129 + 1 + 16 + 7]);
        self::assertSame("\0", $pixels[1 + 16]);
    }
    
    public function testRejectsOutOfRangePoseTiles(): void {
        $this->expectException(InvalidArgumentException::class);
        NesSpriteSheet::toPng(str_repeat("\0", 512), str_repeat("\0", 4), str_repeat("\x1F", 32));
    }
    
    public function testRejectsTruncatedGraphics(): void {
        $this->expectException(InvalidArgumentException::class);
        NesSpriteSheet::toPng('', str_repeat("\0", 4), str_repeat("\0", 32));
    }
}
