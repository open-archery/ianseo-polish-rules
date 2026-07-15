## Why

Polish archery tournaments run 3 archers per boss with a staggered ABC/ACD alternating pattern — odd-numbered bosses use positions A, B, C while even-numbered bosses use A, C, D — so that each boss has one shooting wave with a solo archer and archers on adjacent bosses are never fully packed side-by-side. ianseo's built-in auto-assignment (`Partecipants/SetTarget_auto.php`) always assigns A, B, C for 3-archer sessions and cannot be modified (ianseo core is read-only). Tournament organisers currently have no automated way to apply this pattern.

## What Changes

- New target assignment page in the PL module (`Targets/SetTargetABCACD.php`) that assigns archers to the ABC/ACD alternating slot pattern.
- Menu entry added under **Participants** (`PART`) in `menu.php`.
- Assignment logic: club grouping (one club per boss position, clubs assigned to consecutive bosses), deterministic largest-club-first ordering, randomised within each club.
- Session must be configured with `SesAth4Target = 4`; the empty fourth position per boss (D for odd bosses, B for even) is left unassigned.
- Preview mode shows proposed assignment before it is saved; save mode erases any existing assignment for the selected class and writes fresh.

## Capabilities

### New Capabilities

- `abc-acd-target-assignment`: Auto-assign archers of one division/class to a target range using the staggered ABC/ACD alternating boss pattern, with club grouping that keeps club members on adjacent bosses (max one per boss).

### Modified Capabilities

_(none)_

## Non-goals

- Modifying ianseo core (`Partecipants/SetTarget_auto.php`) — this module is read-only.
- Replacing ianseo's general-purpose auto-assignment for non-Polish or non-staggered setups.
- Handling wheelchair / double-space athletes (those remain assigned manually via ianseo's existing tool).
- Grouping by country (international events) — `EnCountry` in domestic PZŁucz tournaments represents the club.

## Impact

- **New files:** `Modules/Sets/PL/Targets/SetTargetABCACD.php`
- **Modified files:** `Modules/Sets/PL/menu.php`
- **ianseo DB writes:** `Qualifications.QuTarget`, `Qualifications.QuLetter`, `Entries.EnMainInfoUpdate`, `Entries.EnTimestamp` — same fields as ianseo's own assignment page.
- **No new DB tables.**
- **Spec produced by:** Advisor agent → `openspec/specs/abc-acd-target-assignment/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/abc-acd-target-assignment/design.md`
