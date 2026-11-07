<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Slothsoft\Farah\FarahUrl\FarahUrl;
use Slothsoft\Farah\Module\Module;
use Slothsoft\FinalFantasy\Configuration;

/** @runTestsInSeparateProcesses */
final class PageSchemaTest extends TestCase {
    
    public function testUnavailableDocumentMatchesSchema(): void {
        Configuration::setRomPath('i', '/nonexistent/ff1-schema-test.nes');
        $document = $this->loadPage('armor');
        self::assertSame(1, $document->getElementsByTagNameNS(GameData::XML_NAMESPACE, 'unavailable')->length);
        $this->validate($document);
    }
    
    public function testReferencePageMatchesSchema(): void {
        $this->requireRom();
        $document = $this->loadPage('spells');
        self::assertSame('spells', $document->documentElement->getAttribute('view'));
        self::assertSame(64, $document->getElementsByTagNameNS(GameData::XML_NAMESPACE, 'spell')->length);
        $this->validate($document);
    }
    
    public function testEditorErrorAndPreservedSaveMatchSchema(): void {
        $this->requireRom();
        $contents = str_repeat("\0", 8192);
        $contents[0x4FD] = "\xFF";
        $contents[0x4FE] = "\x55";
        $contents[0x4FF] = "\xAA";
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['ff-action' => 'download', 'ff-source' => base64_encode($contents), 'equipment' => []];
        $document = $this->loadPage('editor');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('ff', GameData::XML_NAMESPACE);
        self::assertSame(1.0, $xpath->evaluate('count(/ff:final-fantasy/ff:error)'));
        self::assertSame(4.0, $xpath->evaluate('count(/ff:final-fantasy/ff:editor/ff:character)'));
        self::assertSame(32.0, $xpath->evaluate('count(/ff:final-fantasy/ff:editor/ff:character/*/ff:slot)'));
        self::assertSame(base64_encode($contents), $xpath->evaluate('string(/ff:final-fantasy/ff:editor/@source)'));
        $this->validate($document);
    }
    
    private function loadPage(string $view): DOMDocument {
        return Module::resolveToDOMWriter(FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/api/data?view=' . $view))->toDocument();
    }
    
    private function validate(DOMDocument $document): void {
        self::assertTrue($document->schemaValidate(dirname(__DIR__, 2) . '/assets/schema/i/1.0.xsd'));
    }
    
    private function requireRom(): void {
        $path = Configuration::getRomPath('i') ?? '';
        if (! is_file($path)) {
            self::markTestSkipped('Supply the original US ROM via FINAL_FANTASY_I_ROM for page integration tests.');
        }
    }
}

