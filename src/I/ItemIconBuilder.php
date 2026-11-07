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

final class ItemIconBuilder implements ExecutableBuilderStrategyInterface {
    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            throw new HttpStatusException('Item graphics are currently unavailable.', 503);
        }
        try {
            $png = GameData::fromFile($path)->getItemIcons();
        } catch (InvalidArgumentException|RuntimeException $exception) {
            throw new HttpStatusException('Item graphics are currently unavailable.', 503);
        }
        return new ExecutableStrategies(new FileWriterResultBuilder(FileInfoFactory::createFromString($png), 'item-icons.png'));
    }
}
