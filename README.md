# slothsoft/final-fantasy

A Farah module for Final Fantasy game data and save editing. Currently supports
the original US NES release of Final Fantasy I. Requires PHP 8.2 or later and
the upcoming savegame 1.6 features through `slothsoft/savegame:dev-main`.

## Installation and private ROM configuration

Until a release is published, add the Git repository to your application's
Composer repositories and require `slothsoft/final-fantasy:dev-main`:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/Faulo/slothsoft-final-fantasy"}
    ],
    "require": {
        "slothsoft/final-fantasy": "dev-main",
        "slothsoft/savegame": "dev-main"
    }
}
```

Configure the ROM in your application's bootstrap, after Composer autoloading:

```php
use Slothsoft\FinalFantasy\Configuration;

Configuration::setRomPath('i', '/private/games/Final Fantasy (USA).nes');
```

The path belongs to the PHP runtime (a container path in DDEV). There is no
default ROM path in the package. Passing `null` clears the configuration. Store
the ROM outside public document roots and published assets. ROMs and saves are
ignored by Git and excluded from distribution archives; none are bundled.
The overview remains available without a ROM; data/editor pages explain when
game data is unavailable without exposing the configured filesystem path.

Only the original US PRG is accepted, SHA-256
`fa456d852372173ea31b192459ba1a2026f779df67793327ba6e132476c1d034`.
iNES headers and optional trainers are handled separately. Japanese releases,
randomizers and other modified PRGs are not supported.

## Module and schema

Composer registers the Farah module `slothsoft@final-fantasy`.

| Asset                                                     | Purpose                                                              |
|-----------------------------------------------------------|----------------------------------------------------------------------|
| `farah://slothsoft@final-fantasy/i/api/data?view=weapons` | Domain XML; views are `home`, `editor`, `weapons`, `armor`, `spells` |
| `farah://slothsoft@final-fantasy/i/html/home`             | Overview fragment                                                    |
| `farah://slothsoft@final-fantasy/i/html/editor`           | Equipment editor fragment                                            |
| `farah://slothsoft@final-fantasy/i/html/weapons`          | Weapons reference fragment                                           |
| `farah://slothsoft@final-fantasy/i/html/armor`            | Armor reference fragment                                             |
| `farah://slothsoft@final-fantasy/i/html/spells`           | Spells reference fragment                                            |
| `farah://slothsoft@final-fantasy/schema/i/1.0`            | Versioned domain XSD                                                 |
| `farah://slothsoft@final-fantasy/i/savegame/rom`          | ROM extraction specification                                         |
| `farah://slothsoft@final-fantasy/i/savegame/equipment`    | SRAM equipment specification                                         |
| `farah://slothsoft@final-fantasy/i/gfx/class?class=2`     | Black Belt battle sprite sheet (class IDs 0–11)                      |
| `farah://slothsoft@final-fantasy/i/gfx/items`             | Item-name icon sheet from the original menu font                     |

The domain namespace is `http://schema.slothsoft.net/final-fantasy/i`, with prefix `sf1`. Hosts
embed the HTML fragments and provide `/FinalFantasy/I/`,
`/FinalFantasy/I/SaveEditor/`, `/FinalFantasy/I/Weapons/`, `/FinalFantasy/I/Armor/`
and `/FinalFantasy/I/Spells/` routes used by the navigation.

## Class graphics

Class PNGs are generated on demand from the configured private ROM. The ROM
manifest extracts each class's 32 NES two-bitplane tiles, the palette assignments,
palette color indices and eight pose arrangements. No extracted images are shipped.
Each transparent PNG is 128×24 pixels: eight 16×24 poses arranged horizontally in
ROM order (standing, walking, attack back, attack forward, cheer, cheer, crouch,
crouch). The hardware color indices use a conventional 2C02 RGB approximation;
the original composite colors depend on the display and are not RGB data in the ROM.
PNG encoding uses zlib, without requiring GD, ImageMagick or an external converter.

The reference tables use CSS to crop the standing pose at 2× pixel scale. All
twelve classes appear in every row, with base classes above their promotions;
classes unable to equip or learn the item are dimmed. Tooltips and accessible labels
use ROM class names and state whether that class can equip or learn it. The public
HTTP asset is `/slothsoft@final-fantasy/i/gfx/class?class=2`; invalid class IDs return
400 and unavailable ROMs return 503. The default class ID is 0 (Fighter).

Item icons use the same on-demand PNG pipeline. The 112×8 sheet contains the
fourteen menu-font tiles D4–E1 in ROM order. The manifest reads both their pixels
and the menu palette; palette entry 2 (the menu background) becomes transparent.
CSS selects an 8×8 glyph at 2× scale. Weapons and armor expose an optional mixed
`sf1:label` containing text and `sf1:icon` nodes with the original glyph ID and an
accessible label. Existing `@name` text fallbacks remain available to consumers.
Reference tables render the same tiles as font glyphs inline in their original position. Equipment
options use the ROM font and its original icons in an in-page combobox, with the selected icon beside the dropdown;
changing the selection updates that preview without changing save behavior.

