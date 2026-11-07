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
final class MenuFontAssetTest extends TestCase {
    public function testFontAssetResolvesFromConfiguredRom(): void {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path)) {
            self::markTestSkipped('Supply the US ROM via FINAL_FANTASY_I_ROM and tests/bootstrap.php.');
        }
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/font');
        self::assertSame('/i/font', (string) Module::resolveToAsset($url)->getUrlPath());
        $response = (new LookupAssetStrategy())->process(new ServerRequest('GET', $url));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('font/ttf', explode(';', $response->getHeaderLine('Content-Type'))[0]);
        $font = (string) $response->getBody();
        self::assertSame("\0\1\0\0", substr($font, 0, 4));
        self::assertSame(GameData::fromFile($path)->getMenuFont(), $font);
    }
    
    public function testMissingRomReturnsUnavailableWithoutPrivatePath(): void {
        Configuration::setRomPath('i', '/private/missing-icons.nes');
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/font');
        self::assertSame('/i/font', (string) Module::resolveToAsset($url)->getUrlPath());
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('The menu font is currently unavailable.');
        Module::resolveToFileWriter($url);
    }
}
