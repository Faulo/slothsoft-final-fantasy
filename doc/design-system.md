# Final Fantasy I design system

Load `farah://slothsoft@final-fantasy/i/css/default`. The consuming application
owns its HTML, links, form actions, accessible names and game-data selection.
The package owns all FF presentation. Firefox is the native-control rendering
target; no JavaScript is required for typography or controls.

## Public classes

These fourteen classes are the canonical public CSS contract. Except for
`ff-contain`, utilities apply on an `ff-ui` root or within one as described below.
Keep ordinary page headings and introductory prose outside that root.

| Class             | Supported element and behavior                                                                                                                                                                                                                                   |
|-------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `ff-ui`           | Theme root. ROM font, 8px base text, 150% line height, palette, one scaled black pixel text shadow, links and native controls.                                                                                                                                   |
| `ff-panel`        | Root or descendant container. Content-sized game frame, bounded by available width, and 1em padding. Direct fieldset legends receive the same frame, .5em padding and grid-aligned placement. Root panels have 1em block margins; nested panels have no margins. |
| `ff-fit`          | Root or descendant container. Fit-content width bounded by available width. Use for unframed content; panels already fit their contents.                                                                                                                         |
| `ff-contain`      | Unthemed host container. Inline-size containment, zero minimum width and maximum width of 100%. Requires an available width from its host layout; do not use to shrink-wrap intrinsic content.                                                                   |
| `ff-row`          | Root or descendant container. Flex row, vertically centered, .5em gap, no wrapping by default.                                                                                                                                                                   |
| `ff-grid`         | Root or descendant container. Grid, two max-content columns and 1em row/column gaps by default.                                                                                                                                                                  |
| `ff-scroll`       | Root or descendant container. Horizontal scrolling with zero minimum width and optional inner padding. Put inside a framed fieldset so its legend and frame are not clipped.                                                                                     |
| `ff-number`       | `td`. Right-aligned numeric value. Column headings remain centered.                                                                                                                                                                                              |
| `ff-prose`        | Descendant text container or `td`. Wrapping text, maximum width 44ch, paragraphs with zero margins.                                                                                                                                                              |
| `ff-inline-list`  | Descendant `ul` or `ol`. Inline items separated by comma-space, no markers, margin or padding.                                                                                                                                                                   |
| `ff-class-sprite` | Descendant with `role="img"` and an accessible label. One 16x24 class pose, scaled and pixelated. The host supplies the sprite URL.                                                                                                                              |
| `ff-portrait`     | Modifier on `ff-class-sprite`. Shows the top half of the sprite without shrinking its pixels.                                                                                                                                                                    |
| `ff-select`       | Native descendant `select`. Height 1.5em including borders; width is option text plus 3ch for padding/arrow and both borders.                                                                                                                                    |
| `ff-equipped`     | Native descendant `input[type="checkbox"]`. One-em E marker, 1.5em high; unchecked opacity .25. Its real checked state, form submission and keyboard operation are preserved. Supply an accessible control name.                                                 |

Use semantic HTML without additional classes for tables, headings, legends,
links, buttons and text. Tables have intrinsic width, continuous collapsed row
rules and 2ch between columns, with no vertical cell padding. `thead th` is
centered horizontally and vertically in the heading color; `tbody th` stays
left-aligned, white and unbroken. Explicit `br` elements control heading breaks.
Framed panels, legends and native controls fit their contents rather than stretching
to full width. Compact selects retain their explicit option-derived width.
Selects reserve a transparent border when idle; hover, activation, focus, focus
within and an open dropdown reveal the cyan interaction color. Keyboard focus uses that
frame without an additional outline, and control dimensions stay unchanged.
Enabled inputs (including E checkboxes), selects, buttons, textareas and the
native file-selector button use that same color for text and borders during
interaction. Keyboard focus outlines use it too. Unchecked E markers retain
their dimming. File-selector hover/focus follows the owning file input.
Buttons and native file-selector buttons are borderless menu commands. Their
reserved 2em cursor space shows the ROM-derived 16x16 hand on hover, focus or
activation, without shifting text. The hand and cyan text replace the button
focus outline. Disabled commands are dimmed. The native file caption and
selected filename remain browser-owned.
Native options inherit the font and colors. Item names remain plain Unicode
text; the font supplies the ROM glyphs. No icon wrapper is necessary.

## Public inputs and states

Inline styles in consuming templates should contain only these documented
custom properties, rather than additional presentation rules. Lengths in em/ch
follow the local FF scale. Layout properties reset on each row/grid/scroll/prose
utility, preventing a parent's tracks or gaps from leaking into a nested layout.

