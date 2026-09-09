## Why

Every PZŁucz competition uses a subset of the categories the module creates. A `Poland-Full` tournament creates every division and class, so the organiser then deletes by hand what the competition does not use — before every single event. ianseo already has the mechanism for this: the sub-rule dropdown next to the rule set on the tournament-creation form, which FITA uses for `SetAllClass` / `SetOneClass` / `SetYouthClass` and ianseo's own PL set uses for `SetOneClass` / `SetAllClass` / `SetYouthClass` / `SetKidClass`. This module registers only `Poland-Full`, so the dropdown offers nothing useful.

## What Changes

- `sets.php` registers named presets per TourType, alongside `Poland-Full`.
- `CreateStandardClasses()` gains a preset parameter and creates only the classes the preset names. `InsertStandardEvents()` follows it, creating only the individual and team events those classes compete in — nothing left over to delete.
- `Poland-Full` remains the default and its output is unchanged.
- Each preset is a hardcoded list in the module. Adding one is a code change; there is no preset-editing UI.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `tournament-setup`: setup scripts create a subset of classes and their events according to the selected sub-rule, instead of always creating the full set.

## Open gaps

1. **Which presets to ship.** The competition owner supplies the list. Candidates from the competitions already in the dev database: Puchar Polski Juniorów i Juniorów Młodszych (U21 + U18), Puchar Polski Łuków Bloczkowych i Barebow, Mistrzostwa Krajowego Zrzeszenia LZS, a youth event (U15 + U12), a masters event (50+).
2. **Presets need a division axis, not just classes.** "Puchar Polski Łuków Bloczkowych i Barebow" selects divisions C and B with all their classes — the opposite cut from a youth preset. The parameter has to express both, or two parameters are needed.
3. Whether a preset also trims target faces, or leaves the full set (assumed: leaves them).
4. Whether presets should ever be editable by an operator rather than hardcoded — deferred, not designed for.
5. Overlap with `add-setup-types-16-37`, which needs the same class filter for its youth round. Whichever lands first builds the mechanism.

## Non-goals

- A UI for creating or editing presets.
- Changing which classes exist in `Poland-Full`.
- Retrofitting presets onto tournaments that already exist.
- Filtering entries or participants — this only affects what setup creates.

## Impact

- **Modified files:** `sets.php`, `lib.php` (`CreateStandardClasses`, `InsertStandardEvents`), `Setup_1_PL.php`, `Setup_3_PL.php`, `Setup_6_PL.php`, `LibTest.php`
- **DB:** none
- **Spec produced by:** Advisor agent → delta at `openspec/changes/class-presets/specs/tournament-setup/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/class-presets/design.md`
