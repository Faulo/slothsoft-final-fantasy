# Original US spell effects

The savegame manifest reads each eight-byte spell record at PRG offset `0x301E0`.
Its first five bytes are accuracy, effectivity, element mask, target mask and
effect routine ID. `SpellEffects` interprets these fields, rather than maintaining
a list of damage values indexed by spell name. Status and element labels name
the corresponding bits. Spell names remain ROM data.

The manifest also reads immediate operands from the original code:

| PRG position                       | Value used                                 |
|------------------------------------|--------------------------------------------|
| `0x33874`, `0x33879`               | Base hit threshold, low and high bytes     |
| `0x3386F`                          | Elemental weakness hit bonus               |
| `0x33A86`                          | Maximum magic hit roll                     |
| `0x33AE0`, `0x33AE5`               | HP limit for effect 18, low and high bytes |
| `0x3AF5F`, stride 8, three records | CURE family menu random mask and base      |
| `0x3AFDF`, stride 9, three records | HEAL family menu random mask and base      |
| `0x3B02F`                          | LIFE revival HP                            |

Offsets exclude the iNES header. The normal US PRG hash validation still applies.
No assembly or ROM bytes are distributed. Utility descriptions are English
interpretations of the fixed US dispatch routines, keyed by spell ID rather than
translated names.

## Battle behavior

Damage starts at effectivity plus a random value from zero through effectivity.
Resistance first halves the base, rounding down. Weakness then adds half of the
remaining base, rounding down. HARM-family damage requires an undead target and
ignores elemental modifiers. Damage always connects against an eligible target;
the success roll instead doubles the damage.

The threshold starts at the extracted base plus the spell's accuracy. Elemental
resistance replaces the base with zero; weakness adds the extracted bonus.
Subtract Magic Defense, clamping at zero. A roll less than or equal to this
threshold succeeds, except the maximum roll always fails. The same rule governs
ordinary status spells, SLOW and FEAR. The raw `hit` field is an accuracy bonus,
not a fixed success percentage. For damage spells it affects double damage, not
whether damage connects; effects without a hit roll ignore it. `RandAX` scales a random
byte into the roll range, so the 201 possible roll values are not equiprobable.

Effect 18 instead requires current HP **at most** the extracted limit and no
matching elemental resistance. This inclusive comparison is verified from
`MathBuf_Compare`, rather than the disassembly's informal "under 300" comment.

Healing uses effectivity through twice effectivity, with byte saturation at 255,
and caps recovered HP at maximum HP. Menu healing is separate code: CURE-family
ranges use a frame-counter mask and OR; HEAL-family ranges use a mask and addition.
The US base and mask bits do not overlap, so both maxima are base plus mask.
HEL2 and HEL3 have the same battle power in the actual table; their menu values differ.

LOCK always misses because of an unconditional jump. TMPR and SABR fail to save
attack changes back to party stats, and do not increase accuracy. LOK2 uses the
evasion-increase routine, benefiting enemies. XFER does not persist enemy
resistance changes. CUR4 clears ailments in battle, but dead/stone allies cannot
be selected; outside battle it restores HP without clearing ailments.

## Sources

Primary reference: [Disch's US disassembly, maintained by Entroper](https://github.com/Entroper/FF1Disassembly).
The implementation was checked against the configured US ROM operands.

- [bank 0C](https://github.com/Entroper/FF1Disassembly/blob/master/Final%20Fantasy%20Disassembly/bank_0C.asm):
  `Battle_PrepareMagic`, `Battle_PlayerMagic_CastOnTarget`, `BtlMag_PerformSpellEffect`,
  all `BtlMag_Effect_*` handlers, `BtlMag_PrepHitAndDamage`, `MathBuf_Compare`,
  `RandAX`, and player/enemy defender stat loading and saving.
- [bank 0E](https://github.com/Entroper/FF1Disassembly/blob/master/Final%20Fantasy%20Disassembly/bank_0E.asm):
  `UseMagic_CURE` through `UseMagic_EXIT` for menu healing, revival and utility behavior.
- [Constants.inc](https://github.com/Entroper/FF1Disassembly/blob/master/Final%20Fantasy%20Disassembly/Constants.inc):
  record layout, target flags and ailment bits.
