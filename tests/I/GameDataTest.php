<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Slothsoft\Savegame\Build\XmlBuilder;
use Slothsoft\Savegame\Editor;
use Slothsoft\Savegame\EditorConfig;
use Slothsoft\Savegame\Node\ArchiveParser\CopyArchiveBuilder;
use Slothsoft\Savegame\Node\ArchiveParser\CopyArchiveExtractor;
use SplFileInfo;
use XSLTProcessor;

/** @covers \Slothsoft\FinalFantasy\I\GameData */
final class GameDataTest extends TestCase {
    public function testManifestReadsPointersTablesAndImplementedEffectsFromSyntheticPrg(): void {
        $prg = str_repeat("\0", 0x40000);
        $prg[0x25000 + 2 * 512] = "\x81";
        $prg[0x3ECA4 + 2] = "\x01";
        $prg[0x3EBA5 + 4] = "\x0F";
        $prg[0x3200C + 4] = "\x08";
        for ($id = 0; $id < 256; $id++) {
            $prg = substr_replace($prg, pack('v', 0xBA00), 0x2B700 + $id * 2, 2);
        }
        $prg = substr_replace($prg, "\x8A\x95\x99\x91\x8A\0", 0x2BA00, 6); // ALPHA
        $prg = substr_replace($prg, "\x8B\x8E\x9D\x8A\0", 0x2BA20, 5); // BETA
        for ($class = 0; $class < 12; $class++) {
            $prg = substr_replace($prg, pack('v', 0xB000 + $class * 8), 0x3AD00 + $class * 2, 2);
            $prg = substr_replace($prg, str_repeat("\xFF", 8), 0x3B000 + $class * 8, 8);
        }
        $prg[0x3B000 + 11 * 8] = "\x7F"; // Only class 11 can learn the first spell.
        $prg = substr_replace($prg, "\x0A\x17\x63\x01\xFF\xFF", 0x30000, 6);
        $prg = substr_replace($prg, pack('v', 1234), 0x37C00 + 28 * 2, 2);
        $prg = substr_replace($prg, pack('v', 0x7FF), 0x3BF50, 2); // Only class 0.
        $prg = substr_replace($prg, "\x05\x09\xFF\x02", 0x30140, 4);
        $prg[0x3BCD1] = "\x03";
        $prg = substr_replace($prg, "\x11\x22\x44\x08\x10", 0x301E0, 5);
        $prg[0x33874] = chr(151);
        $prg[0x33A86] = chr(199);
        $prg[0x3386F] = chr(43);
        $prg[0x33AE0] = chr(45);
        $prg[0x33AE5] = chr(1);
        $prg[0x3AF5F] = chr(7);
        $prg[0x3AF61] = chr(48);
        
        [$dataset, $domain] = $this->parseSyntheticPrg($prg);
        $raw = new DOMXPath($dataset);
        $raw->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        self::assertStringStartsWith('81 00 ', $raw->evaluate('string(//sse:instruction[@name="class-graphics"]/sse:group[3]/sse:image/@value)'));
        self::assertSame(12.0, $raw->evaluate('count(//sse:instruction[@name="class-graphics"]/sse:group/sse:image)'));
        self::assertStringStartsWith('00 00 01 ', $raw->evaluate('string(//sse:binary[@name="class-palettes"]/@value)'));
        self::assertStringStartsWith('00 00 00 00 0f ', $raw->evaluate('string(//sse:binary[@name="sprite-palettes"]/@value)'));
        self::assertStringStartsWith('00 00 00 00 08 ', $raw->evaluate('string(//sse:binary[@name="class-poses"]/@value)'));
        self::assertSame('23', $raw->evaluate('string(//sse:instruction[@name="weapons"]/sse:group[1]/sse:integer[@name="damage"]/@value)'));
        self::assertSame(mb_convert_encoding("\x8A\x95\x99\x91\x8A", 'UTF-8', 'ISO-8859-1'), $raw->evaluate('string(//sse:instruction[@name="weapon-names"]/sse:group[1]/sse:string/@value)'));
        self::assertTrue($domain->schemaValidate(dirname(__DIR__, 2) . '/assets/schema/i/1.0.xsd'));
        $xpath = new DOMXPath($domain);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('ALPHA', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@name)'));
        self::assertSame('1234', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@price)'));
        self::assertSame('1', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@critical)'));
        self::assertSame(0.0, $xpath->evaluate('count(//sf1:weapon/@stored-critical | //sf1:weapon/@unused-element | //sf1:weapon/@unused-category)'));
        self::assertSame(1.0, $xpath->evaluate('count(//sf1:weapon[@id="1"]/sf1:class-ref)'));
        self::assertSame('0', $xpath->evaluate('string(//sf1:weapon[@id="1"]/sf1:class-ref/@id)'));
        self::assertSame('255', $xpath->evaluate('string(//sf1:armor/sf1:item[@id="1"]/@resistance-mask)'));
        self::assertSame('3', $xpath->evaluate('string(//sf1:armor/sf1:item[@id="1"]/@category)'));
        self::assertSame('34', $xpath->evaluate('string(//sf1:spell[@id="1"]/@effectivity)'));
        self::assertSame('151', $xpath->evaluate('string(//sf1:spell-rules/@base-hit)'));
        self::assertSame('199', $xpath->evaluate('string(//sf1:spell-rules/@roll-max)'));
        self::assertSame('43', $xpath->evaluate('string(//sf1:spell-rules/@weakness-hit)'));
        self::assertSame('301', $xpath->evaluate('string(//sf1:spell-rules/@hp-limit)'));
        self::assertStringContainsString('Raises evasion of each ally by 34', $xpath->evaluate('string(//sf1:spell[@id="1"]/sf1:effect-description)'));
        self::assertStringContainsString('48-55 HP', $xpath->evaluate('string(//sf1:spell[@id="1"]/sf1:effect-description)'));
        self::assertSame(1.0, $xpath->evaluate('count(//sf1:spell[@id="1"]/sf1:class-ref)'));
        self::assertSame('11', $xpath->evaluate('string(//sf1:spell[@id="1"]/sf1:class-ref/@id)'));
        
        // Change only the source pointer: parsing and the domain transform must follow it.
        $prg = substr_replace($prg, pack('v', 0xBA20), 0x2B700 + 28 * 2, 2);
        [, $changed] = $this->parseSyntheticPrg($prg);
        $xpath = new DOMXPath($changed);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('BETA', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@name)'));
        self::assertSame('ALPHA', $xpath->evaluate('string(//sf1:weapon[@id="2"]/@name)'));
    }
    
