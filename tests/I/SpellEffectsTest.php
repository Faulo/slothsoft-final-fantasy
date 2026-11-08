<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;
use PHPUnit\Framework\TestCase;

/** @covers \Slothsoft\FinalFantasy\I\SpellEffects */
final class SpellEffectsTest extends TestCase {
    /** @dataProvider effects */
    public function testImplementedEffects(int $effect, int $power, int $element, int $target, string $expected): void {
        $spell = $this->element(['id' => 5, 'effect' => $effect, 'effectivity' => $power, 'element-mask' => $element, 'target' => $target, 'hit' => 24]);
        $rules = $this->element(['base-hit' => 148, 'hp-limit' => 300]);
        self::assertStringContainsString($expected, implode(' ', SpellEffects::describe($spell, $rules)));
        self::assertStringNotContainsString(' element.', implode(' ', SpellEffects::describe($spell, $rules)));
        self::assertStringNotContainsString('Cancels ', implode(' ', SpellEffects::describe($spell, $rules)));
    }
    
    /** @return list<array{int, int, int, int, string}> */
    public function effects(): array {
        return [
            [1, 10, 16, 2, 'Deals 10-20 Fire damage to one enemy.'],
            [2, 40, 0, 1, 'Only affects undead enemies.'],
            [3, 2, 2, 2, 'Inflicts Stone on one enemy.'],
            [3, 32, 1, 1, 'Inflicts Sleep on each enemy.'],
            [5, 40, 1, 1, 'Inflicts 40 morale loss on each enemy.'],
            [7, 140, 0, 8, 'Restores HP to each ally'],
            [8, 64, 0, 16, 'Removes Silence from one ally.'],
            [9, 12, 0, 8, 'Grants 12 defense to each ally'],
            [10, 137, 0, 8, 'Grants Status, Death, Earth resistance'],
            [12, 0, 0, 16, 'Grants doubled hit count to one ally.'],
            [13, 16, 0, 4, 'No effect.'],
            [14, 20, 0, 2, 'Always misses'],
            [15, 0, 0, 16, 'Restores all HP to one ally.'],
            [16, 20, 0, 1, 'Grants 20 evasion to each enemy'],
            [17, 0, 0, 2, 'No effect.'],
            [18, 1, 8, 2, 'at most 300 current HP.']
        ];
    }
    
    public function testUtilityDescriptions(): void {
        $rules = $this->element(['life-hp' => 1]);
        foreach ([
                     34 => 'Outside battle, revives one fallen ally with 1 HP.',
                     39 => 'Returns the party to the previous floor.',
                     41 => 'Outside battle, removes Stone from one ally.',
                     42 => 'Returns the party to the overworld.',
                     57 => 'Outside battle, revives one fallen ally with full HP.'
                 ] as $id => $expected) {
            $spell = $this->element(['id' => $id, 'effect' => 0]);
            self::assertSame([$expected], SpellEffects::describe($spell, $rules));
        }
    }
    
    public function testChangingSourceValuesChangesDescription(): void {
        $spell = $this->element(['id' => 17, 'effect' => 7, 'effectivity' => 37, 'element-mask' => 0, 'target' => 16, 'hit' => 0]);
        $rules = $this->element(['base-hit' => 159, 'hp-limit' => 400]);
        self::assertNotNull($rules->ownerDocument);
        $field = $rules->ownerDocument->createElementNS(GameData::XML_NAMESPACE, 'sf1:field-healing');
        foreach (['spell' => 17, 'minimum' => 45, 'spread' => 7] as $name => $value) {
            $field->setAttribute($name, (string) $value);
        }
        $rules->appendChild($field);
        self::assertSame(['Restores 45-52 HP to one ally.'], SpellEffects::describe($spell, $rules));
        $spell->setAttribute('effect', '1');
        $spell->setAttribute('hit', '31');
        self::assertStringContainsString('Deals 37-74 non-elemental damage', implode(' ', SpellEffects::describe($spell, $rules)));
        $spell->setAttribute('effect', '18');
        self::assertStringContainsString('at most 400 current HP', implode(' ', SpellEffects::describe($spell, $rules)));
    }
    
    /** @param array<string, int> $attributes */
    private function element(array $attributes): DOMElement {
        $document = new DOMDocument();
        $element = $document->createElement('record');
        $document->appendChild($element);
        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, (string) $value);
        }
        return $element;
    }
}
