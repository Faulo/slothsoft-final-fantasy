<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use Slothsoft\Core\IO\Sanitizer\StringSanitizer;
use Slothsoft\Farah\Module\Asset\ParameterFilterStrategy\AbstractMapParameterFilter;

final class PageParameters extends AbstractMapParameterFilter {
    /** @return array<string, StringSanitizer> */
    protected function createValueSanitizers(): array {
        return ['view' => new StringSanitizer('home')];
    }
}
