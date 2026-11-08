<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMXPath;
use RuntimeException;

/** Per-playthrough acquisition counts for the original US game's equipment. */
final class ItemSources {
    public static function appendTo(DOMDocument $document, DOMDocument $dataset): void {
        $raw = new DOMXPath($dataset);
        $raw->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        $layouts = self::binary($raw, 'map-layouts', 49152);
        $tilesets = self::binary($raw, 'map-tilesets', 61);
        $properties = self::binary($raw, 'map-properties', 2048);
        $objects = self::binary($raw, 'map-objects', 61 * 48);
        $treasures = self::binary($raw, 'chest-items', 256);
        $flags = self::binary($raw, 'initial-flags', 256);
        $types = self::binary($raw, 'shop-types', 71);
        $shops = $chests = $npcs = [];
        for ($map = 0; $map < 61; $map++) {
            $pointer = ord($layouts[$map * 2]) | (ord($layouts[$map * 2 + 1]) << 8);
            $tileset = ord($tilesets[$map]);
            if ($pointer < 128 || $pointer >= strlen($layouts) || $tileset > 7) {
                throw new RuntimeException('Invalid acquisition map data.');
            }
            $tiles = MapGraphics::decompress(substr($layouts, $pointer), 4096);
            foreach (array_unique(str_split($tiles)) as $tile) {
                $offset = $tileset * 256 + ord($tile) * 2;
                $special = ord($properties[$offset]) & 0x1E;
                $id = ord($properties[$offset + 1]);
                if ($special === 2 && $id > 0) {
                    $shops[$id] = true;
                } elseif ($special === 8 && (ord($flags[$id]) & 4) === 0) {
                    // Several physical chests share this global opened flag.
                    $chests[$id] = true;
                }
            }
            // The sixteenth record is padding; the game loads only fifteen NPCs.
            for ($slot = 0; $slot < 15; $slot++) {
                $npcs[ord($objects[$map * 48 + $slot * 3])] = true;
            }
        }
        $shopCounts = $chestCounts = [];
        $records = XmlElements::query($raw, '//sse:instruction[@name="shops"]/sse:group');
        foreach (array_keys($shops) as $id) {
            if ($id >= 71 || ! isset($records[$id])) {
                throw new RuntimeException('Invalid placed shop ID.');
            }
            $type = ord($types[$id]) & 7;
            if ($type > 1) {
                continue;
            }
            $inventory = self::decode((string) $raw->evaluate('string(sse:binary[@name="inventory"]/@value)', $records[$id]), 5);
            $seen = [];
            foreach (str_split($inventory) as $byte) {
                $item = ord($byte);
                if ($item === 0) {
                    break;
                }
                // Ignore bytes outside this shop type's equipment range.
                $start = $type === 0 ? 28 : 68;
                if ($item >= $start && $item < $start + 40 && ! isset($seen[$item])) {
                    $shopCounts[$item] = ($shopCounts[$item] ?? 0) + 1;
                    $seen[$item] = true;
                }
            }
        }
        foreach (array_keys($chests) as $id) {
            $item = ord($treasures[$id]);
            $chestCounts[$item] = ($chestCounts[$item] ?? 0) + 1;
        }
        $smith = (int) $raw->evaluate('string(//sse:integer[@name="smith-object"]/@value)');
        $reward = (int) $raw->evaluate('string(//sse:integer[@name="smith-weapon"]/@value)');
        $smithAvailable = $smith > 0 && $smith < 256 && isset($npcs[$smith]) && (ord($flags[$smith]) & 2) === 0;
        $domain = new DOMXPath($document);
        $domain->registerNamespace('sf1', GameData::XML_NAMESPACE);
        foreach (['weapons/sf1:weapon' => 27, 'armor/sf1:item' => 67] as $path => $base) {
            foreach (XmlElements::query($domain, '/sf1:final-fantasy/sf1:' . $path) as $item) {
                $id = (int) $item->getAttribute('id');
                $item->setAttribute('shop-count', (string) ($shopCounts[$base + $id] ?? 0));
                $item->setAttribute('chest-count', (string) ($chestCounts[$base + $id] ?? 0));
                $item->setAttribute('npc-count', $base === 27 && $id === $reward && $smithAvailable ? '1' : '0');
            }
        }
    }
    
    private static function binary(DOMXPath $xpath, string $name, int $size): string {
        return self::decode((string) $xpath->evaluate('string(//sse:binary[@name="' . $name . '"]/@value)'), $size);
    }
    
    private static function decode(string $hex, int $size): string {
        $bytes = hex2bin(str_replace(' ', '', trim($hex)));
        if ($bytes === false || strlen($bytes) !== $size) {
            throw new RuntimeException('Incomplete acquisition source data.');
        }
        return $bytes;
    }
}
