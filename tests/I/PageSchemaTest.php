<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Slothsoft\Core\ServerEnvironment;
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
        self::assertNotNull($document->documentElement);
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
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame(1.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:error)'));
        self::assertSame(4.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:editor/sf1:character)'));
        self::assertSame(32.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:editor/sf1:character/*/sf1:slot)'));
        self::assertSame(4.0, $xpath->evaluate('count(/sf1:final-fantasy/sf1:editor/sf1:character/sf1:stats[@hp="0"][@level="1"][@magic-defense="0"])'));
        self::assertSame(base64_encode($contents), $xpath->evaluate('string(/sf1:final-fantasy/sf1:editor/@source)'));
        $this->validate($document);
    }
    
    public function testEditorMetadataUsesDiskModificationTime(): void {
        $this->requireRom();
        $directory = ServerEnvironment::getDataDirectory() . '/slothsoft/final-fantasy/i/users';
        $repository = new SaveRepository($directory);
        $user = SaveRepository::userId('');
        $contents = str_repeat("\0", 8192);
        $contents[0x4FD] = "\xFF";
        $contents[0x4FE] = "\x55";
        $contents[0x4FF] = "\xAA";
        $repository->store($user, $contents);
        self::assertTrue(touch($directory . '/' . $user . '.sav', 946684800));
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['user' => $user];
        $document = $this->loadPage('editor');
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('sf1', GameData::XML_NAMESPACE);
        self::assertSame('2000-01-01T00:00:00Z', $xpath->evaluate('string(/sf1:final-fantasy/sf1:editor/@modified)'));
        self::assertSame('8192', $xpath->evaluate('string(/sf1:final-fantasy/sf1:editor/@size)'));
        self::assertSame(946684800, $repository->getModifiedTime($user));
        $this->validate($document);
    }
    
    private function loadPage(string $view): DOMDocument {
        return Module::resolveToDOMWriter(FarahUrl::createFromReference('farah://slothsoft@final-fantasy/i/api/data?view=' . $view))->toDocument();
    }
    
    private function validate(DOMDocument $document): void {
        foreach ($document->getElementsByTagNameNS(GameData::XML_NAMESPACE, '*') as $element) {
            self::assertSame('sf1', $element->prefix);
        }
        self::assertTrue($document->schemaValidate(dirname(__DIR__, 2) . '/assets/schema/i/1.0.xsd'));
    }
    
    private function requireRom(): void {
        $path = Configuration::getRomPath('i') ?? '';
        if (! is_file($path)) {
            self::markTestSkipped('Supply the original US ROM via FINAL_FANTASY_I_ROM for page integration tests.');
        }
    }
}

