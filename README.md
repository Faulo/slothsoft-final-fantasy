# slothsoft/final-fantasy

A Farah module for Final Fantasy game data and save editing. Currently supports
the original US NES release of Final Fantasy I. Requires PHP 8.2 or later and
`slothsoft/savegame` 1.6 or later.

## Installation and private ROM configuration

Add the Git repository to your application's Composer repositories and require
the stable `slothsoft/final-fantasy` 1.1 release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/Faulo/slothsoft-final-fantasy"}
    ],
    "require": {
        "slothsoft/final-fantasy": "^1.1.0"
    }
}
```

Configure the ROM in your application's bootstrap, after Composer autoloading:

```php
use Slothsoft\FinalFantasy\Configuration;

Configuration::setRomPath('i', '/private/games/Final Fantasy (USA).nes');
```

The path may also point to a ZIP containing exactly one `.nes` file (case
insensitive, including nested entries). Other files in the archive are ignored.
Configuration unpacks the ROM into a managed temporary file and returns that
path from `getRomPath()`; plain ROM paths remain unchanged. Invalid archives,
multiple ROMs and oversized entries are rejected when configuring the path.
The existing US-ROM validation still runs when the game data is loaded.
ZIP support requires PHP's `ext-zip` extension.

The path belongs to the PHP runtime (a container path in DDEV). There is no
default ROM path in the package. Passing `null` clears the configuration. Store
the application's ROM outside public document roots and published assets.
The source repository includes a ROM fixture under `test-files/` for testing;
that directory is excluded from distribution archives. No ROM is bundled in
the published package or selected automatically by production code.
The XML API reports unavailable game data without exposing the configured
filesystem path when the ROM cannot be read.

Only the original US PRG is accepted, SHA-256
`fa456d852372173ea31b192459ba1a2026f779df67793327ba6e132476c1d034`.
iNES headers and optional trainers are handled separately. Japanese releases,
randomizers and other modified PRGs are not supported.

## Module and schema

### Equipment acquisition

Each weapon and armor record exposes `shop-count`, `chest-count` and `npc-count`
in XML (`shopCount`, `chestCount`, `npcCount` in PHP). These count sources per new
playthrough, independently of the user's save. A positive shop count means
unlimited purchases; otherwise add chest and NPC counts to obtain the finite
number of copies. Spells do not expose these attributes.

The savegame manifest reads map tile properties, placed NPCs, treasure rewards,
shop types and inventories, initial flags, and the operands of the dwarf smith's
reward routine. Only shop and chest IDs actually placed on the 61 standard maps
count. Shared chest IDs count once, since opening one placement opens all copies;
unused table records and initially opened chests do not count. The original US
game has 35 weapon and 51 armor chest rewards, plus the smith's single Xcalber.
Monsters do not drop equipment, and the party starts without equipment.

The format follows the original game's `LoadShopInventory`, `OpenTreasureChest`
and `Talk_Smith` routines, documented in the
[FF1 disassembly](https://github.com/Entroper/FF1Disassembly). Offsets are in the
manifest; no ROM tables or names are bundled. The domain attributes are optional
in the schema so existing documents remain valid.

### Maps

`farah://slothsoft@final-fantasy/i/gfx/map?map=world` renders the overworld as an
opaque 4096×4096 PNG. Map IDs `0`–`60` render the original standard maps as
1024×1024 PNGs. `GameData::getMap(string $id)` exposes the same renderer in PHP.
Invalid IDs return HTTP 400; an unavailable ROM returns HTTP 503 without exposing
its path. Layouts, metatile quadrants, NES 2bpp graphics and palette indices come
from `i/savegame/rom.xml`, including the menu font used for shop signs.

These are static terrain atlases: rooms are revealed, chests remain closed, and
NPCs, vehicles and the party are not overlaid. For simultaneous indoor/outdoor
visibility, palette 0 and the dark part of palette 1 use the indoor colors;
wall highlights and outdoor terrain retain their outdoor colors. Full 64×64
layouts are preserved, including unused space and disconnected cave sections.
Names, floor labels, grouping and routes belong to the consuming site.

