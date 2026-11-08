<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Psr7\ServerRequest;
use Slothsoft\Farah\RequestStrategy\LookupAssetStrategy;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlAuthority;
use Slothsoft\Farah\Module\Module;
use Slothsoft\FarahTesting\Module\AbstractModuleTest;
use Slothsoft\FinalFantasy\I\GameData;
use Slothsoft\FinalFantasy\I\NesSpriteSheet;

final class AssetsModuleTest extends AbstractModuleTest {
    protected static function getManifestAuthority(): FarahUrlAuthority {
        return FarahUrlAuthority::createFromVendorAndModule('slothsoft', 'final-fantasy');
    }
    
    /** @return array<string, array{\Slothsoft\Farah\FarahUrl\FarahUrl}> */
    public function assetLocalUrlProvider(): array {
        $assets = parent::assetLocalUrlProvider();
        $rom = Configuration::getRomPath('i');
        if ($rom === null || ! is_file($rom) || ! is_readable($rom)) {
            // Unlike XML pages, PNG assets require a private ROM. ClassSpriteTest covers
            // their lookup and 503 contract without it, and actual PNG results with it.
            unset($assets['farah://slothsoft@final-fantasy/i/gfx/class']);
            unset($assets['farah://slothsoft@final-fantasy/i/gfx/items']);
            unset($assets['farah://slothsoft@final-fantasy/i/font']);
        }
        return $assets;
    }
    
    public function testDomainSchemaResolvesAtItsNamespaceLocation(): void {
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/schema/i/1.0');
        $document = Module::resolveToDOMWriter($url)->toDocument();
        self::assertNotNull($document->documentElement);
        self::assertSame('http://www.w3.org/2001/XMLSchema', $document->documentElement->namespaceURI);
        self::assertSame(GameData::XML_NAMESPACE, $document->documentElement->getAttribute('targetNamespace'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $xpath->registerNamespace('ssv', 'http://schema.slothsoft.net/schema/versioning');
        self::assertSame('1.0', $xpath->evaluate('string(/xs:schema/xs:annotation/xs:appinfo/ssv:info/ssv:version)'));
        self::assertSame(GameData::XML_NAMESPACE, $xpath->evaluate('string(/xs:schema/xs:annotation/xs:appinfo/ssv:info/ssv:namespace)'));
        self::assertSame('sf1', $xpath->evaluate('string(/xs:schema/xs:annotation/xs:appinfo/ssv:info/ssv:prefix)'));
    }
    
    /** @runInSeparateProcess */
    public function testDataWithoutConfiguredRomResolvesToValidUnavailableDocument(): void {
        Configuration::setRomPath('i', null);
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/api/data?view=weapons');
        $document = Module::resolveToDOMWriter($url)->toDocument();
        self::assertTrue($document->schemaValidate(dirname(__DIR__) . '/assets/schema/i/1.0.xsd'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('weapons', $xpath->evaluate('string(/sf1:final-fantasy/@view)'));
        self::assertSame(1.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:unavailable)'));
        self::assertSame(0.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:weapons)'));
    }
    
    /** @runInSeparateProcess */
    public function testUtilityExampleResolvesWithoutPrivateRom(): void {
        Configuration::setRomPath('i', null);
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/examples/panels');
        self::assertSame('/i/examples/panels', (string) Module::resolveToAsset($url)->getUrlPath());
        $response = (new LookupAssetStrategy())->process(new ServerRequest('GET', $url));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/xhtml+xml', explode(';', $response->getHeaderLine('content-type'))[0]);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML((string) $response->getBody()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
        self::assertSame(3.0, $xpath->evaluate('count(//html:form[contains(@class, "ff-ui")])'));
        self::assertSame(2.0, $xpath->evaluate('count(//html:fieldset[contains(@class, "ff-ui")]//html:table)'));
        self::assertSame('/slothsoft@final-fantasy/i/css/default', $xpath->evaluate('string(//html:link[@rel="stylesheet"]/@href)'));
        self::assertSame(0.0, $xpath->evaluate('count(//html:a[not(starts-with(@href, "#"))] | //html:nav)'));
        self::assertSame(1.0, $xpath->evaluate('count(//html:a[@href="#palette"])'));
        self::assertSame(55.0, $xpath->evaluate('count(//html:section[@aria-label="Named NES palette"]//html:li)'));
    }
    
    public function testUtilityStylesheetResolves(): void {
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/css/default');
        $response = (new LookupAssetStrategy())->process(new ServerRequest('GET', $url));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/css', explode(';', $response->getHeaderLine('content-type'))[0]);
        self::assertStringContainsString('/slothsoft@final-fantasy/i/font', (string) $response->getBody());
    }
    
    public function testUtilityPaletteMatchesAllSpriteRendererColors(): void {
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/css/default');
        $response = (new LookupAssetStrategy())->process(new ServerRequest('GET', $url));
        $css = (string) $response->getBody();
        if (preg_match('/\.ff-ui\s*\{([^}]+)}/', $css, $block) !== 1) {
            self::fail('The FF UI palette block is missing.');
        }
        self::assertSame(55, preg_match_all('/--ff-([a-z-]+):\s*#([a-f0-9]{6});/i', $block[1], $colors));
        $namedColors = array_combine($colors[1], $colors[2]);
        self::assertSame('f8b800', $namedColors['gold']);
        self::assertSame('503000', $namedColors['brown']);
        self::assertSame('0000fc', $namedColors['blue']);
        self::assertSame('fcfcfc', $namedColors['white']);
        self::assertSame('7c7c7c', $namedColors['gray']);
        self::assertSame('000000', $namedColors['black']);
        
        $pngColors = [];
        for ($index = 0; $index < 64; $index += 4) {
            $palette = pack('C4', $index, $index + 1, $index + 2, $index + 3);
            $png = NesSpriteSheet::toTileStrip(str_repeat("\0", 16), $palette);
            $position = strpos($png, 'PLTE');
            self::assertNotFalse($position);
            foreach (str_split(substr($png, $position + 4, 12), 3) as $rgb) {
                $pngColors[] = bin2hex($rgb);
            }
        }
        $pngColors = array_values(array_unique($pngColors));
        $cssColors = array_values($namedColors);
        sort($pngColors);
        sort($cssColors);
        self::assertSame($pngColors, $cssColors, 'CSS must expose every renderer color without adding or changing RGB values.');
    }
}
