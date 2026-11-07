<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use DOMDocument;
use PHPUnit\Framework\TestCase;

/** @covers \Slothsoft\FinalFantasy\I\TextCodec */
final class TextCodecTest extends TestCase {
    
    public function testDecodesOriginalSpellingAndCase(): void {
        self::assertSame('Falchon', TextCodec::decode("\x8F\xA4\xAF\xA6\xAB\xB2\xB1\0\x8A"));
        self::assertSame('Greg', TextCodec::decode("\x90\xB5\xA8\xAA"));
    }
    
    public function testPreservesIconAndUnknownGlyphIdentity(): void {
        self::assertSame('A [sword]', TextCodec::decode("\x8A\xFF\xD4\xFF"));
        self::assertSame('[7F]', TextCodec::decode("\x7F"));
        self::assertSame('', TextCodec::decode(''));
    }
    
    public function testMixedLabelKeepsSpacesAndGlyphPositions(): void {
        $document = new DOMDocument();
        $label = TextCodec::createLabel($document, "\xD4\xFF\x8A\xFF\xD9\xFF\x7F\xFF\0\x8B");
        self::assertSame('<sf1:label xmlns:sf1="http://schema.slothsoft.net/final-fantasy/i"><sf1:icon glyph="212" label="sword"/> A <sf1:icon glyph="217" label="nunchuck"/> [7F]</sf1:label>', $document->saveXML($label));
    }
}