Use `<img class="ff-pixel-art" ... />` inside an `ff-ui ff-panel` to retain crisp
pixel edges and aspect ratio while fitting the available width. The package's
`i/examples/maps` page includes an example. No ROM-derived images are shipped.

### Assets

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
| `farah://slothsoft@final-fantasy/i/gfx/cursor`            | Transparent 16x16 menu hand from the original ROM                    |

The domain namespace is `http://schema.slothsoft.net/final-fantasy/i`, with prefix
`sf1`. Applications consume the XML data API and own their HTML, page routes,
form destinations and browser behavior. The former `/i/html/*` page fragments,
`/i/xsl/page` and `/i/js/item-icons` have moved into the consuming site as part of
the pre-release package/site separation.

## Utility CSS and host integration

The package provides fourteen composable public classes, semantic HTML defaults
and documented custom-property inputs. Applications can build the reference
tables and native equipment editor without additional CSS. See the
[design-system contract](doc/design-system.md) for the complete API, supported
markup, layout recipes, palette tokens and compatibility helpers.

Load `/slothsoft@final-fantasy/i/css/default` after host styles. Apply `ff-ui`
only to the themed panels, leaving surrounding headings and prose in the host
theme. Combine it with `ff-panel` for a content-sized game frame, bounded by the
available width. Framed controls also fit their contents by default. Legends are framed and aligned automatically. Tables use
continuous row rules, 2ch column spacing, centered pale-blue column headings and
left-aligned body names; use `ff-number` on numeric body cells.

`--ff-scale` defaults to 1. Set `style="--ff-scale: 2"` on a root for 16px text,
24px line height, 48px full sprite height and 2px borders/shadows. `ff-portrait`
crops a class sprite's top half without changing scale. Native `ff-select`
controls take their longest option width in `--ff-option-width`; `ff-equipped`
preserves a real checkbox while showing the game's E marker.

The full 64-entry NES palette is exposed as 55 named color variables, with
duplicate black indices sharing `--ff-black`. Values match the PNG renderer's
conventional 2C02 RGB approximation; they are not RGB values stored in the ROM.
Semantic tokens define foreground, background, frame, heading and link colors.
Links remain gold, ochre when visited, cyan on hover/focus and white when pressed.

The [standalone examples](assets/i/examples/panels.xhtml) demonstrate reference
tables and editor grids at both 1x and 2x, plus uploads and palette swatches.
Examples depend only on package assets; names are illustrative. Actual game
names must come from the XML API. Legacy table, class-list, tooltip and item-icon
helpers remain available but are not needed by new compositions.

For editing, hosts consume `i/api/data?view=editor`. It accepts `ff-action=upload`
with the `ff-save` file, or `ff-action=save`/`ff-action=download` with
`equipment[character][weapons|armor][slot][id|equipped]` fields. Carry the returned
`user` ID in the form URL. One multipart form may contain all controls: the
clicked button supplies `ff-action`, and only upload reads `ff-save`.
The host chooses the form action. Successful downloads return the edited save;
errors and loaded party data are represented in the domain XML.

POST `ff-action=preview` with the same equipment fields and user ID to compute
an unsaved editor state. This uses the exact save validation and stat calculation,
including class-specific rules and byte overflow. The response is the usual XML,
with `sf1:error` on invalid equipment. Preview never creates or writes a stored
save, does not consume uploads, and leaves the disk modification time unchanged.

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
concise lines describing the implemented US effects. Healing descriptions use only
menu values; target restrictions are separate lines and cancellation notes are omitted.
Damage descriptions use "Deals X ... damage". Elements remain
available through each spell's `element-mask`, without a separate description line. `getSpells()` also exposes these
lines as `effectDescription`. The optional `sf1:spell-rules` element supplies
ROM-derived hit-roll constants, the HP threshold and menu healing ranges.
`SpellEffects` interprets parsed values without reading ROM offsets or producing
HTML. See [spell mechanics and extraction sources](doc/spell-effects.md).
The reference pages
describe the original US implementation: weapon critical thresholds use the
weapon IDs, ineffective elemental/enemy-category weapon bonuses are omitted,
and armor resistance masks are decoded. Spell descriptions report actual bugs,
including ineffective LOCK, TMPR, SABR and XFER, beneficial enemy evasion from
LOK2. Raw battle and menu healing values remain available in the XML.

