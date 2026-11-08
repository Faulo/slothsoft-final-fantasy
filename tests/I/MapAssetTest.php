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
final class MapAssetTest extends TestCase {
    public function testMapAssetsResolveToDistinctPngImages(): void {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path)) {
            self::markTestSkipped('Supply the US ROM via FINAL_FANTASY_I_ROM and tests/bootstrap.php.');
        }
        $hashes = [];
        foreach (['world', '0', '15', '60'] as $map) {
            $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/map?map=' . $map);
            self::assertSame('/i/gfx/map', (string) Module::resolveToAsset($url)->getUrlPath());
            $request = (new ServerRequest('GET', $url))->withQueryParams(['map' => (string) $map]);
            $response = (new LookupAssetStrategy())->process($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('image/png', explode(';', $response->getHeaderLine('Content-Type'))[0]);
            $png = (string) $response->getBody();
            $info = getimagesizefromstring($png);
            self::assertIsArray($info);
            $size = $map === 'world' ? 4096 : 1024;
            self::assertSame([$size, $size, IMAGETYPE_PNG], array_slice($info, 0, 3));
            $hashes[] = hash('sha256', $png);
        }
        self::assertCount(4, array_unique($hashes));
    }
    
    public function testMissingRomDoesNotLeakItsPath(): void {
        Configuration::setRomPath('i', '/private/missing-game.nes');
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/map?map=2');
        self::assertSame('/i/gfx/map', (string) Module::resolveToAsset($url)->getUrlPath());
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('Map graphics are currently unavailable.');
        Module::resolveToFileWriter($url);
    }
    
    /** @dataProvider invalidMaps */
    public function testRejectsInvalidMap(string $map): void {
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(400);
        Module::resolveToFileWriter(FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/map?map=' . rawurlencode($map)));
    }
    
    /** @return list<array{string}> */
    public function invalidMaps(): array {
        return [['-1'], ['61'], ['2foo'], ['../2']];
    }
}


