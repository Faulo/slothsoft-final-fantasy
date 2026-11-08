<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** @runTestsInSeparateProcesses */
final class SaveTest extends TestCase {
    use UnpackHelper;
    
    private function fixture(): string {
        $bytes = str_repeat("\x39", 8192);
        for ($i = 0; $i < 4; $i++) {
            $base = 0x500 + 64 * $i;
            $bytes[$base] = "\0";
            $bytes = substr_replace($bytes, "\x8A\xA5\xA6\xFF", $base + 2, 4);
            $bytes = substr_replace($bytes, str_repeat("\0", 8), $base + 0x18, 8);
            $bytes[$base + 0x20] = chr(21);
            $bytes[$base + 0x21] = chr(39);
            $bytes[$base + 0x22] = chr(0);
            $bytes[$base + 0x23] = chr(47);
            $bytes[$base + 0x24] = chr(0);
        }
        $bytes[0x4FE] = "\x55";
        $bytes[0x4FF] = "\xAA";
        return $this->checksum($bytes);
    }
    
    /** Independent modulo-255 oracle, sufficient for these generated records. */
    private function checksum(string $bytes): string {
        $bytes[0x4FD] = "\0";
        $sum = array_sum(self::unpack('C*', substr($bytes, 0x400, 0x400)));
        $bytes[0x4FD] = chr(($sum % 255) ^ 255);
        return $bytes;
    }
    
    private function catalog(): EquipmentCatalog {
        return new class implements EquipmentCatalog {
            
            public function getWeapons(): array {
                return [
                    1 => ['classes' => [0, 2, 8], 'damage' => 7, 'hit' => 5],
                    2 => ['classes' => [0, 2, 8], 'damage' => 13, 'hit' => 10],
                    3 => ['classes' => [5], 'damage' => 2, 'hit' => 1]
                ];
            }
            
            public function getArmor(): array {
                return [
                    1 => ['classes' => [0, 2, 8], 'category' => 0, 'evadePenalty' => 3, 'absorb' => 7, 'resistanceMask' => 16],
                    2 => ['classes' => [0, 2, 8], 'category' => 0, 'evadePenalty' => 8, 'absorb' => 19, 'resistanceMask' => 32],
                    3 => ['classes' => [0, 2, 8], 'category' => 1, 'evadePenalty' => 1, 'absorb' => 4, 'resistanceMask' => 8]
                ];
            }
        };
    }
    
    public function testReadsMaskedEquipmentAndPreservesWholeUneditedSave(): void {
        $bytes = $this->fixture();
        $bytes[0x518] = chr(129);
        $bytes[0x519] = chr(2);
        $bytes = $this->checksum($bytes);
        $save = new Save($bytes);
        $party = $save->getCharacters();
        self::assertCount(4, $party);
        self::assertSame('Abc', $party[0]['name']);
        self::assertSame(['id' => 1, 'equipped' => true], $party[0]['weapons'][0]);
        self::assertSame(['id' => 2, 'equipped' => false], $party[0]['weapons'][1]);
        self::assertSame($bytes, $save->withEquipment([], $this->catalog()));
        self::assertSame($bytes, $save->withEquipment([0 => ['weapons' => $party[0]['weapons'], 'armor' => $party[0]['armor']]], $this->catalog()));
    }
    
    public function testInventoryChangesOnlyTouchSlotAndChecksum(): void {
        $bytes = $this->fixture();
        $edited = (new Save($bytes))->withEquipment([3 => ['weapons' => [2 => ['id' => 2]]]], $this->catalog());
        $expected = $bytes;
        $expected[0x5DA] = chr(2);
        self::assertSame($this->checksum($expected), $edited);
        self::assertSame(2, (new Save($edited))->getCharacters()[3]['weapons'][2]['id']);
    }
    
    public function testReplacingEquippedWeaponPreservesFlagAndAdjustsBonuses(): void {
        $bytes = $this->fixture();
        $bytes[0x518] = chr(129);
        $bytes = $this->checksum($bytes);
        $save = new Save($bytes);
        $edited = $save->withEquipment([0 => ['weapons' => [0 => ['id' => 2]]]], $this->catalog());
        $expected = $bytes;
        $expected[0x518] = chr(130);
        $expected[0x520] = chr(27);
        $expected[0x521] = chr(44);
        self::assertSame($this->checksum($expected), $edited);
        self::assertSame(1, $save->getCharacters()[0]['weapons'][0]['id'], 'Editing must not mutate the input object.');
        new Save($edited);
    }
    
    public function testArmorUpdatesCachedAbsorbEvadeAndResistance(): void {
        $bytes = $this->fixture();
        $bytes[0x51C] = chr(129);
        $bytes[0x51D] = chr(131);
        $bytes[0x522] = chr(11);
        $bytes[0x524] = chr(24);
        $bytes = $this->checksum($bytes);
        $edited = (new Save($bytes))->withEquipment([0 => ['armor' => [0 => ['id' => 2]]]], $this->catalog());
        $expected = $bytes;
        $expected[0x51C] = chr(130);
        $expected[0x522] = chr(23);
        $expected[0x523] = chr(42);
        $expected[0x524] = chr(40);
        self::assertSame($this->checksum($expected), $edited);
    }
    
