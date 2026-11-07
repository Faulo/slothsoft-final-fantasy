<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;

/** Converts the menu's 128 NES tiles to unhinted, monochrome TrueType outlines.
 * @see https://learn.microsoft.com/en-us/typography/opentype/spec/otff
 */
final class MenuFont {
    public static function toTrueType(string $tiles): string {
        if (strlen($tiles) !== 2048) {
            throw new InvalidArgumentException('The menu font requires 128 two-bitplane tiles.');
        }
        $glyf = '';
        $loca = pack('N', 0); // Glyph zero is the empty .notdef glyph.
        $hmtx = pack('nn', 1024, 0);
        for ($tile = 0; $tile < 128; $tile++) {
            $loca .= pack('N', strlen($glyf));
            $points = [];
            for ($y = 0; $y < 8; $y++) {
                $ink = ord($tiles[$tile * 16 + $y]) & ord($tiles[$tile * 16 + $y + 8]);
                for ($x = 0; $x < 8; $x++) {
                    if (($ink & (0x80 >> $x)) === 0) {
                        continue;
                    }
                    $left = $x * 128;
                    while ($x < 7 && ($ink & (0x80 >> ($x + 1))) !== 0) {
                        $x++;
                    }
                    $right = ($x + 1) * 128;
                    $bottom = (7 - $y) * 128;
                    array_push($points, [$left, $bottom], [$left, $bottom + 128], [$right, $bottom + 128], [$right, $bottom]);
                }
            }
            $xmin = $points ? min(array_column($points, 0)) : 0;
            $hmtx .= pack('nn', 1024, $xmin);
            if (! $points) {
                continue;
            }
            $count = count($points);
            $outline = pack('n5', intdiv($count, 4), $xmin, min(array_column($points, 1)), max(array_column($points, 0)), max(array_column($points, 1)));
            for ($end = 3; $end < $count; $end += 4) {
                $outline .= pack('n', $end);
            }
            $outline .= pack('n', 0) . str_repeat("\x01", $count);
            foreach ([0, 1] as $axis) {
                $previous = 0;
                foreach ($points as $point) {
                    $outline .= pack('n', ($point[$axis] - $previous) & 0xFFFF);
                    $previous = $point[$axis];
                }
            }
            $glyf .= self::pad($outline);
        }
        $loca .= pack('N', strlen($glyf));
        $tables = [
            'head' => pack('N4n2', 0x10000, 0x10000, 0, 0x5F0F3CF5, 3, 1024)
                . str_repeat("\0", 16) . pack('n9', 0, 0, 1024, 1024, 0, 8, 2, 1, 0),
            'hhea' => pack('Nn*', 0x10000, 1024, 0, 0, 1024, 0, 0, 1024, 1, 0, 0, 0, 0, 0, 0, 0, 129),
            'maxp' => pack('Nn*', 0x10000, 129, 256, 64, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0),
            'OS/2' => pack('n*', 0, 1024, 400, 5, 0, 512, 512, 0, 128, 512, 512, 0, 512, 64, 512, 0)
                . str_repeat("\0", 10) . pack('N4', 1, 0x12004000, 0, 0) . 'SLOT'
                . pack('n*', 64, 32, 0xFFFF, 1024, 0, 0, 1024, 0),
            'name' => self::names(),
            'cmap' => self::characterMap(),
            'post' => pack('N2n2N5', 0x30000, 0, 0, 64, 1, 0, 0, 0, 0),
            'loca' => $loca, 'glyf' => $glyf, 'hmtx' => $hmtx
        ];
        ksort($tables);
        $header = pack('Nn4', 0x10000, 10, 128, 3, 32);
        $body = '';
        $headOffset = 0;
        foreach ($tables as $tag => $data) {
            $offset = 172 + strlen($body);
            if ($tag === 'head') {
                $headOffset = $offset;
            }
            $header .= $tag . pack('N3', self::checksum($data), $offset, strlen($data));
            $body .= self::pad($data);
        }
        $font = $header . $body;
        return substr_replace($font, pack('N', (0xB1B0AFBA - self::checksum($font)) & 0xFFFFFFFF), $headOffset + 8, 4);
    }
    
    private static function characterMap(): string {
        $map = TextCodec::getFontCharacterMap();
        ksort($map);
        // Format 4 is limited to the BMP. Keep it for existing Unicode clients.
        $bmp = array_filter($map, static fn(int $code): bool => $code < 0xFFFF, ARRAY_FILTER_USE_KEY);
        $bmp[0xFFFF] = 0x7F; // Sentinel maps to missing glyph zero.
        $count = count($bmp);
        $power = (int) floor(log($count, 2));
        $ends = $starts = $deltas = '';
        foreach ($bmp as $code => $tile) {
            $ends .= pack('n', $code);
            $starts .= pack('n', $code);
            $deltas .= pack('n', ($tile - 0x7F - $code) & 0xFFFF);
        }
        $subtable = pack('n7', 4, 16 + 8 * $count, 0, 2 * $count, 2 * (1 << $power), $power, 2 * $count - 2 * (1 << $power))
            . $ends . pack('n', 0) . $starts . $deltas . str_repeat("\0", 2 * $count);
        // Format 12 contains the complete repertoire, including supplementary-plane emoji.
        $groups = '';
        foreach ($map as $code => $tile) {
            $groups .= pack('N3', $code, $code, $tile - 0x7F);
        }
        $full = pack('n2N3', 12, 0, 16 + strlen($groups), 0, count($map)) . $groups;
        $fullOffset = 36 + strlen($subtable);
        return pack('n2', 0, 4)
            . pack('n2N', 0, 3, 36) . pack('n2N', 0, 4, $fullOffset)
            . pack('n2N', 3, 1, 36) . pack('n2N', 3, 10, $fullOffset)
            . $subtable . $full;
    }
    
    private static function names(): string {
        $names = [1 => 'Final Fantasy I', 2 => 'Regular', 3 => 'Slothsoft-FinalFantasyI-ROM-1', 4 => 'Final Fantasy I', 5 => 'Version 1.0', 6 => 'FinalFantasyI'];
        $records = $strings = '';
        foreach ($names as $id => $name) {
            $encoded = mb_convert_encoding($name, 'UTF-16BE', 'UTF-8');
            $records .= pack('n6', 3, 1, 0x409, $id, strlen($encoded), strlen($strings));
            $strings .= $encoded;
        }
        return pack('n3', 0, count($names), 6 + 12 * count($names)) . $records . $strings;
    }
    
    private static function pad(string $data): string {
        return $data . str_repeat("\0", (4 - strlen($data) % 4) % 4);
    }
    
    private static function checksum(string $data): int {
        $sum = 0;
        foreach (unpack('N*', self::pad($data)) ?: [] as $word) {
            $sum = ($sum + $word) & 0xFFFFFFFF;
        }
        return $sum;
    }
}
