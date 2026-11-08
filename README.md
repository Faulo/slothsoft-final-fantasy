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
The XML API reports unavailable game data without exposing the configured
filesystem path when the ROM cannot be read.

Only the original US PRG is accepted, SHA-256
`fa456d852372173ea31b192459ba1a2026f779df67793327ba6e132476c1d034`.
iNES headers and optional trainers are handled separately. Japanese releases,
randomizers and other modified PRGs are not supported.

## Module and schema

Composer registers the Farah module `slothsoft@final-fantasy`.

| Asset                                                     | Purpose                                                              |
|-----------------------------------------------------------|----------------------------------------------------------------------|
| `farah://slothsoft@final-fantasy/i/api/data?view=weapons` | Domain XML; views are `home`, `editor`, `weapons`, `armor`, `spells` |
| `farah://slothsoft@final-fantasy/i/css/default`           | Reusable FF panel, typography, control and sprite utilities          |
| `farah://slothsoft@final-fantasy/i/examples/panels`       | Standalone XHTML examples at 1x and 2x scale                         |
| `farah://slothsoft@final-fantasy/schema/i/1.0`            | Versioned domain XSD                                                 |
| `farah://slothsoft@final-fantasy/i/savegame/rom`          | ROM extraction specification                                         |
| `farah://slothsoft@final-fantasy/i/savegame/equipment`    | SRAM equipment specification                                         |
| `farah://slothsoft@final-fantasy/i/font`                  | ROM-derived TrueType font                                            |
| `farah://slothsoft@final-fantasy/i/gfx/class?class=2`     | Black Belt battle sprite sheet (class IDs 0–11)                      |
| `farah://slothsoft@final-fantasy/i/gfx/items`             | Item-name icon sheet from the menu font                              |

The domain namespace is `http://schema.slothsoft.net/final-fantasy/i`, with prefix
`sf1`. Applications consume the XML data API and own their HTML, page routes,
form destinations and browser behavior. The former `/i/html/*` page fragments,
`/i/xsl/page` and `/i/js/item-icons` have moved into the consuming site as part of
the pre-release package/site separation.

## Utility CSS and host integration

The `.ff-ui` block exposes the full 64-entry NES palette used by the PNG renderer
as 55 descriptive color variables, including `--ff-gold`, `--ff-brown`,
`--ff-mint` and `--ff-pale-blue`. Duplicate black indices share `--ff-black`.
Each declaration documents its NES indices; the existing `--ff-blue`,
`--ff-white`, `--ff-gray` and `--ff-black` values are unchanged. These are
palette primitives, with no new semantic roles assigned.

The ROM supplies hardware color indices, not RGB values. These variables use
the same conventional 2C02 RGB approximation as `NesSpriteSheet`; they are not
RGB colors extracted from the ROM or a claim that every color is used by FF1.
See the palette swatches in the example HTML below. A host can use a color
directly, for example `.my-heading { color: var(--ff-gold); }`, and introduce
semantic aliases later.

Load `/slothsoft@final-fantasy/i/css/default` in the host page. Apply
`class="ff-ui ff-panel"` to a form or a table container; add `ff-table` to make a
wide table scroll within its panel. Only these components receive the ROM font
and white-on-blue theme, including headings, labels and native controls.
The host's surrounding text and page header keep their own styling.

Links use color instead of underlines: gold when unvisited, ochre (NES $18) when
visited, cyan on hover or keyboard focus, and white while pressed. Keyboard
focus retains its visible outline. Active color takes precedence over hover
and focus. The example's palette link demonstrates these states.

The base units are `--ff-font-size: 8px` and `--ff-sprite-size: 24px` (height).
`--ff-scale` defaults to 1. Set it on each `ff-ui` root to multiply text, sprites,
spacing and borders together, for example `style="--ff-scale: 2"`. The line
height is 150%; the border unit is one eighth of the scaled font size. All panel
text has an opaque black shadow offset one scaled pixel right and down, with
no blur. Descendants and controls inherit it without adding another shadow.
Character sprites preserve their 16:24 aspect ratio. `.ff-class-sprite` crops the standing
pose by default; set `--ff-pose` to an index from 0 to 7 for another pose.
`.ff-item-icon` uses `--ff-icon-index` (0–13) to select a menu pictogram.

See [the complete example HTML](assets/i/examples/panels.xhtml) for forms,
tables, symbols and class sprites. Example text is illustrative; actual game
names must come from the XML API. The examples depend only on package assets.

