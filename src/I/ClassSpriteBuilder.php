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

/** Converts the configured ROM's class graphics to a transparent PNG on demand. */
final class ClassSpriteBuilder implements ExecutableBuilderStrategyInterface {
    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $id = (string) $args->get('class', '0');
        if (! preg_match('/^(?:[0-9]|1[01])$/D', $id)) {
            throw new HttpStatusException('Choose a class ID from 0 to 11.', 400);
        }
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            throw new HttpStatusException('Class graphics are currently unavailable.', 503);
        }
        try {
            $png = GameData::fromFile($path)->getClassSprite((int) $id);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpStatusException('Class graphics are currently unavailable.', 503);
        }
        return new ExecutableStrategies(new FileWriterResultBuilder(FileInfoFactory::createFromString($png), 'class-' . $id . '.png'));
    }
}