## Binary extraction and implemented behavior

Like `slothsoft/amber`, this module uses a savegame XML specification to retrieve
game data from the original binary. `assets/i/savegame/rom.xml` owns all record
offsets, strides, little-endian name pointers, prices and class permission masks.
Name and spell-permission pointers use repeated records; the dereferencing
string or repeat-group explicitly opts into `variable-resolution="scoped"`
to select its own record's pointer. Resolution settings do not inherit.
The `NesArchiveExtractor` validates the iNES container and US PRG identity, then
exposes PRG bytes to the savegame parser. It contains no game-data table offsets.
`GameData` runs `Editor` and `XmlBuilder`, decodes the parsed FF text through
`TextCodec`, and applies `assets/i/xsl/data.xsl` to produce domain XML. There is
no second hand-written ROM record parser. Names, including their spelling and
case, come from the ROM; equipment glyphs retain their original tile identity.
`GameData::getDatasetDocument()` returns the parsed savegame dataset with text
in its ISO-8859-1 carrier encoding; `toDocument()` returns decoded domain data.

Weapons and armor include equip permissions and spells cast by items. Spells
include learning permissions, level, school and price. The reference pages
describe the original US implementation: weapon critical thresholds use the
weapon IDs, ineffective elemental/enemy-category weapon bonuses are omitted,
and armor resistance masks are decoded. Stored spell metadata is not presented
as a guarantee that the corresponding effect works as intended.

`Save` uses `assets/i/savegame/equipment.xml` to edit four weapon and four armor
slots for each character in an 8192-byte battery save, including equipped flags.
Class permissions, one equipped weapon and one item per armor category are
enforced. Inventory may contain unusable items; unchanged pre-existing invalid
equipment is preserved. Character status, learned spells, party items and
general data are outside the editor scope.

No-op exports are byte-identical. Changes update equipment slots, the checksum,
and cached combat stats when the equipped set changes, reproducing original
byte overflow and Black Belt/Master rules. Other SRAM bytes are preserved.
The checksum follows the game's interleaved 6502 carry accumulation and the
result is validated before download. The web flow is stateless: source save
bytes travel as base64 in the form and are revalidated on download.

## Development and validation

Use DDEV's PHP runtime (`PHP_VERSION` in `.env`):

```sh
ddev start
ddev composer install
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --error-format=json
```

Tests use synthetic binary data and catalogs. Optional integration tests accept
an original ROM through `FINAL_FANTASY_I_ROM` inside the test runtime; they skip
when it is absent. No copyrighted fixtures are required for CI. Tests cover
save round-trips, the manifest extraction pipeline, schema validity and both
Farah asset lookup and resolved output.

## References and license

MIT licensed. Binary layout and behavior were checked against
[Disch's US disassembly, maintained by Entroper](https://github.com/Entroper/FF1Disassembly)
and the MIT-licensed
[Game Tools Collection editor](https://github.com/RyudoSynbios/game-tools-collection/tree/master/src/lib/templates/final-fantasy/saveEditor).
No disassembly binaries or third-party editor source are bundled.

## ROM typography

`farah://slothsoft@final-fantasy/i/font` returns a generated `font/ttf` font.
The savegame manifest reads all 128 menu tiles (80–FF); PHP converts foreground
pixels (palette index 3) to rectangular TrueType contours. No font files or ROM
pixels are shipped, and no external font converter is required. Missing ROMs
return 503, as with the PNG assets.

ASCII letters, digits and decoded punctuation map to their original tiles.
Unicode private-use characters U+E080–U+E0FF expose all tiles, including equipment
icons. Text remains selectable; item options retain accessible text names.
ROM/save names use the font at 16px (twice the original 8px grid): equipment,
spells, cast links, character legends and class tooltips. Interface labels and
explanations retain the host typography. Unsupported characters use the browser's
fallback font. Existing plain-text domain names and PNG endpoints are unchanged.

The encoder follows the [OpenType table specification](https://learn.microsoft.com/en-us/typography/opentype/spec/otff).

Equipment pickers enhance the original form selects with in-page listboxes, since
operating-system dropdowns may ignore downloaded fonts. Arrow keys, Home/End,
Page Up/Down and typing navigate; Enter/Space/Tab commit, and Escape cancels.
The native select remains the submitted form value. Without JavaScript, native
options show readable icon descriptions instead of private-use glyph boxes.

The browser regression check is `node tests/browser/equipment-picker.cjs` with
Playwright available (or `FF_PLAYWRIGHT_MODULE` pointing to its module),
`FF_SAVE_PATH` pointing to a private sample save, and optionally `FF_EDITOR_URL`
and `FF_BROWSER_CHANNEL` overriding the local DDEV URL and Edge browser.
