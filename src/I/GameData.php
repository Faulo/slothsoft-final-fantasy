<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Slothsoft\Savegame\Build\XmlBuilder;
use Slothsoft\Savegame\Editor;
use Slothsoft\Savegame\EditorConfig;
use Slothsoft\Savegame\Node\ArchiveParser\CopyArchiveBuilder;
use SplFileInfo;
use XSLTProcessor;

/** Reads game data through the savegame manifest, then transforms it into FF I domain XML. */
final class GameData implements EquipmentCatalog {
    public const XML_NAMESPACE = 'http://schema.slothsoft.net/final-fantasy/i';
    public const US_PRG_SHA256 = NesArchiveExtractor::US_PRG_SHA256;
    
    private DOMDocument $dataset;
    
    private DOMDocument $document;
    
    /** @throws Exception */
    public function __construct(string $contents) {
        $directory = temp_dir('final-fantasy-i-data');
        if (file_put_contents($directory . '/game.nes', $contents) !== strlen($contents)) {
            throw new RuntimeException('Could not prepare the game data source.');
        }
        $config = new EditorConfig(
            new SplFileInfo($directory),
            new SplFileInfo($directory),
            new SplFileInfo($directory),
            new SplFileInfo(dirname(__DIR__, 2) . '/assets/i/savegame/rom.xml'),
            ['NES' => new NesArchiveExtractor()],
            ['NES' => new CopyArchiveBuilder()]
        );
        $editor = new Editor($config);
        $savegame = $editor->loadSavegame(true, true);
        $builder = new XmlBuilder($savegame);
        $builder->setCacheDirectory($directory . '/xml');
        $this->dataset = new DOMDocument('1.0', 'UTF-8');
        if (! $this->dataset->loadXML(implode('', iterator_to_array($builder->toChunks())))) {
            throw new RuntimeException('Could not serialize the parsed game data.');
        }
        // FF's character encoding is domain-specific. The manifest has already read
        // and bounded each name using its pointer and terminator; only decode glyphs here.
        $input = clone $this->dataset;
        $xpath = new DOMXPath($input);
        $xpath->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        foreach ($xpath->query('//sse:string[@name="text"]') as $node) {
            assert($node instanceof DOMElement);
            $bytes = mb_convert_encoding($node->getAttribute('value'), 'ISO-8859-1', 'UTF-8');
            $node->setAttribute('value', TextCodec::decode($bytes));
            $node->appendChild(TextCodec::createLabel($input, $bytes));
        }
        $stylesheet = new DOMDocument();
        if (! $stylesheet->load(dirname(__DIR__, 2) . '/assets/i/xsl/data.xsl')) {
            throw new RuntimeException('Could not load the game data transformation.');
        }
        $processor = new XSLTProcessor();
        $processor->importStylesheet($stylesheet);
        $processor->setParameter('', 'rom-sha256', self::US_PRG_SHA256);
        $result = $processor->transformToDoc($input);
        if (! $result instanceof DOMDocument) {
            throw new RuntimeException('Could not transform the game data.');
        }
        $this->document = $result;
        SpellEffects::appendTo($this->document);
    }
    