| Property                          | Set on               | Default / accepted value                                        |
|-----------------------------------|----------------------|-----------------------------------------------------------------|
| `--ff-scale`                      | `ff-ui`              | `1`; positive multiplier. The site uses `2`.                    |
| `--ff-columns`                    | `ff-grid`            | `repeat(2, max-content)`; CSS grid track definition.            |
| `--ff-gap`                        | `ff-row` / `ff-grid` | `.5em` / `1em`; nonnegative CSS length.                         |
| `--ff-row-gap`, `--ff-column-gap` | `ff-grid`            | Local `--ff-gap`; nonnegative CSS length.                       |
| `--ff-wrap`                       | `ff-row`             | `nowrap`; use `wrap` for an upload toolbar.                     |
| `--ff-scroll-padding`             | `ff-scroll`          | `0`; use `.25em` around controls to accommodate focus outlines. |
| `--ff-measure`                    | `ff-prose`           | `44ch`; maximum text width.                                     |
| `--ff-option-width`               | `ff-select`          | `10ch`; longest rendered option length in ch, including glyphs. |
| `--ff-sprite`                     | `ff-class-sprite`    | `none`; `url(...)` of the package's class PNG endpoint.         |
| `--ff-pose`                       | `ff-class-sprite`    | `0`; integer 0-7, inherited from the theme root when omitted.   |

`data-allowed="false"` on a sprite retains the unavailable appearance:
grayscale .5, opacity .5 and brightness .75. This is a visual state, not a
disabled control; describe the permission in `aria-label`. Equipped markers
use their independent native `:checked` state. Keyboard focus remains visible.

The complete named palette remains public. Semantic tokens can be overridden
on the theme root; their defaults preserve the existing colors:

| Token               | Palette default                                      |
|---------------------|------------------------------------------------------|
| `--ff-foreground`   | `--ff-white`                                         |
| `--ff-background`   | `--ff-blue`                                          |
| `--ff-frame`        | `--ff-white`                                         |
| `--ff-frame-shade`  | `--ff-gray`                                          |
| `--ff-frame-shadow` | `--ff-black`                                         |
| `--ff-heading`      | `--ff-pale-blue`                                     |
| `--ff-link`         | `--ff-gold`                                          |
| `--ff-link-visited` | `--ff-ochre`                                         |
| `--ff-interaction`  | `--ff-cyan` (control interaction and focus outlines) |
| `--ff-link-hover`   | `--ff-interaction` (also keyboard focus)             |
| `--ff-link-active`  | `--ff-white`                                         |

The base font/sprite sizes and derived measurements remain available for
compatibility, but new consumers should configure scale rather than calculated
border or sprite dimensions. The black text shadow is independent of frame
colors. Each new `ff-ui` establishes its own theme and scale; use `ff-panel`
without another `ff-ui` for nested frames that inherit the root theme.

## Compositions

- Reference group: `ff-ui ff-panel` on a fieldset, a direct legend and an
  `ff-scroll` child around the table.
- Permission strip: `ff-row` with `--ff-gap: .25em`, containing full or portrait
  sprites. Class order and permission filtering belong to the application.
- Party: `ff-row` with `--ff-wrap: wrap; --ff-gap: 1em`. Fieldsets fill a row
  at their natural width before wrapping, including inside a content-sized form.
- Character: nested `ff-panel` fieldset; `ff-row` inside its legend combines
  name and sprite.
- Equipment: `ff-scroll` with `--ff-scroll-padding: .25em`, containing `ff-grid`
  with `--ff-row-gap: 0`. Four children produce the two-by-two arrangement.
- Slot: `ff-grid` with `--ff-columns: 1em minmax(0, 1fr); --ff-gap: .5em`,
  containing `ff-equipped` and `ff-select` directly.
- Commands: `ff-grid` with `--ff-columns: minmax(0, 1fr); --ff-gap: 0` stacks
  buttons vertically. Use the same layout on an upload form; its first row is an
  `ff-row` with `--ff-wrap: wrap; --ff-gap: 1em`, containing the file input
  followed by its label. The label wraps below the input on narrow screens.
- Collections that should grow horizontally before wrapping: a content-sized
  panel containing `ff-row` with `--ff-wrap: wrap`. An auto-repeat grid inside
  an intrinsically sized parent can instead resolve to a single column. The
  palette example uses wrapping rows with bounded 22em swatches.

See [the standalone examples](../assets/i/examples/panels.xhtml) for references
and editor controls at both 1x and 2x, using only package CSS. The palette
demonstration additionally uses inline styling to visualize the color primitives;
those swatch declarations are not additional utility APIs.

Load package CSS after host styles. The supported host integration is tested
against slothsoft.net's styles; this stylesheet does not promise to override
arbitrary host `!important` declarations. Do not depend on internal selectors,
computed variable names, or utility order in a class attribute.

## Compatibility

The published asset URLs, font/glyph mapping, palette names, sprite pose and
permission state remain unchanged. `ff-table`, `ff-classes`, `ff-class-entry`,
`ff-class-tooltip` and `ff-item-icon` remain as legacy helpers. New compositions
use the fourteen classes above. Existing inline `background-image` sprite
sources still work; new templates use `--ff-sprite`.

This pre-release design refactor intentionally updates default table spacing,
width, heading appearance and panel legends to the established site design.
Legacy helper preservation does not freeze those superseded visual defaults.
