<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

final class ItemSourcesTest extends TestCase {
    public function testCountsPlacedSourcesAndSharedFlagsOnlyOnce(): void {
        [$domain, $dataset] = $this->fixture();
        ItemSources::appendTo($domain, $dataset);
        $xpath = new DOMXPath($domain);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        foreach ([1 => [2, 1, 0], 2 => [1, 1, 0], 3 => [0, 0, 1]] as $id => $counts) {
            foreach (array_combine(['shop-count', 'chest-count', 'npc-count'], $counts) as $attribute => $expected) {
                self::assertSame((string) $expected, $xpath->evaluate('string(//sf1:weapon[@id="' . $id . '"]/@' . $attribute . ')'));
            }
        }
        self::assertSame('1', $xpath->evaluate('string(//sf1:armor/sf1:item/@chest-count)'));
        self::assertSame('0', $xpath->evaluate('string(//sf1:armor/sf1:item/@shop-count)'));
        
        // Change only ROM data: the smith's reward moves, and an initially opened chest disappears.
        $raw = new DOMXPath($dataset);
        $raw->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        XmlElements::query($raw, '//sse:integer[@name="smith-weapon"]')[0]->setAttribute('value', '2');
        $flags = str_repeat("\0", 256);
        $flags[7] = $flags[9] = "\x04";
        XmlElements::query($raw, '//sse:binary[@name="initial-flags"]')[0]->setAttribute('value', bin2hex($flags));
        ItemSources::appendTo($domain, $dataset);
        self::assertSame('0', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@chest-count)'));
        self::assertSame('1', $xpath->evaluate('string(//sf1:weapon[@id="2"]/@npc-count)'));
        self::assertSame('0', $xpath->evaluate('string(//sf1:weapon[@id="3"]/@npc-count)'));
        
        $flags[9] = "\x06"; // Smith's completed event flag, separate from chest-open flag.
        XmlElements::query($raw, '//sse:binary[@name="initial-flags"]')[0]->setAttribute('value', bin2hex($flags));
        ItemSources::appendTo($domain, $dataset);
        self::assertSame('0', $xpath->evaluate('string(//sf1:weapon[@id="2"]/@npc-count)'));
    }
    
    /** @return array{DOMDocument, DOMDocument} */
    private function fixture(): array {
        $domain = new DOMDocument();
        $domain->loadXML('<final-fantasy xmlns="' . GameData::XML_NAMESPACE . '"><weapons><weapon id="1"/><weapon id="2"/><weapon id="3"/></weapons><armor><item id="1"/></armor></final-fantasy>');
        $dataset = new DOMDocument();
        $dataset->loadXML('<sse:savegame xmlns:sse="http://schema.slothsoft.net/savegame/editor"/>');
        $root = $dataset->documentElement;
        self::assertNotNull($root);
        $layouts = str_pad(str_repeat(pack('v', 128), 61), 128, "\0");
        $layouts .= "\x01\x02\x03\x04\x05\x06\x07" . str_repeat("\x80\0", 15) . "\x80\xF9\xFF";
        $properties = str_repeat("\0", 2048);
        foreach ([1 => [8, 7], 2 => [8, 7], 3 => [8, 8], 4 => [2, 1], 5 => [2, 2], 6 => [8, 9], 7 => [8, 10]] as $tile => [$special, $id]) {
            $properties[$tile * 2] = chr($special);
            $properties[$tile * 2 + 1] = chr($id);
        }
        $treasures = str_repeat("\0", 256);
        $treasures[7] = chr(28);
        $treasures[8] = chr(68);
        $treasures[9] = $treasures[10] = $treasures[200] = chr(29); // 200 is unplaced.
        $flags = str_repeat("\0", 256);
        $flags[9] = "\x04";
        $objects = str_repeat("\0", 61 * 48);
        $objects[0] = chr(9);
        foreach (['map-layouts' => str_pad($layouts, 49152, "\0"), 'map-tilesets' => str_repeat("\0", 61), 'map-properties' => $properties, 'map-objects' => $objects, 'chest-items' => $treasures, 'initial-flags' => $flags, 'shop-types' => str_repeat("\0", 71)] as $name => $bytes) {
            $node = $dataset->createElementNS($root->namespaceURI, 'sse:binary');
            $node->setAttribute('name', $name);
            $node->setAttribute('value', bin2hex($bytes));
            $root->appendChild($node);
        }
        $shops = $dataset->createElementNS($root->namespaceURI, 'sse:instruction');
        $shops->setAttribute('name', 'shops');
        $root->appendChild($shops);
        foreach (["\0\0\0\0\0", "\x1C\x1C\0\x1D\x1D", "\x1C\x1D\0\0\0", "\x1D\0\0\0\0"] as $inventory) {
            $group = $dataset->createElementNS($root->namespaceURI, 'sse:group');
            $node = $dataset->createElementNS($root->namespaceURI, 'sse:binary');
            $node->setAttribute('name', 'inventory');
            $node->setAttribute('value', bin2hex($inventory));
            $group->appendChild($node);
            $shops->appendChild($group);
        }
        foreach (['smith-object' => 9, 'smith-weapon' => 3] as $name => $value) {
            $node = $dataset->createElementNS($root->namespaceURI, 'sse:integer');
            $node->setAttribute('name', $name);
            $node->setAttribute('value', (string) $value);
            $root->appendChild($node);
        }
        return [$domain, $dataset];
    }
}
