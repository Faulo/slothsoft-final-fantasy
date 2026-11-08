<?php
declare(strict_types = 1);

require dirname(__DIR__) . '/vendor/autoload.php';

$path = getenv('FINAL_FANTASY_I_ROM') ?: dirname(__DIR__) . '/test-files/ff1-us.zip';
if (! is_file($path) || ! is_readable($path)) {
    throw new RuntimeException('The FF I test ROM is missing or unreadable. Restore test-files/ff1-us.zip or set FINAL_FANTASY_I_ROM.');
}
\Slothsoft\FinalFantasy\Configuration::setRomPath('i', $path);
