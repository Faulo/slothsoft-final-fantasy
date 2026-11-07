# Changelog

## Unreleased

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
