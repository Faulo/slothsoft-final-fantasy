# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-11-08

### Fixed

- Nested `ff-ui` roots now keep their own font size, line height and text shadow
  when their scale differs from the surrounding layout.

## [1.1.0] - 2026-11-08

### Added

- ZIP ROM configuration: a single `.nes` entry is unpacked to a managed temporary
  file, preserving support for plain ROM paths. Requires PHP's `ext-zip`.
- The original US ROM as a source-only test fixture, loaded automatically by
  PHPUnit in local and CI runs; `test-files/` remains excluded from distributions.
- ROM-derived `shop-count`, `chest-count` and `npc-count` for weapons and armor.
  Counts include placed shops and obtainable chest rewards, deduplicate shared
  chest flags, and include the dwarf smith's one-time weapon reward.
- On-demand PNGs for the world map and all 61 standard US maps, decoded from
  layouts, metatiles, graphics and palettes extracted by the savegame manifest.
  Static atlas views reveal rooms and leave chests closed.
- The `i/gfx/map?map=world` and `i/gfx/map?map=0`–`60` assets, `GameData::getMap()`
  and the responsive `ff-pixel-art` image utility.
- The `ff-flush` panel modifier for edge-to-edge images with an overlaid legend.

## [1.0.0] - 2026-11-08

### Added

- A reusable Farah module for the original US NES release of Final Fantasy I,
  with the `http://schema.slothsoft.net/final-fantasy/i` domain schema and `sf1`
  namespace prefix.
- ROM and SRAM extraction through savegame manifests, including scoped variable
  resolution for repeated name and spell-permission pointers. Requires
  `slothsoft/savegame` 1.6 or later.
- Private ROM configuration through the package API. No ROMs, saves or extracted
  copyrighted game assets are included in the distribution.
- ROM-derived weapon, armor and spell names, statistics, class permissions,
  equipment spell casts and implemented battle effects. Spell descriptions use
  raw ROM values and menu healing ranges, while the XML API preserves battle
  constants, accuracy, elements, targets, status masks and implemented bugs.
- Equipment editing with four weapon and armor slots per character, equipped
  flags, validation, checksum updates and recalculated combat statistics.
- Private save persistence with bookmarkable user IDs, optional uploads and
  ROM-derived new-game saves. Editor XML exposes file size and modification time,
  character names, classes, levels, HP, attributes and cached combat statistics.
- A read-only equipment preview action that calculates combat statistics without
  writing the save or changing its disk timestamp.
- On-demand transparent PNG class sprite sheets, item pictograms and the menu
  hand cursor, decoded from the private ROM.
- A ROM-derived TrueType menu font, including thirteen Unicode item pictograms
  and private-use aliases. Native Firefox equipment options use the same font.
- Fourteen public CSS utilities for panels, legends, tables, layout, sprites,
  numbers and native editor controls, plus configurable scales and semantic color
  tokens. The design uses the original white-on-blue menus, scaled black text
  shadows, content-sized frames and a complete palette of 55 named NES colors.
- Native menu commands with overlaid hand cursors, cyan widget interaction states,
  E checkbox markers, compact equipment selects and palette-colored links.
- Standalone utility examples at `/i/examples/panels`. Application markup,
  navigation, form actions and browser behavior belong to the consuming site.
- PHPStan level 8 validation, cross-platform PHP integration tests and browser
  checks for the utility contract, with optional private-ROM integration tests.

[Unreleased]: https://github.com/Faulo/slothsoft-final-fantasy/compare/1.1.1...HEAD
[1.1.1]: https://github.com/Faulo/slothsoft-final-fantasy/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/Faulo/slothsoft-final-fantasy/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/Faulo/slothsoft-final-fantasy/releases/tag/1.0.0
