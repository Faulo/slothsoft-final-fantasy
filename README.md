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

The domain namespace is `http://schema.slothsoft.net/final-fantasy/i`, with prefix `sf1`. Hosts
embed the HTML fragments and provide `/FinalFantasy/I/`,
`/FinalFantasy/I/SaveEditor/`, `/FinalFantasy/I/Weapons/`, `/FinalFantasy/I/Armor/`
and `/FinalFantasy/I/Spells/` routes used by the navigation.

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
case, come from the ROM; equipment glyphs are rendered as bracketed labels.
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
