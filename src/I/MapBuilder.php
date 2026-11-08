<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use RuntimeException;
use Slothsoft\Core\IO\FileInfoFactory;
use Slothsoft\Farah\Exception\HttpStatusException;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\FileWriterResultBuilder;
use Slothsoft\FinalFantasy\Configuration;

/** Converts the configured ROM's map graphics to an opaque PNG on demand. */
final class MapBuilder implements ExecutableBuilderStrategyInterface {
    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $id = (string) $args->get('map', 'world');
        if ($id !== 'world' && ! preg_match('/^(?:[0-9]|[1-5][0-9]|60)$/D', $id)) {
            throw new HttpStatusException('Choose world or a map ID from 0 to 60.', 400);
        }
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            throw new HttpStatusException('Map graphics are currently unavailable.', 503);
        }
        try {
            $png = GameData::fromFile($path)->getMap($id);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpStatusException('Map graphics are currently unavailable.', 503);
        }
        return new ExecutableStrategies(new FileWriterResultBuilder(FileInfoFactory::createFromString($png), 'map-' . $id . '.png'));
    }
}

