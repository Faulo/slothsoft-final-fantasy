<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use DOMElement;
use DOMException;
use InvalidArgumentException;
use RuntimeException;
use Slothsoft\Core\IO\FileInfoFactory;
use Slothsoft\FinalFantasy\Configuration;
use Slothsoft\Farah\Exception\HttpDownloadAssetException;
use Slothsoft\Farah\FarahUrl\FarahUrlArguments;
use Slothsoft\Farah\Module\Asset\AssetInterface;
use Slothsoft\Farah\Module\Asset\ExecutableBuilderStrategy\ExecutableBuilderStrategyInterface;
use Slothsoft\Farah\Module\Executable\ExecutableStrategies;
use Slothsoft\Farah\Module\Executable\ResultBuilderStrategy\FileWriterResultBuilder;

/** ROM-backed reference data and stateless equipment editing. Saves are never published or retained. */
final class PageDataBuilder implements ExecutableBuilderStrategyInterface {
    private const NS = GameData::XML_NAMESPACE;
    
    /** @throws DOMException */
    public function buildExecutableStrategies(AssetInterface $context, FarahUrlArguments $args): ExecutableStrategies {
        $view = (string) $args->get('view', 'home');
        $document = new DOMDocument('1.0', 'UTF-8');
        $root = $document->createElementNS(self::NS, 'sf1:final-fantasy');
        $document->appendChild($root);
        $root->setAttribute('view', $view);
        $path = Configuration::getRomPath('i');
        if ($path === null || ! is_file($path) || ! is_readable($path)) {
            $this->appendText($root, 'unavailable', 'Game data is currently unavailable.');
        } else {
            try {
                $rom = GameData::fromFile($path);
                $document = $rom->toDocument();
                $root = $document->documentElement;
                assert($root instanceof DOMElement);
                $root->setAttribute('view', $view);
                if ($view === 'editor') {
                    $this->processEditor($root, $rom);
                }
            } catch (HttpDownloadAssetException $exception) {
                throw $exception;
            } catch (InvalidArgumentException|RuntimeException $exception) {
                // Configuration failures must not expose the private source path.
                $this->appendText($root, 'unavailable', 'Game data is currently unavailable.');
                error_log('Final Fantasy: ' . $exception->getMessage());
            }
        }
        return new ExecutableStrategies(new FileWriterResultBuilder(FileInfoFactory::createFromDocument($document), 'final-fantasy.xml'));
    }
    
    /** @throws DOMException */
    private function processEditor(DOMElement $root, GameData $rom): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        try {
            $contents = $this->readPostedSave();
            $save = new Save($contents);
        } catch (InvalidArgumentException $exception) {
            $this->appendText($root, 'error', $exception->getMessage());
            return;
        }
        try {
            if (($_POST['ff-action'] ?? '') === 'download') {
                $output = $save->withEquipment($this->readEquipment($_POST['equipment'] ?? null), $rom);
                throw new HttpDownloadAssetException(new ExecutableStrategies(new FileWriterResultBuilder(FileInfoFactory::createFromString($output), 'Final Fantasy (USA)-edited.sav')));
            }
            $this->appendSave($root, $save, $contents);
        } catch (InvalidArgumentException $exception) {
            $this->appendText($root, 'error', $exception->getMessage());
            $this->appendSave($root, $save, $contents);
        }
    }
    
    private function readPostedSave(): string {
        $action = $_POST['ff-action'] ?? '';
        if ($action === 'upload') {
            $upload = $_FILES['ff-save'] ?? [];
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($upload['size'] ?? 0) !== 8192) {
                throw new InvalidArgumentException('Choose an 8 KiB NES battery save (.sav), not an emulator save state.');
            }
            $file = FileInfoFactory::createFromUpload($upload['tmp_name']);
            $contents = file_get_contents((string) $file);
            if ($contents === false) {
                throw new InvalidArgumentException('The save could not be read.');
            }
            return $contents;
        }
        if ($action === 'download') {
            $encoded = $_POST['ff-source'] ?? '';
            if (! is_string($encoded) || strlen($encoded) > 10924) {
                throw new InvalidArgumentException('The save data is invalid. Please load your save again.');
            }
            $contents = base64_decode($encoded, true);
            if ($contents === false) {
                throw new InvalidArgumentException('The save data is invalid. Please load your save again.');
            }
            return $contents;
        }
        throw new InvalidArgumentException('Choose a save to begin.');
    }
    
    /** @param mixed $input
     * @return array<int, array<string, array<int, array{id: int, equipped: bool}>>>
     */
    private function readEquipment(mixed $input): array {
        if (! is_array($input) || count($input) !== 4) {
            throw new InvalidArgumentException('The equipment form is incomplete.');
        }
        $result = [];
        for ($character = 0; $character < 4; $character++) {
            foreach (['weapons', 'armor'] as $kind) {
                for ($slot = 0; $slot < 4; $slot++) {
                    $value = $input[$character][$kind][$slot] ?? null;
                    if (! is_array($value) || ! isset($value['id']) || ! is_string($value['id']) || ! preg_match('/^(?:[0-9]|[1-3][0-9]|40)$/D', $value['id'])) {
                        throw new InvalidArgumentException('Select an item for every equipment slot.');
                    }
                    if (isset($value['equipped']) && $value['equipped'] !== '1') {
                        throw new InvalidArgumentException('Invalid equipped flag.');
                    }
                    $result[$character][$kind][$slot] = ['id' => (int) $value['id'], 'equipped' => isset($value['equipped'])];
                }
            }
        }
        return $result;
    }
    
    /** @throws DOMException */
    private function appendSave(DOMElement $root, Save $save, string $contents): void {
        $editor = $this->appendText($root, 'editor', '');
        $editor->setAttribute('source', base64_encode($contents));
        foreach ($save->getCharacters() as $character) {
            $node = $this->appendText($editor, 'character', '');
            foreach (['index', 'name', 'class'] as $key) {
                $node->setAttribute($key, (string) $character[$key]);
            }
            foreach (['weapons', 'armor'] as $kind) {
                $group = $this->appendText($node, $kind, '');
                foreach ($character[$kind] as $index => $item) {
                    $slot = $this->appendText($group, 'slot', '');
                    $slot->setAttribute('index', (string) $index);
                    $slot->setAttribute('id', (string) $item['id']);
                    $slot->setAttribute('equipped', $item['equipped'] ? '1' : '0');
                }
            }
        }
    }
    
    /** @throws DOMException */
    private function appendText(DOMElement $parent, string $name, string $text): DOMElement {
        $document = $parent->ownerDocument;
        assert($document instanceof DOMDocument);
        $element = $document->createElementNS(self::NS, 'sf1:' . $name);
        $element->appendChild($document->createTextNode($text));
        $parent->appendChild($element);
        return $element;
    }
}
