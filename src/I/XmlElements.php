<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/** Checked element-only queries for the game-data XML contract. */
final class XmlElements {
    /** @return list<DOMElement> */
    public static function query(DOMXPath $xpath, string $expression, ?DOMNode $context = null): array {
        $nodes = $xpath->query($expression, $context);
        if ($nodes === false) {
            throw new RuntimeException('Invalid game-data XPath: ' . $expression);
        }
        $elements = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMElement) {
                throw new RuntimeException('Expected an element in game-data XPath: ' . $expression);
            }
            $elements[] = $node;
        }
        return $elements;
    }
}