For editing, hosts consume `i/api/data?view=editor`. It accepts `ff-action=upload`
with the `ff-save` file, or `ff-action=download` with the returned base64
`ff-source` and `equipment[character][weapons|armor][slot][id|equipped]` fields.
The host chooses the form action. Successful downloads return the edited save;
errors and loaded party data are represented in the domain XML.

## Class graphics

Class PNGs are generated on demand from the configured private ROM. The ROM
manifest extracts each class's 32 NES two-bitplane tiles, the palette assignments,
palette color indices and eight pose arrangements. No extracted images are shipped.
Each transparent PNG is 128×24 pixels: eight 16×24 poses arranged horizontally in
ROM order (standing, walking, attack back, attack forward, cheer, cheer, crouch,
crouch). The hardware color indices use a conventional 2C02 RGB approximation;
the original composite colors depend on the display and are not RGB data in the ROM.
PNG encoding uses zlib, without requiring GD, ImageMagick or an external converter.

The CSS utilities crop a single pose at the selected scale. Use
`data-allowed="false"` to dim an unavailable class sprite. Hosts supply the
class names and equip/learn descriptions from the XML data. The public HTTP asset
is `/slothsoft@final-fantasy/i/gfx/class?class=2`; invalid class IDs return 400 and
unavailable ROMs return 503. The default class ID is 0 (Fighter).

Item icons use the same on-demand PNG pipeline. The 112×8 sheet contains the
fourteen menu-font tiles D4–E1 in ROM order. The manifest reads both their pixels
and the menu palette; palette entry 2 (the menu background) becomes transparent.
CSS selects an 8×8 glyph at the configured scale. Weapons and armor expose an optional mixed
`sf1:label` containing text and `sf1:icon` nodes with the original glyph ID and an
accessible label. Existing `@name` text fallbacks remain available to consumers.
Hosts render item names as plain Unicode text, including pictograms in their
original position. The dynamic font maps those characters to the ROM tiles.
The PNG sheet is also available for standalone icon previews.

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
include learning permissions, level, school, price and `sf1:effect-description`
lines describing the implemented US effects. `getSpells()` also exposes these
lines as `effectDescription`. The optional `sf1:spell-rules` element supplies
ROM-derived hit-roll constants, the HP threshold and menu healing ranges.
`SpellEffects` interprets parsed values without reading ROM offsets or producing
HTML. See [spell mechanics and extraction sources](doc/spell-effects.md).
The reference pages
describe the original US implementation: weapon critical thresholds use the
weapon IDs, ineffective elemental/enemy-category weapon bonuses are omitted,
and armor resistance masks are decoded. Spell descriptions report actual bugs,
including ineffective LOCK, TMPR, SABR and XFER, beneficial enemy evasion from
LOK2, and the distinct battle and menu healing ranges.

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
icons, as backward-compatible aliases. Item labels use these Unicode equivalents:

| Icon     | Character | Code point |
|----------|-----------|------------|
| Sword    | 🗡        | U+1F5E1    |
| Hammer   | 🔨        | U+1F528    |
| Knife    | 🔪        | U+1F52A    |
| Axe      | 🪓        | U+1FA93    |
| Staff    | 🪄        | U+1FA84    |
| Nunchaku | 🥢        | U+1F962    |
| Armor    | 🦺        | U+1F9BA    |
| Shield   | ⛨        | U+26E8     |
| Helmet   | 🪖        | U+1FA96    |
| Gauntlet | 🧤        | U+1F9E4    |
| Bracelet | 💍        | U+1F48D    |
| Shirt    | 👕        | U+1F455    |
| Potion   | 🧴        | U+1F9F4    |

The font provides BMP format 4 and full-Unicode format 12 `cmap` subtables.
These characters display the original ROM pixels with the font loaded and a
recognizable Unicode symbol when it is unavailable. No emoji variation selectors
are added. Plain-text domain fallbacks and stored save bytes remain unchanged.
Text remains selectable; item options retain accessible text names.
All text inside `.ff-ui` panels uses the ROM font at the configured scale.
Unsupported characters use the browser's fallback font. Existing plain-text
domain names and PNG endpoints are unchanged.

The encoder follows the [OpenType table specification](https://learn.microsoft.com/en-us/typography/opentype/spec/otff).

Native selects and options inherit the panel font and palette through CSS;
Firefox is the rendering target. Panels use the menu's blue, white, gray and
black palette (NES indices 01, 30, 00 and 0F), using the same conventional 2C02
RGB approximation as the generated PNGs. Sprite transparency is preserved.
