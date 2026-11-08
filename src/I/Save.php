<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use Exception;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Slothsoft\Savegame\Editor;
use Slothsoft\Savegame\EditorConfig;
use Slothsoft\Savegame\Node\AbstractValueContent;
use Slothsoft\Savegame\Node\ArchiveParser\CopyArchiveBuilder;
use Slothsoft\Savegame\Node\ArchiveParser\CopyArchiveExtractor;
use Slothsoft\Savegame\Node\FileContainer;
use SplFileInfo;

/** Equipment editing for the original US game's battery SRAM format. */
final class Save {
    
    private string $contents;
    
    private ?FileContainer $file = null;
    
    public function __construct(string $contents) {
        if (strlen($contents) !== 8192) {
            throw new InvalidArgumentException('Choose an 8192-byte NES battery save (.sav), not an emulator save state.');
        }
        if (substr($contents, 0x4FE, 2) !== "\x55\xAA" || self::accumulateChecksum($contents) !== 255) {
            throw new InvalidArgumentException('The save has an invalid FF1 signature or checksum.');
        }
        $this->contents = $contents;
    }
    
    public static function newGame(GameData $rom): string {
        $state = $rom->getNewGameState();
        $state[0xFE] = "\x55";
        $state[0xFF] = "\xAA";
        return self::withChecksum($state . $state . str_repeat("\0", 6144));
    }
    
    /** @return array<int, array{index:int,name:string,class:int,weapons:array<int,array{id:int,equipped:bool}>,armor:array<int,array{id:int,equipped:bool}>}> */
    public function getCharacters(): array {
        $characters = [];
        for ($character = 0; $character < 4; $character++) {
            $base = 0x500 + 0x40 * $character;
            $record = [
                'index' => $character,
                'name' => TextCodec::decode(substr($this->contents, $base + 2, 4)),
                'class' => ord($this->contents[$base]),
                'weapons' => [],
                'armor' => []
            ];
            foreach (['weapons' => 0x18, 'armor' => 0x1C] as $kind => $relative) {
                for ($slot = 0; $slot < 4; $slot++) {
                    $offset = $base + $relative + $slot;
                    $record[$kind][$slot] = [
                        'id' => (int) $this->node($offset, 'item')->getValue(),
                        'equipped' => (bool) $this->node($offset, 'equipped')->getValue()
                    ];
                }
            }
            $characters[$character] = $record;
        }
        return $characters;
    }
    
    /**
     * Partial updates: character index => weapons/armor => slot index => id/equipped.
     *
     * The nested input is validated here; malformed keys and values are rejected.
     *
     * @param array<array-key, mixed> $updates
     */
    public function withEquipment(array $updates, EquipmentCatalog $rom): string {
        $original = $this->getCharacters();
        $next = $original;
        $catalogs = ['weapons' => $rom->getWeapons(), 'armor' => $rom->getArmor()];
        foreach ($updates as $character => $groups) {
            if (! is_int($character) || ! isset($next[$character]) || ! is_array($groups)) {
                throw new InvalidArgumentException('Unknown party member.');
            }
            foreach ($groups as $kind => $slots) {
                if (($kind !== 'weapons' && $kind !== 'armor') || ! is_array($slots)) {
                    throw new InvalidArgumentException('Only weapons and armor can be edited.');
                }
                foreach ($slots as $slot => $values) {
                    if (! is_int($slot) || $slot < 0 || $slot > 3 || ! is_array($values) || array_diff(array_keys($values), ['id', 'equipped'])) {
                        throw new InvalidArgumentException('Unknown equipment slot or field.');
                    }
                    if (array_key_exists('id', $values) && (! is_int($values['id']) || $values['id'] < 0 || $values['id'] > 40)) {
                        throw new InvalidArgumentException('Equipment IDs must be between 0 and 40.');
                    }
                    if (array_key_exists('equipped', $values) && ! is_bool($values['equipped'])) {
                        throw new InvalidArgumentException('The equipped flag must be true or false.');
                    }
                    $previous = $next[$character][$kind][$slot];
                    $next[$character][$kind][$slot] = [
                        'id' => $values['id'] ?? $previous['id'],
                        'equipped' => $values['equipped'] ?? $previous['equipped']
                    ];
                }
            }
        }
        foreach ($next as $character => $record) {
            foreach ($catalogs as $kind => $catalog) {
                foreach ($record[$kind] as $slot => $item) {
                    if ($item === $original[$character][$kind][$slot]) {
                        continue;
                    }
                    if ($item['id'] !== 0 && ! isset($catalog[$item['id']])) {
                        throw new InvalidArgumentException('Unknown equipment item.');
                    }
                    if (! $item['equipped']) {
                        continue;
                    }
                    if ($item['id'] === 0 || ! in_array($record['class'], $catalog[$item['id']]['classes'], true)) {
                        throw new InvalidArgumentException('This character cannot equip the selected item.');
                    }
                    foreach ($record[$kind] as $otherSlot => $other) {
                        if ($otherSlot === $slot || ! $other['equipped']) {
                            continue;
                        }
                        if ($kind === 'weapons' || ! isset($catalog[$other['id']]) || $catalog[$item['id']]['category'] === $catalog[$other['id']]['category']) {
                            throw new InvalidArgumentException('Equip only one weapon and one piece of armor of each category.');
                        }
                    }
                }
            }
        }
        if ($next === $original) {
            return $this->contents;
        }
        
        // A fresh instance makes this operation immutable, including after a failed validation.
        $result = new self($this->contents);
        foreach ($next as $character => $record) {
            $base = 0x500 + 0x40 * $character;
            foreach (['weapons' => 0x18, 'armor' => 0x1C] as $kind => $relative) {
                foreach ($record[$kind] as $slot => $item) {
                    $offset = $base + $relative + $slot;
                    $result->node($offset, 'item')->setValue($item['id'], true);
                    $result->node($offset, 'equipped')->setValue($item['equipped'], true);
                }
                $oldEquipped = self::equippedIds($original[$character][$kind]);
                $newEquipped = self::equippedIds($record[$kind]);
                if ($oldEquipped !== $newEquipped) {
                    foreach (array_merge($oldEquipped, $newEquipped) as $id) {
                        if (! isset($catalogs[$kind][$id])) {
                            throw new InvalidArgumentException('Cannot adjust stats for unknown equipped items in this save.');
                        }
                    }
                    $result->adjustStats($base, $record['class'], $kind, $oldEquipped, $newEquipped, $catalogs[$kind]);
                }
            }
        }
        return self::withChecksum($result->loadFile()->getContent());
    }
    