    public function testMovingEquippedItemDoesNotRecalculateExistingStats(): void {
        $bytes = $this->fixture();
        $bytes[0x518] = chr(129);
        $bytes = $this->checksum($bytes);
        $edited = (new Save($bytes))->withEquipment([0 => ['weapons' => [0 => ['id' => 0, 'equipped' => false], 3 => ['id' => 1, 'equipped' => true]]]], $this->catalog());
        $expected = $bytes;
        $expected[0x518] = chr(0);
        $expected[0x51B] = chr(129);
        self::assertSame($this->checksum($expected), $edited);
    }
    
    public function testMonkEquipmentRulesIncludingOddStrengthCarry(): void {
        $bytes = $this->fixture();
        $bytes[0x500] = chr(2);
        $bytes[0x510] = chr(21);
        $bytes[0x526] = chr(8);
        $bytes = $this->checksum($bytes);
        $edited = (new Save($bytes))->withEquipment([0 => ['weapons' => [0 => ['id' => 1, 'equipped' => true]]]], $this->catalog());
        self::assertSame(18, ord($edited[0x520]));
        $unequipped = (new Save($edited))->withEquipment([0 => ['weapons' => [0 => ['equipped' => false]]]], $this->catalog());
        self::assertSame(18, ord($unequipped[0x520]));
        $armor = (new Save($bytes))->withEquipment([0 => ['armor' => [0 => ['id' => 1, 'equipped' => true]]]], $this->catalog());
        $unarmored = (new Save($armor))->withEquipment([0 => ['armor' => [0 => ['equipped' => false]]]], $this->catalog());
        self::assertSame(9, ord($unarmored[0x522]));
    }
    
    public function testDerivedStatsFollowNesByteOverflow(): void {
        $bytes = $this->fixture();
        $bytes[0x518] = chr(129);
        $bytes[0x520] = chr(253);
        $bytes[0x521] = chr(254);
        $bytes[0x523] = chr(2);
        $bytes = $this->checksum($bytes);
        $edited = (new Save($bytes))->withEquipment([0 => [
            'weapons' => [0 => ['id' => 2]],
            'armor' => [0 => ['id' => 2, 'equipped' => true]]
        ]], $this->catalog());
        self::assertSame(3, ord($edited[0x520]));
        self::assertSame(3, ord($edited[0x521]));
        self::assertSame(250, ord($edited[0x523]));
        new Save($edited);
    }
    
    public function testUnchangedInvalidEquipmentCanBePreserved(): void {
        $bytes = $this->fixture();
        $bytes[0x518] = chr(255);
        $bytes = $this->checksum($bytes);
        self::assertSame($bytes, (new Save($bytes))->withEquipment([], $this->catalog()));
        $edited = (new Save($bytes))->withEquipment([1 => ['weapons' => [1 => ['id' => 1]]]], $this->catalog());
        self::assertSame(255, ord($edited[0x518]));
    }
    
    /**
     * @dataProvider invalidUpdates
     * @param array<array-key, mixed> $updates
     */
    public function testRejectsInvalidEquipmentEdits(array $updates): void {
        $this->expectException(InvalidArgumentException::class);
        (new Save($this->fixture()))->withEquipment($updates, $this->catalog());
    }
    
    /** @return array<string, array{array<array-key, mixed>}> */
    public function invalidUpdates(): array {
        return [
            'non-integer member' => [['hero' => ['weapons' => []]]],
            'non-array groups' => [[0 => null]],
            'non-array slots' => [[0 => ['weapons' => false]]],
            'non-integer slot' => [[0 => ['weapons' => ['left' => ['id' => 1]]]]],
            'non-array slot values' => [[0 => ['weapons' => [0 => null]]]],
            'unknown member' => [[4 => ['weapons' => []]]],
            'unknown category' => [[0 => ['spells' => []]]],
            'unknown slot' => [[0 => ['weapons' => [4 => ['id' => 1]]]]],
            'unknown field' => [[0 => ['weapons' => [0 => ['damage' => 255]]]]],
            'out of range' => [[0 => ['weapons' => [0 => ['id' => 41]]]]],
            'negative' => [[0 => ['weapons' => [0 => ['id' => -1]]]]],
            'untyped id' => [[0 => ['weapons' => [0 => ['id' => '1']]]]],
            'untyped equipped' => [[0 => ['weapons' => [0 => ['equipped' => 'true']]]]],
            'empty equipped' => [[0 => ['weapons' => [0 => ['equipped' => true]]]]],
            'class restriction' => [[0 => ['weapons' => [0 => ['id' => 3, 'equipped' => true]]]]],
            'two weapons' => [[0 => ['weapons' => [0 => ['id' => 1, 'equipped' => true], 1 => ['id' => 2, 'equipped' => true]]]]],
            'two body armors' => [[0 => ['armor' => [0 => ['id' => 1, 'equipped' => true], 1 => ['id' => 2, 'equipped' => true]]]]]
        ];
    }
    
    /** @dataProvider invalidSaves */
    public function testRejectsInvalidSaves(string $kind): void {
        $bytes = $this->fixture();
        if ($kind === 'length') {
            $bytes = substr($bytes, 0, 2048);
        } elseif ($kind === 'signature') {
            $bytes[0x4FE] = "\0";
            $bytes = $this->checksum($bytes);
        } else {
            $bytes[0x41C] = chr(ord($bytes[0x41C]) ^ 1);
        }
        $this->expectException(InvalidArgumentException::class);
        new Save($bytes);
    }
    
    /** @return list<array{string}> */
    public function invalidSaves(): array {
        return [['length'], ['signature'], ['checksum']];
    }
}
