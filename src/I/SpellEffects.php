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
        $hit = (int) $spell->getAttribute('hit');
        $element = (int) $spell->getAttribute('element-mask');
        $targetId = (int) $spell->getAttribute('target');
        $target = match (true) {
            (bool) ($targetId & 1) => 'each enemy',
            (bool) ($targetId & 2) => 'one enemy',
            (bool) ($targetId & 4) => 'the caster',
            (bool) ($targetId & 8) => 'each ally',
            default => 'one ally'
        };
        $threshold = (int) $rules->getAttribute('base-hit') + $hit;
        $elementName = $element ? self::mask($element, self::ELEMENTS) : 'non-elemental';
        $chance = "Success roll at most $threshold minus Magic Defense.";
        $lines = match ($effect) {
            0 => [match ($id) {
                34 => 'Outside battle, revives one fallen ally with ' . $rules->getAttribute('life-hp') . ' HP.',
                39 => 'Outside battle, returns the party to the previous floor. On the first floor, leaves the dungeon.',
                41 => 'Outside battle, removes Stone from one ally.',
                42 => 'Outside battle, leaves the dungeon and returns the party to the overworld.',
                57 => 'Outside battle, revives one fallen ally with full HP.',
                default => 'No effect in battle.'
            }],
            1, 2 => [sprintf('%d-%d %s damage to %s. A successful roll doubles damage.', $power, $power * 2,
                $effect === 2 ? 'non-elemental' : $elementName, $target),
                "Double damage roll at most $threshold minus Magic Defense."],
            3 => ['Inflicts ' . self::mask($power, self::AILMENTS) . " on $target.", $chance],
            4 => ["Slows $target. Cancels FAST, otherwise limits attacks to one hit. Does not stack further.", $chance],
            5 => ["Lowers morale of $target by $power, to a minimum of 0.", $chance],
            6, 7 => [sprintf('Restores %d-%d HP to %s in battle.', $power, min(255, $power * 2), $target)],
            8 => ['Removes ' . self::mask($power, self::AILMENTS) . " from $target."],
            9 => ["Raises defense of $target by $power, up to 255. Always succeeds."],
            10 => ['Grants ' . self::mask($power, self::ELEMENTS) . " resistance to $target. Always succeeds."],
            11, 13 => ['No attack or accuracy increase on party members in this US version.'],
            12 => ["Speeds up $target. Cancels SLOW, otherwise doubles hit count. Does not stack further."],
            14 => ['Always misses in this US version.'],
            15 => ["Restores all HP and removes all ailments from $target in battle. Cannot target dead or petrified allies.",
                'Outside battle, restores all HP but leaves ailments unchanged, even Death and Stone.'],
            16 => ["Raises evasion of $target by $power, up to 255. Always succeeds."],
            17 => ['Does not remove enemy resistances in this US version.'],
            18 => ['Inflicts ' . self::mask($power, self::AILMENTS) . " on $target with at most " . $rules->getAttribute('hp-limit') . ' current HP, unless resistant. No random roll.'],
            default => ['Unknown spell effect.']
        };
        if ($effect === 2) {
            $lines[] = 'Only affects undead enemies.';
        }
        if (in_array($effect, [3, 4, 5, 18], true) && $element) {
            $lines[] = "$elementName element.";
        }
        foreach ($rules->getElementsByTagNameNS(GameData::XML_NAMESPACE, 'field-healing') as $healing) {
            if ((int) $healing->getAttribute('spell') === $id) {
                $minimum = (int) $healing->getAttribute('minimum');
                $maximum = $minimum + (int) $healing->getAttribute('spread');
                $lines[] = "Outside battle, restores $minimum-$maximum HP to $target.";
            }
        }
        if ($id === 25) {
            $lines[] = 'Also usable outside battle.';
        }
        return $lines;
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
