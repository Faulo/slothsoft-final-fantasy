<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

/** The US game's single-tile alphabet. Equipment pictograms retain explicit labels. */
final class TextCodec {
    
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    
    private const SYMBOLS = [
        0xBE => "'", 0xBF => ',', 0xC0 => '.', 0xC1 => ' ', 0xC2 => '-',
        0xC3 => '…', 0xC4 => '!', 0xC5 => '?',
        0xD4 => '[sword]', 0xD5 => '[hammer]', 0xD6 => '[knife]',
        0xD7 => '[axe]', 0xD8 => '[staff]', 0xD9 => '[nunchuck]',
        0xDA => '[armor]', 0xDB => '[shield]', 0xDC => '[helmet]',
        0xDD => '[gauntlet]', 0xDE => '[bracelet]', 0xDF => '[shirt]',
        0xE1 => '[potion]', 0xFF => ' '
    ];
    
    public static function decode(string $bytes): string {
        $text = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            if ($byte === 0) {
                break;
            }
            $text .= $byte >= 0x80 && $byte <= 0xBD
                ? self::ALPHABET[$byte - 0x80]
                : (self::SYMBOLS[$byte] ?? sprintf('[%02X]', $byte));
        }
        return rtrim($text);
    }
}
