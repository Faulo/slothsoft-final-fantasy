<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;

/** The US game's single-tile alphabet, with text fallbacks and structured pictograms. */
final class TextCodec {
    
    private const ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    
    private const SYMBOLS = [
        0xBE => "'", 0xBF => ',', 0xC0 => '.', 0xC1 => ' ', 0xC2 => '-',
        0xC3 => '…', 0xC4 => '!', 0xC5 => '?',
        0xFF => ' '
    ];
    
    private const ICONS = [
        0xD4 => 'sword', 0xD5 => 'hammer', 0xD6 => 'knife',
        0xD7 => 'axe', 0xD8 => 'staff', 0xD9 => 'nunchuck',
        0xDA => 'armor', 0xDB => 'shield', 0xDC => 'helmet',
        0xDD => 'gauntlet', 0xDE => 'bracelet', 0xDF => 'shirt',
        0xE1 => 'potion'
    ];
    
    /** Unicode code points mapped to original NES tile IDs, including all tiles in the private-use area.
     * @return array<int, int>
     */
    public static function getFontCharacterMap(): array {
        $map = [];
        for ($tile = 0x80; $tile <= 0xFF; $tile++) {
            $map[0xE000 + $tile] = $tile;
        }
        foreach (str_split(self::ALPHABET) as $index => $character) {
            $map[ord($character)] = 0x80 + $index;
        }
        foreach (self::SYMBOLS as $tile => $character) {
            $map[mb_ord($character, 'UTF-8')] = $tile;
        }
        return $map;
    }
    
    public static function decode(string $bytes): string {
        $text = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            if ($byte === 0) {
                break;
            }
            $text .= isset(self::ICONS[$byte]) ? '[' . self::ICONS[$byte] . ']' : self::decodeByte($byte);
        }
        return rtrim($text);
    }
    
    /** Keeps glyph identity and position from the original encoded name, not its fallback string. */
    public static function createLabel(DOMDocument $document, string $bytes): DOMElement {
        $label = $document->createElementNS(GameData::XML_NAMESPACE, 'sf1:label');
        $text = '';
        foreach (unpack('C*', $bytes) ?: [] as $byte) {
            if ($byte === 0) {
                break;
            }
            if (isset(self::ICONS[$byte])) {
                if ($text !== '') {
                    $label->appendChild($document->createTextNode($text));
                    $text = '';
                }
                $icon = $document->createElementNS(GameData::XML_NAMESPACE, 'sf1:icon');
                $icon->setAttribute('glyph', (string) $byte);
                $icon->setAttribute('label', self::ICONS[$byte]);
                $label->appendChild($icon);
            } else {
                $text .= self::decodeByte($byte);
            }
        }
        if (rtrim($text) !== '') {
            $label->appendChild($document->createTextNode(rtrim($text)));
        }
        return $label;
    }
    
    private static function decodeByte(int $byte): string {
        return $byte >= 0x80 && $byte <= 0xBD
            ? self::ALPHABET[$byte - 0x80]
            : (self::SYMBOLS[$byte] ?? sprintf('[%02X]', $byte));
    }
}
