<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\Exception\HttpStatusException;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\Module\Module;
use Slothsoft\Farah\RequestStrategy\LookupAssetStrategy;
use Slothsoft\FinalFantasy\Configuration;

/** @runTestsInSeparateProcesses */
final class ItemIconTest extends TestCase {
    public function testIconAssetResolvesToPngFromConfiguredRom(): void {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path)) {
            self::markTestSkipped('Supply the US ROM via FINAL_FANTASY_I_ROM and tests/bootstrap.php.');
        }
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/items');
        self::assertSame('/i/gfx/items', (string) Module::resolveToAsset($url)->getUrlPath());
        $response = (new LookupAssetStrategy())->process(new ServerRequest('GET', $url));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', explode(';', $response->getHeaderLine('Content-Type'))[0]);
        $png = (string) $response->getBody();
        $info = getimagesizefromstring($png);
        self::assertIsArray($info);
        self::assertSame([112, 8, IMAGETYPE_PNG], array_slice($info, 0, 3));
        self::assertSame("\xFF\xFF\0\xFF", substr($png, strpos($png, 'tRNS') + 4, 4));
    }
    
    public function testMissingRomReturnsUnavailableWithoutPrivatePath(): void {
        Configuration::setRomPath('i', '/private/missing-icons.nes');
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/items');
        self::assertSame('/i/gfx/items', (string) Module::resolveToAsset($url)->getUrlPath());
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('Item graphics are currently unavailable.');
        Module::resolveToFileWriter($url);
    }
}