    /**
     * Exercise the public manifest with a synthetic PRG; production GameData still requires the US ROM hash.
     * @return array{DOMDocument, DOMDocument}
     */
    private function parseSyntheticPrg(string $prg): array {
        $directory = temp_dir('final-fantasy-synthetic');
        file_put_contents($directory . '/game.nes', $prg);
        $config = new EditorConfig(
            new SplFileInfo($directory), new SplFileInfo($directory), new SplFileInfo($directory),
            new SplFileInfo(dirname(__DIR__, 2) . '/assets/i/savegame/rom.xml'),
            ['NES' => new CopyArchiveExtractor()], ['NES' => new CopyArchiveBuilder()]
        );
        $savegame = (new Editor($config))->loadSavegame(true, true);
        $builder = new XmlBuilder($savegame);
        $builder->setCacheDirectory($directory . '/xml');
        $dataset = new DOMDocument();
        self::assertTrue($dataset->loadXML(implode('', iterator_to_array($builder->toChunks()))));
        $input = clone $dataset;
        $xpath = new DOMXPath($input);
        $xpath->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        foreach ($xpath->query('//sse:string[@name="text"]') as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $bytes = mb_convert_encoding($node->getAttribute('value'), 'ISO-8859-1', 'UTF-8');
            $node->setAttribute('value', TextCodec::decode($bytes));
            $node->appendChild(TextCodec::createLabel($input, $bytes));
        }
        $stylesheet = new DOMDocument();
        self::assertTrue($stylesheet->load(dirname(__DIR__, 2) . '/assets/i/xsl/data.xsl'));
        $processor = new XSLTProcessor();
        $processor->importStylesheet($stylesheet);
        $processor->setParameter('', 'rom-sha256', str_repeat('0', 64));
        $domain = $processor->transformToDoc($input);
        self::assertInstanceOf(DOMDocument::class, $domain);
        SpellEffects::appendTo($domain);
        return [$dataset, $domain];
    }
    
    public function testItemIconsAndMixedNamesComeFromSyntheticRomBytes(): void {
        $prg = str_repeat("\0", 0x40000);
        $prg = substr_replace($prg, pack('v', 0xBA00), 0x2B700 + 28 * 2, 2);
        $prg = substr_replace($prg, "\x8A\xD9\x8B\xD4\xFF\0", 0x2BA00, 6);
        $prg[0x24D40] = "\x80";
        $prg = substr_replace($prg, "\x0F\0\x01\x30", 0x3AD7C, 4);
        [$dataset, $domain] = $this->parseSyntheticPrg($prg);
        $raw = new DOMXPath($dataset);
        $raw->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        self::assertStringStartsWith('80 00 ', $raw->evaluate('string(//sse:image[@name="item-icons"]/@value)'));
        self::assertSame('224', $raw->evaluate('string(//sse:image[@name="item-icons"]/@size)'));
        self::assertSame('0f 00 01 30 ', $raw->evaluate('string(//sse:binary[@name="menu-palette"]/@value)'));
        self::assertTrue($domain->schemaValidate(dirname(__DIR__, 2) . '/assets/schema/i/1.0.xsd'));
        $xpath = new DOMXPath($domain);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('A[nunchuck]B[sword]', $xpath->evaluate('string(//sf1:weapon[@id="1"]/@name)'));
        self::assertSame('217', $xpath->evaluate('string(//sf1:weapon[@id="1"]/sf1:label/sf1:icon[1]/@glyph)'));
        self::assertSame('212', $xpath->evaluate('string(//sf1:weapon[@id="1"]/sf1:label/sf1:icon[2]/@glyph)'));
        self::assertSame('B', $xpath->evaluate('string(//sf1:weapon[@id="1"]/sf1:label/text()[2])'));
    }
    