    /**
     * @param array<int, array{id:int,equipped:bool}> $slots
     * @return list<int>
     */
    private static function equippedIds(array $slots): array {
        $ids = [];
        foreach ($slots as $item) {
            if ($item['equipped']) {
                $ids[] = $item['id'];
            }
        }
        sort($ids);
        return $ids;
    }
    
    /**
     * Reproduce UnadjustEquipStats/ReadjustEquipStats (bank 0F), including byte overflow.
     *
     * @param list<int> $before
     * @param list<int> $after
     * @param array<int, array<string, mixed>> $catalog
     */
    private function adjustStats(int $base, int $class, string $kind, array $before, array $after, array $catalog): void {
        $monk = $class === 2 || $class === 8;
        if ($kind === 'weapons') {
            $damage = ord($this->contents[$base + 0x20]);
            $hit = ord($this->contents[$base + 0x21]);
            foreach ($before as $id) {
                $damage -= $catalog[$id]['damage'];
                $hit -= $catalog[$id]['hit'];
            }
            if ($monk) {
                $damage = 0;
            }
            foreach ($after as $id) {
                $damage += $catalog[$id]['damage'];
                $hit += $catalog[$id]['hit'];
            }
            $damage &= 255;
            if ($monk) {
                // LSR leaves the strength low bit in carry, so the ADC rounds upward.
                $damage = $damage ? $damage + intdiv(ord($this->contents[$base + 0x10]) + 1, 2) : (ord($this->contents[$base + 0x26]) + 1) * 2;
            }
            $this->node($base + 0x20, 'damage')->setValue($damage & 255, true);
            $this->node($base + 0x21, 'hit')->setValue($hit & 255, true);
        } else {
            $evade = ord($this->contents[$base + 0x23]);
            $absorb = 0;
            $resistance = 0;
            foreach ($before as $id) {
                $evade += $catalog[$id]['evadePenalty'];
            }
            foreach ($after as $id) {
                $evade -= $catalog[$id]['evadePenalty'];
                $absorb += $catalog[$id]['absorb'];
                $resistance |= $catalog[$id]['resistanceMask'];
            }
            $absorb &= 255;
            if ($monk && $absorb === 0) {
                $absorb = ord($this->contents[$base + 0x26]) + 1;
            }
            $this->node($base + 0x22, 'absorb')->setValue($absorb & 255, true);
            $this->node($base + 0x23, 'evade')->setValue($evade & 255, true);
            $this->node($base + 0x24, 'resistance')->setValue($resistance, true);
        }
    }
    
    /** The game interleaves the four pages and retains carry after every ADC. */
    private static function accumulateChecksum(string $contents): int {
        $accumulator = 0;
        $carry = 0;
        for ($offset = 0; $offset < 256; $offset++) {
            for ($page = 4; $page < 8; $page++) {
                $sum = $accumulator + ord($contents[$page * 256 + $offset]) + $carry;
                $accumulator = $sum & 255;
                $carry = $sum >> 8;
            }
        }
        return $accumulator;
    }
    
    private static function withChecksum(string $contents): string {
        $contents[0x4FD] = "\0";
        $contents[0x4FD] = chr(self::accumulateChecksum($contents) ^ 255);
        if (self::accumulateChecksum($contents) !== 255) {
            // The original save routine assumes its final ADC cannot overflow. Preserve
            // arbitrary trailing bytes too, choosing a byte accepted by VerifyChecksum.
            for ($candidate = 0; $candidate < 256; $candidate++) {
                $contents[0x4FD] = chr($candidate);
                if (self::accumulateChecksum($contents) === 255) {
                    return $contents;
                }
            }
            throw new InvalidArgumentException('The edited save cannot satisfy the game checksum.');
        }
        return $contents;
    }
    
    private function node(int $offset, string $name): AbstractValueContent {
        foreach ($this->loadFile()->getValueList() as $node) {
            if ($node->getContentOffset() === $offset && $node->getName() === $name) {
                return $node;
            }
        }
        throw new LogicException('The FF1 equipment structure is incomplete.');
    }
    
    private function loadFile(): FileContainer {
        if ($this->file === null) {
            try {
                $directory = temp_dir('final-fantasy');
            } catch (Exception $exception) {
                throw new RuntimeException('Could not prepare the save editor.', 0, $exception);
            }
            file_put_contents($directory . '/party.sav', $this->contents);
            $config = new EditorConfig(
                new SplFileInfo($directory),
                new SplFileInfo($directory),
                new SplFileInfo($directory),
                new SplFileInfo(dirname(__DIR__, 2) . '/assets/i/savegame/equipment.xml'),
                ['raw' => new CopyArchiveExtractor()],
                ['raw' => new CopyArchiveBuilder()]
            );
            $editor = new Editor($config);
            $archive = $editor->loadArchive('party.sav', true);
            $this->file = $archive->getFileNodeByName('1');
        }
        return $this->file;
    }
}
