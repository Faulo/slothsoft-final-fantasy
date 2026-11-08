# Changelog

## Unreleased

- Expose save size and the server file's last-modified time in editor XML, without updating timestamps when viewing a save.

- Add `--ff-align` for row alignment and `--ff-max-width` for bounded content sizing, with a native form-associated menu example. Leave spacing around nested themed panels to their parent layout.

- Render buttons and native file-selector buttons as borderless menu commands with the original ROM hand on hover/focus and cyan text. Demonstrate vertical command lists and horizontally wrapping party fieldsets in content-sized panels.

- Share the cyan interaction token across links, native controls, E markers, file-selector buttons and keyboard focus outlines. Preserve idle select transparency and unchecked marker dimming.

- Hide select frames while idle and reveal them on hover, activation, focus and open-dropdown states without resizing controls. Let the palette example grow horizontally before wrapping.

- Size framed panels, legends and native controls to their contents by default, bounded by available width; preserve option-derived compact select sizing.

- Establish fourteen public design-system utilities, configurable layouts and semantic color tokens. Consolidate the established site table and legend design into package defaults; demonstrate and verify references and native editor controls at both scales. Retain published asset URLs and legacy CSS helpers.

- Fix level-8 PHPStan diagnostics with checked XML and binary reads, explicit equipment shapes and typed test providers; retain the existing analysis configuration.

- Add private user-ID save persistence and ROM-derived new-game saves, with optional uploads.

- Shorten WARP and EXIT descriptions to their destinations.

- Shorten spell descriptions to effect and target, using only menu healing ranges. Use "Deals X ... damage", omit cancellation notes and keep target restrictions on separate lines; expose elements through the existing mask instead of standalone element notes. Retain raw battle values and rules in the XML API.

- Describe all US spell effects in domain XML, using ROM power, accuracy, targets, elements, status masks, battle constants and separate menu-healing operands. Document implemented spell bugs and expose descriptions through `getSpells()`.

- Give all panel text an opaque black, unblurred shadow offset one pixel right and down at the configured scale, inherited by text and controls.

- Use palette colors instead of underlines for panel links: gold, ochre (the Black Mage hat's darker shade, NES $18) when visited, cyan on hover or keyboard focus, and white while active. Preserve keyboard focus outlines.

- Expose the renderer's complete NES color palette as 55 descriptively named CSS variables in `.ff-ui`, preserving the original four colors. Include palette swatches in the standalone example and verify CSS colors against generated PNG palettes.

- Move application HTML, navigation, form markup and browser behavior into the consuming site. Replace the pre-release HTML page assets with standalone utility-CSS examples at `/i/examples/panels`.
- Scope FF typography and white-on-blue colors to explicit panels. Scale the 8px font, 24px sprite height, spacing and font-relative borders with a shared multiplier.

- Render reference-table item names as plain Unicode text without inline icon wrappers; the ROM font supplies their original pictograms.

- Correct the equipment-update input contract for static analysis while preserving rejection of malformed nested input.

- Map the thirteen item pictograms to the selected Unicode symbols in rendered names and the ROM font, with full-Unicode font support and preserved private-use aliases.

- Style native Firefox equipment selects with the ROM font using the Ambermoon approach; remove the JavaScript replacement picker.
- Give panels the original menu palette, outlined borders and tile-based spacing; preserve sprite transparency.

- Generate a TrueType menu font from the private ROM and use it for game-derived names, class tooltips and equipment options.

- Extract item-name glyphs and menu colors from the ROM into an on-demand PNG sheet.
- Render inline item icons in reference tables and selected equipment previews, preserving text fallbacks in domain XML.

- Decode ROM class tiles, palettes and pose arrangements into transparent PNG sprite sheets on demand.
- Show all twelve class sprites in weapon, armor and spell tables, dimming classes without permission.

- Use the `sf1` namespace prefix throughout Final Fantasy I domain XML, schema metadata and XPath expressions.
- Publish Final Fantasy I navigation and form actions under `/FinalFantasy/I/`.
- Add schema versioning metadata to the Final Fantasy I domain schema.

- Extract the Final Fantasy I US module from server-slothsoft.net into `slothsoft/final-fantasy`.
- Read ROM game data through savegame manifests and expose the `http://schema.slothsoft.net/final-fantasy/i` domain schema.
- Configure private ROM locations through the package configuration API; distribute no game binaries.
- Provide ROM-backed equipment editing and weapon, armor, and spell reference assets.
- Use savegame 1.6 scoped variable resolution for repeated name and spell-permission pointers, replacing numbered pointer declarations. Require savegame `dev-main` until that release is tagged.
