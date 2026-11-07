<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy;

use DOMXPath;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\FarahUrl\FarahUrlAuthority;
use Slothsoft\Farah\Module\Module;
use Slothsoft\FarahTesting\Module\AbstractModuleTest;
use Slothsoft\FinalFantasy\I\GameData;

final class AssetsModuleTest extends AbstractModuleTest {
    protected static function getManifestAuthority(): FarahUrlAuthority {
        return FarahUrlAuthority::createFromVendorAndModule('slothsoft', 'final-fantasy');
    }
    
    public function testDomainSchemaResolvesAtItsNamespaceLocation(): void {
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/schema/i/1.0');
        $document = Module::resolveToDOMWriter($url)->toDocument();
        self::assertSame('http://www.w3.org/2001/XMLSchema', $document->documentElement->namespaceURI);
        self::assertSame(GameData::XML_NAMESPACE, $document->documentElement->getAttribute('targetNamespace'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $xpath->registerNamespace('ssv', 'http://schema.slothsoft.net/schema/versioning');
        self::assertSame('1.0', $xpath->evaluate('string(/xs:schema/xs:annotation/xs:appinfo/ssv:info/ssv:version)'));
        self::assertSame(GameData::XML_NAMESPACE, $xpath->evaluate('string(/xs:schema/xs:annotation/xs:appinfo/ssv:info/ssv:namespace)'));
    }
    
    /** @runInSeparateProcess */
    public function testDataWithoutConfiguredRomResolvesToValidUnavailableDocument(): void {
        Configuration::setRomPath('i', null);
        $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/api/data?view=weapons');
        $document = Module::resolveToDOMWriter($url)->toDocument();
        self::assertTrue($document->schemaValidate(dirname(__DIR__) . '/assets/schema/i/1.0.xsd'));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ff', GameData::XML_NAMESPACE);
        self::assertSame('weapons', $xpath->evaluate('string(/ff:final-fantasy/@view)'));
        self::assertSame(1.0, $xpath->evaluate('count(/ff:final-fantasy/ff:unavailable)'));
        self::assertSame(0.0, $xpath->evaluate('count(/ff:final-fantasy/ff:weapons)'));
    }
    
    /** @runInSeparateProcess */
    public function testPublicPagesResolveWithoutPrivateRom(): void {
        Configuration::setRomPath('i', null);
        foreach (['home', 'editor', 'weapons', 'armor', 'spells'] as $view) {
            $url = FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/html/' . $view);
            $document = Module::resolveToDOMWriter($url)->toDocument();
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('html', 'http://www.w3.org/1999/xhtml');
            self::assertSame(1.0, $xpath->evaluate('count(//html:article)'), $view);
            self::assertStringContainsString($view === 'home' ? 'The equipment and magic of Final Fantasy' : 'Game data is currently unavailable.', $document->textContent, $view);
        }
    }
}