`Save` uses `assets/i/savegame/equipment.xml` to edit four weapon and four armor
slots for each character in an 8192-byte battery save, including equipped flags.
Class permissions, one equipped weapon and one item per armor category are
enforced. Inventory may contain unusable items; unchanged pre-existing invalid
equipment is preserved. Character status, learned spells, party items and
general data are outside the editor scope.

Each character exposes read-only `sf1:stats` (also `getCharacters()['stats']`):
current/max HP, one-based level, strength, agility, intelligence, vitality, luck,
and the saved damage, hit, absorb, evade, resistance and magic-defense values.
The savegame manifest reads these fields; no values are estimated from class or
level. Cached combat stats reflect the saved equipment and update on saving
equipment changes, without adding transient battle buffs. Layout is documented
by the [US RAM map](https://github.com/Entroper/FF1Disassembly/blob/master/Final%20Fantasy%20Disassembly/variables.inc).

No-op exports are byte-identical. Changes update equipment slots, the checksum,
and cached combat stats when the equipped set changes, reproducing original
byte overflow and Black Belt/Master rules. Other SRAM bytes are preserved.
The checksum follows the game's interleaved 6502 carry accumulation and the
result is validated before download. The editor persists validated saves under random user IDs; the legacy base64
source field remains accepted on a first submission for compatibility.
The editor XML also exposes `size` in bytes and, when a stored save exists,
`modified` as its actual filesystem modification time in UTC (`xs:dateTime`).
Opening an existing save does not change this timestamp. Saving, uploading and
downloading write the stored save and update its modification time.

## Development and validation

Use DDEV's PHP runtime (`PHP_VERSION` in `.env`):

```sh
ddev start
ddev composer install
ddev exec vendor/bin/phpunit
ddev exec vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --no-progress --error-format=json
```

Tests use synthetic binary data and catalogs alongside `test-files/ff1-us.zip`,
the original US ROM fixture. Composer's development autoloader selects it automatically
for local tests, Jenkins and GitHub CI. `FINAL_FANTASY_I_ROM` can override its
location inside the test runtime, accepting either a ZIP or a plain ROM. A missing or unreadable fixture fails the
bootstrap rather than silently skipping ROM coverage. Tests still explicitly
exercise unavailable-ROM behavior by clearing the configuration.

Tests cover save round-trips, the manifest extraction pipeline, schema validity
and both Farah asset lookup and resolved output. `test-files/` is excluded from
distribution archives; source checkouts include it. The ROM fixture is original
game data and is not covered by this package's MIT license.

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

The FF editor creates a random user ID on opening a fresh page. Its form actions
and bookmark link retain `?user=...`; revisiting that URL loads the stored save.
Save changes and downloads persist edits, while uploads replace that user's save.
Files live privately under `ServerEnvironment::getDataDirectory()` at
`slothsoft/final-fantasy/i/users`. The URL is the access key; no account is required.
A fresh editor starts with the ROM's default party (Fighter, Thief, Black Belt,
Red Mage), blank names, initial stats, flags, gold and empty inventory. These
initial tables are extracted through the savegame manifest; no save is bundled.

The optional browser contract check is `tests/browser/utilities.cjs`. Run it in
DDEV with Playwright Firefox installed, setting `FF_PLAYWRIGHT_MODULE` to its
module path and `FF_EXAMPLE_URL` to a configured host's package example URL.
`FF_EXAMPLE_SCREENSHOT` optionally captures the rendered examples. This verifies
the standalone utility contract without relying on the site's stylesheet.
