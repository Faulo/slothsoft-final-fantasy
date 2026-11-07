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
final class ClassSpriteTest extends TestCase {
    public function testAllClassAssetsResolveToDistinctPngSheets(): void {
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path)) {
            self::markTestSkipped('Supply the US ROM via FINAL_FANTASY_I_ROM and tests/bootstrap.php.');
        }
        $hashes = [];
        for ($class = 0; $class < 12; $class++) {
            $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/class?class=' . $class);
            self::assertSame('/i/gfx/class', (string) Module::resolveToAsset($url)->getUrlPath());
            $request = (new ServerRequest('GET', $url))->withQueryParams(['class' => (string) $class]);
            $response = (new LookupAssetStrategy())->process($request);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('image/png', explode(';', $response->getHeaderLine('Content-Type'))[0]);
            $png = (string) $response->getBody();
            $info = getimagesizefromstring($png);
            self::assertIsArray($info);
            self::assertSame([128, 24, IMAGETYPE_PNG], array_slice($info, 0, 3));
            $hashes[] = hash('sha256', $png);
        }
        self::assertCount(12, array_unique($hashes));
    }
    
    public function testMissingRomDoesNotLeakItsPath(): void {
        Configuration::setRomPath('i', '/private/missing-game.nes');
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/class?class=2');
        self::assertSame('/i/gfx/class', (string) Module::resolveToAsset($url)->getUrlPath());
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(503);
        $this->expectExceptionMessage('Class graphics are currently unavailable.');
        Module::resolveToFileWriter($url);
    }
    
    /** @dataProvider invalidClasses */
    public function testRejectsInvalidClass(string $class): void {
        $this->expectException(HttpStatusException::class);
        $this->expectExceptionCode(400);
        Module::resolveToFileWriter(FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/gfx/class?class=' . rawurlencode($class)));
    }
    
    public function invalidClasses(): array {
        return [['-1'], ['12'], ['2foo'], ['../2']];
    }
}
