<?php
declare(strict_types = 1);

use PHPStan\DependencyInjection\ContainerFactory;
use PHPStan\DependencyInjection\NeonAdapter;

// Replace the bundled DOM stub to avoid duplicate declarations during stub validation.
// Read the default list so other bundled stubs remain aligned with the installed PHPStan.
$phpstanRoot = dirname((new ReflectionClass(ContainerFactory::class))->getFileName(), 3);
$adapter = new NeonAdapter([]);
$defaults = $adapter->load($phpstanRoot . '/conf/config.neon');
$stubFiles = [];
foreach ($defaults['parameters']['stubFiles'] as $stubFile) {
    $stubFiles[] = basename($stubFile) === 'dom.stub'
        ? __DIR__ . '/DOM.stub'
        : $phpstanRoot . '/conf/' . $stubFile;
}

return $adapter->process([
    'parameters' => [
        'stubFiles!' => $stubFiles
    ]
], '', __FILE__);
