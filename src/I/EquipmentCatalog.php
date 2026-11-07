<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

/** ROM-derived equipment rules used when applying save edits. */
interface EquipmentCatalog {
    
    /** @return array<int, array<string, mixed>> Local IDs 1 through 40. */
    public function getWeapons(): array;
    
    /** @return array<int, array<string, mixed>> Local IDs 1 through 40. */
    public function getArmor(): array;
}
