<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use PHPUnit\Framework\Assert;

/** Binary test readers must fail at decoding, not later at an array access. */
trait UnpackHelper {
    /** @return array<int|string, int> */
    private static function unpack(string $format, string $data): array {
        $values = unpack($format, $data);
        Assert::assertIsArray($values, 'Could not decode binary test data: ' . $format);
        $integers = [];
        foreach ($values as $key => $value) {
            Assert::assertIsInt($value, 'Expected an integer binary field: ' . $key);
            $integers[$key] = $value;
        }
        return $integers;
    }
}