    public function testRejectsNonRom(): void {
        $this->expectException(InvalidArgumentException::class);
        new GameData(str_repeat("\0", 8192));
    }
    
    public function testRejectsUnknownPrg(): void {
        $this->expectException(InvalidArgumentException::class);
        new GameData("NES\x1A\x10\x00\x13" . str_repeat("\0", 9 + 0x40000));
    }
    
    public function testExtractsEquipmentAndActualCriticalRate(): void {
        $rom = new GameData($this->localRom());
        $weapons = $rom->getWeapons();
        self::assertCount(40, $weapons);
        self::assertSame('Falchon', $weapons[15]['name']);
        self::assertSame(450, $weapons[15]['price']);
        self::assertSame(15, $weapons[15]['damage']);
        self::assertSame(10, $weapons[15]['hit']);
        self::assertSame(15, $weapons[15]['critical']);
        self::assertSame([0, 1, 3, 6, 7, 9], $weapons[15]['classes']);
        self::assertArrayNotHasKey('elementMask', $weapons[39]);
        self::assertArrayNotHasKey('categoryMask', $weapons[39]);
        self::assertSame(range(0, 11), $weapons[40]['classes']);
        
        $armor = $rom->getArmor();
        self::assertCount(40, $armor);
        self::assertSame('Ribbon', $armor[32]['name']);
        self::assertSame(255, $armor[32]['resistanceMask']);
        self::assertSame(2, $armor[32]['category']);
        self::assertSame(3, $armor[40]['category']);
    }
    
    public function testSpellsAndClassRestrictionsResolve(): void {
        $rom = new GameData($this->localRom());
        $spells = $rom->getSpells();
        self::assertCount(64, $spells);
        self::assertSame('CURE', $spells[1]['name']);
        self::assertSame([3, 4, 6, 9, 10], $spells[1]['classes']);
        self::assertSame([11], $spells[61]['classes']);
        self::assertSame('NUKE', $spells[61]['name']);
        self::assertSame('Restores 33-66 HP to one ally in battle.', $spells[17]['effectDescription'][0]);
        self::assertSame('Outside battle, restores 32-63 HP to one ally.', $spells[17]['effectDescription'][1]);
        self::assertSame('Restores 48-96 HP to each ally in battle.', $spells[36]['effectDescription'][0]);
        self::assertStringContainsString('Always misses', $spells[7]['effectDescription'][0]);
        self::assertStringContainsString('Raises evasion of each enemy by 20', $spells[24]['effectDescription'][0]);
        self::assertStringContainsString('at most 300 current HP', $spells[64]['effectDescription'][0]);
        foreach ($spells as $spell) {
            self::assertNotEmpty($spell['effectDescription']);
        }
        self::assertSame('RUSE', $spells[$rom->getWeapons()[32]['spell']]['name']);
        self::assertSame('SABR', $spells[$rom->getArmor()[38]['spell']]['name']);
        self::assertSame('FIGHTER', $rom->getClasses()[0]);
        foreach (array_merge($rom->getWeapons(), $rom->getArmor()) as $item) {
            if ($item['spell'] !== 0) {
                self::assertArrayHasKey($item['spell'], $spells);
            }
        }
    }
    
    public function testDomainDocumentMatchesSchema(): void {
        $rom = new GameData($this->localRom());
        $document = $rom->toDocument();
        self::assertTrue($document->schemaValidate(dirname(__DIR__, 2) . '/assets/schema/i/1.0.xsd'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('Falchon', $xpath->evaluate('string(/sf1:final-fantasy/sf1:weapons/sf1:weapon[@id="15"]/@name)'));
        self::assertSame(64.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:spells/sf1:spell)'));
    }
    
    public function testTrainerDoesNotShiftTables(): void {
        $contents = $this->localRom();
        $header = substr($contents, 0, 16);
        $header[6] = chr(ord($header[6]) | 4);
        $rom = new GameData($header . str_repeat("\0", 512) . substr($contents, 16));
        self::assertSame('Falchon', $rom->getWeapons()[15]['name']);
    }
    
    private function localRom(): string {
        $path = getenv('FINAL_FANTASY_I_ROM') ?: '';
        if (! is_file($path)) {
            self::markTestSkipped('Supply the original US ROM via FINAL_FANTASY_I_ROM for extraction integration tests.');
        }
        return (string) file_get_contents($path);
    }
}

