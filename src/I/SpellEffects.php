<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;
use DOMXPath;

/** Interprets the original US spell routines; all balance values come from the manifest. */
final class SpellEffects {
    private const AILMENTS = [1 => 'Death', 2 => 'Stone', 4 => 'Poison', 8 => 'Blindness', 16 => 'Paralysis', 32 => 'Sleep', 64 => 'Silence', 128 => 'Confusion'];
    private const ELEMENTS = [1 => 'Status', 2 => 'Poison, Stone', 4 => 'Time', 8 => 'Death', 16 => 'Fire', 32 => 'Ice', 64 => 'Lightning', 128 => 'Earth'];
    
    public static function appendTo(DOMDocument $document): void {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        $rules = $xpath->query('/sf1:final-fantasy/sf1:spell-rules')->item(0);
        if (! $rules instanceof DOMElement) {
            return;
        }
        foreach ($xpath->query('/sf1:final-fantasy/sf1:spells/sf1:spell') as $spell) {
            assert($spell instanceof DOMElement);
            $description = $document->createElementNS(GameData::XML_NAMESPACE, 'sf1:effect-description');
            foreach (self::describe($spell, $rules) as $text) {
                $line = $document->createElementNS(GameData::XML_NAMESPACE, 'sf1:line');
                $line->appendChild($document->createTextNode($text));
                $description->appendChild($line);
            }
            $spell->appendChild($description);
        }
    }
    
    /** @return list<string> */
    public static function describe(DOMElement $spell, DOMElement $rules): array {
        $id = (int) $spell->getAttribute('id');
        $effect = (int) $spell->getAttribute('effect');
        $power = (int) $spell->getAttribute('effectivity');
        $element = (int) $spell->getAttribute('element-mask');
        $targetId = (int) $spell->getAttribute('target');
        $target = match (true) {
            (bool) ($targetId & 1) => 'each enemy',
            (bool) ($targetId & 2) => 'one enemy',
            (bool) ($targetId & 4) => 'the caster',
            (bool) ($targetId & 8) => 'each ally',
            default => 'one ally'
        };
        $elementName = $element ? self::mask($element, self::ELEMENTS) : 'non-elemental';
        $lines = match ($effect) {
            0 => [match ($id) {
                34 => 'Outside battle, revives one fallen ally with ' . $rules->getAttribute('life-hp') . ' HP.',
                39 => 'Outside battle, returns the party to the previous floor. On the first floor, leaves the dungeon.',
                41 => 'Outside battle, removes Stone from one ally.',
                42 => 'Outside battle, leaves the dungeon and returns the party to the overworld.',
                57 => 'Outside battle, revives one fallen ally with full HP.',
                default => 'No effect in battle.'
            }],
            1, 2 => [sprintf('%d-%d HP %s damage to %s.', $power, $power * 2,
                $effect === 2 ? 'non-elemental' : $elementName, $target)],
            3 => ['Inflicts ' . self::mask($power, self::AILMENTS) . " on $target."],
            4 => ["Inflicts a one-hit limit on $target.", 'Cancels FAST.'],
            5 => ["Inflicts $power morale loss on $target."],
            6, 7 => [self::healing($id, $target, $rules)],
            8 => ['Removes ' . self::mask($power, self::AILMENTS) . " from $target."],
            9 => ["Grants $power defense to $target."],
            10 => ['Grants ' . self::mask($power, self::ELEMENTS) . " resistance to $target."],
            11, 13 => ['No effect.'],
            12 => ["Grants doubled hit count to $target.", 'Cancels SLOW.'],
            14 => ['Always misses.'],
            15 => ["Restores all HP to $target."],
            16 => ["Grants $power evasion to $target."],
            17 => ['No effect.'],
            18 => ['Inflicts ' . self::mask($power, self::AILMENTS) . " on $target with at most " . $rules->getAttribute('hp-limit') . ' current HP.'],
            default => ['Unknown spell effect.']
        };
        if ($effect === 2) {
            $lines[] = 'Only affects undead enemies.';
        }
        return $lines;
    }
    
    private static function healing(int $id, string $target, DOMElement $rules): string {
        foreach ($rules->getElementsByTagNameNS(GameData::XML_NAMESPACE, 'field-healing') as $healing) {
            if ((int) $healing->getAttribute('spell') === $id) {
                $minimum = (int) $healing->getAttribute('minimum');
                $maximum = $minimum + (int) $healing->getAttribute('spread');
                return "Restores $minimum-$maximum HP to $target.";
            }
        }
        return "Restores HP to $target.";
    }
    
    /** @param array<int, string> $names */
    private static function mask(int $mask, array $names): string {
        $result = [];
        foreach ($names as $bit => $name) {
            if ($mask & $bit) {
                $result[] = $name;
            }
        }
        return implode(', ', $result);
    }
}
