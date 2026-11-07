<?php
declare(strict_types = 1);

require dirname(__DIR__) . '/vendor/autoload.php';

if ($path = getenv('FINAL_FANTASY_I_ROM')) {
    \Slothsoft\FinalFantasy\Configuration::setRomPath('i', $path);
}