    /** @throws Exception */
    public static function fromFile(string $path): self {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('The Final Fantasy US ROM is not configured or readable.');
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not read the Final Fantasy US ROM.');
        }
        return new self($contents);
    }
    
    public function getDatasetDocument(): DOMDocument {
        return clone $this->dataset;
    }
    
    public function toDocument(): DOMDocument {
        return clone $this->document;
    }
    
    public function getClassSprite(int $class): string {
        if ($class < 0 || $class > 11) {
            throw new InvalidArgumentException('Choose a class ID from 0 to 11.');
        }
        $tiles = $this->readGraphics('//sse:instruction[@name="class-graphics"]/sse:group[' . ($class + 1) . ']/sse:image');
        $assignments = $this->readGraphics('//sse:binary[@name="class-palettes"]');
        $palettes = $this->readGraphics('//sse:binary[@name="sprite-palettes"]');
        $poses = $this->readGraphics('//sse:binary[@name="class-poses"]');
        return NesSpriteSheet::toPng($tiles, substr($palettes, (ord($assignments[$class]) & 3) * 4, 4), $poses);
    }
    
    /** New-game SRAM blocks, read exclusively through the ROM manifest. */
    public function getNewGameState(): string {
        $state = $this->readGraphics('//sse:binary[@name="initial-state"]') . str_repeat("\0", 256)
            . $this->readGraphics('//sse:binary[@name="initial-flags"]') . str_repeat("\0", 256);
        $stats = $this->readGraphics('//sse:binary[@name="starting-stats"]');
        $party = $this->readGraphics('//sse:binary[@name="starting-party"]');
        $state[0x14] = "\x01"; // On foot, as in GameStart.
        for ($i = 0; $i < 4; $i++) {
            $class = ord($party[$i * 16]);
            $base = 0x100 + $i * 64;
            $state[$base] = chr($class);
            $state = substr_replace($state, substr($party, $i * 16 + 2, 4), $base + 2, 4);
            $state[$base + 0x16] = chr(40);
            foreach ([1 => 0x0A, 2 => 0x10, 3 => 0x11, 4 => 0x12, 5 => 0x13, 6 => 0x14, 7 => 0x20, 8 => 0x21, 9 => 0x23, 10 => 0x25] as $source => $target) {
                $state[$base + $target] = $stats[$class * 16 + $source];
            }
            $state[$base + 0x0C] = $state[$base + 0x0A];
            if ($class >= 3 && $class < 6) {
                $state[0x320 + $i * 64] = "\x02";
                $state[0x328 + $i * 64] = "\x02";
            }
        }
        return $state;
    }
    
    public function getMenuFont(): string {
        return MenuFont::toTrueType($this->readGraphics('//sse:image[@name="menu-font"]'));
    }
    
    public function getItemIcons(): string {
        return NesSpriteSheet::toTileStrip(
            $this->readGraphics('//sse:image[@name="item-icons"]'),
            $this->readGraphics('//sse:binary[@name="menu-palette"]'),
            2
        );
    }
    
    private function readGraphics(string $query): string {
        $xpath = new DOMXPath($this->dataset);
        $xpath->registerNamespace('sse', 'http://schema.slothsoft.net/savegame/editor');
        $hex = (string) $xpath->evaluate('string(' . $query . '/@value)');
        $value = hex2bin(str_replace(' ', '', trim($hex)));
        if ($value === false || $value === '') {
            throw new RuntimeException('The graphics dataset is incomplete.');
        }
        return $value;
    }
    
    /** @return array<int, string> */
    public function getClasses(): array {
        $classes = [];
        foreach ($this->records('classes') as $id => $record) {
            $classes[$id] = (string) $record['name'];
        }
        return $classes;
    }
    
    /** @return array<int, array<string, mixed>> */
    public function getWeapons(): array {
        return $this->records('weapons');
    }
    
    /** @return array<int, array<string, mixed>> */
    public function getArmor(): array {
        return $this->records('armor');
    }
    
    /** @return array<int, array<string, mixed>> */
    public function getSpells(): array {
        return $this->records('spells');
    }
    
    /** @return array<int, array<string, mixed>> */
    private function records(string $group): array {
        $xpath = new DOMXPath($this->document);
        $xpath->registerNamespace('sf1', self::XML_NAMESPACE);
        $records = [];
        foreach ($xpath->query('/sf1:final-fantasy/sf1:' . $group . '/*') as $node) {
            assert($node instanceof DOMElement);
            $record = [];
            foreach ($node->attributes as $attribute) {
                $key = preg_replace_callback('/-([a-z])/', static fn(array $match): string => strtoupper($match[1]), $attribute->name);
                $record[$key] = in_array($key, ['name', 'school'], true) ? $attribute->value : (int) $attribute->value;
            }
            $record['classes'] = [];
            if ($group === 'spells') {
                $record['effectDescription'] = [];
                foreach ($xpath->query('sf1:effect-description/sf1:line', $node) as $line) {
                    $record['effectDescription'][] = $line->textContent;
                }
            }
            foreach ($xpath->query('sf1:class-ref', $node) as $class) {
                assert($class instanceof DOMElement);
                $record['classes'][] = (int) $class->getAttribute('id');
            }
            $records[(int) $node->getAttribute('id')] = $record;
        }
        return $records;
    }
}
