<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Slothsoft\FinalFantasy\Configuration;

final class MapGraphicsTest extends TestCase {
    use UnpackHelper;
    
    public function testLiteralRunAndZeroLengthRun(): void {
        self::assertSame("\x01\x02\x02\x02", MapGraphics::decompress("\x01\x82\x03\xFF", 4));
        self::assertSame(str_repeat("\x7E", 256), MapGraphics::decompress("\xFE\0\xFF", 256));
    }
    
    /** @dataProvider malformedLayouts */
    public function testRejectsMalformedLayout(string $data, int $size): void {
        $this->expectException(InvalidArgumentException::class);
        MapGraphics::decompress($data, $size);
    }
    
    /** @return list<array{string, int}> */
    public function malformedLayouts(): array {
        return [["\xFF", 1], ["\x80", 1], ["\x80\x02", 1], ["\x01", 2], ['', 0], ['', 4097]];
    }
    
    public function testQuadrantsBitplanesAndOpaquePalette(): void {
        $tiles = str_repeat("\0", 16) . str_repeat("\xFF", 8) . str_repeat("\0", 8)
            . str_repeat("\0", 8) . str_repeat("\xFF", 8) . str_repeat("\xFF", 16) . str_repeat("\0", 4032);
        $quadrants = str_repeat("\0", 128) . str_repeat("\x01", 128) . str_repeat("\x02", 128) . str_repeat("\x03", 128);
        $png = MapGraphics::standard(str_repeat("\x80\0", 16), $tiles, $quadrants, str_repeat("\xAA", 128), str_repeat("\x0F\x00\x10\x30", 12));
        self::assertStringNotContainsString('tRNS', $png);
        $offset = strpos($png, 'IDAT');
        self::assertIsInt($offset);
        $length = self::unpack('Nlength', substr($png, $offset - 4, 4))['length'];
        $pixels = gzuncompress(substr($png, $offset + 4, $length));
        self::assertIsString($pixels);
        self::assertSame(str_repeat("\0", 8) . str_repeat("\x09", 8), substr($pixels, 1, 16));
        self::assertSame(str_repeat("\x0A", 8) . str_repeat("\x0B", 8), substr($pixels, 8 * 1025 + 1, 16));
    }
    
    public function testAllOriginalMapsRenderWithDistinctLayouts(): void {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path)) {
            self::markTestSkipped('Supply the US ROM via FINAL_FANTASY_I_ROM and tests/bootstrap.php.');
        }
        $game = GameData::fromFile($path);
        $hashes = [];
        foreach (array_merge(['world'], array_map('strval', range(0, 60))) as $id) {
            $png = $game->getMap($id);
            $info = getimagesizefromstring($png);
            self::assertIsArray($info, $id);
            $size = $id === 'world' ? 4096 : 1024;
            self::assertSame([$size, $size, IMAGETYPE_PNG], array_slice($info, 0, 3), $id);
            $hashes[] = hash('sha256', $png);
        }
        self::assertCount(62, array_unique($hashes));
    }
}
