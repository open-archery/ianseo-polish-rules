## Why

Two PZŁucz formats have no proper home in the module. The double round (§2.3.1.10.7) is currently faked as the `Poland-4x70m` sub-rule of TourType 3, which reports four qualification sessions on a tournament type ianseo believes has two; ianseo already ships TourType 37 (`Type_2x70mRound`, four distances) for exactly this. The children's round (§2.3.1.10.11) has no setup at all, so a youth-only competition for Dzieci and Młodzicy has to be built by creating a full tournament and deleting the adult classes by hand.

## What Changes

- New `Setup_37_PL.php`: the double round on its native TourType 37 — every class shoots its `Poland-Full` distances twice, 144 arrows in four sessions.
- **BREAKING** `Poland-4x70m` is removed from `sets.php` and from `Setup_3_PL.php`. TourType 3 goes back to a single sub-rule. Tournaments already created under `Poland-4x70m` keep their stored `ToTypeSubRule`, which the module no longer registers; they must not be re-run through setup.
- New `Setup_16_PL.php`: a youth-only round on TourType 16, creating U15 and U12 classes only. U15 shoots the 40m/20m round (§2.3.1.10.5 — Recurve 122cm at 40m, 80cm at 20m; Compound 80cm at 40m, 60cm at 20m). U12 shoots the children's round (§2.3.1.10.11 — 18 arrows at each of four distances, the two longer on 122cm, the two shorter on 80cm), 3-arrow ends per §2.4.1.1.
- `sets.php` registers types 16 and 37 alongside 1, 3 and 6, each with the single `Poland-Full` sub-rule.

## Open gaps

Deliberately unresolved — decide before implementing, see `design.md` for detail:

1. ~~The four U12 children's-round distances.~~ **Answered:** 25m, 20m, 15m, 10m (Recurve), 18 arrows each — 72 total, same as every other class — `regulamin-lucznictwa.md` §2.1.2.3.1-2. See `design.md`.
2. ~~Whether U12 Compound exists in the youth round.~~ **Answered:** no — every U12 provision in the regulation is Recurve-only, confirmed by exhaustive check, not assumption. See `design.md`.
3. ~~Whether the youth round has eliminations at all.~~ **Answered:** no, for both classes, but for different reasons — U15 follows existing module precedent (already no-elimination in `Setup_3_PL.php`/`Setup_6_PL.php`, independent of the championship-only exemption in §2.3.1.8); U12 has no elimination format defined anywhere in the regulation at all. See `design.md`.
4. ~~Whether any live tournament still uses `Poland-4x70m`.~~ **Answered:** one does — `KZLZS26` (Mistrzostwa Krajowego Zrzeszenia LZS), dated 2026-09-26, with no entries and no scores yet. It can be recreated as TourType 37 before the event. Repeat the check on production.

The class and event type gates in `lib.php` (`in_array($TourType, array(3, 6))` for U15, `== 6` for U12) exclude both new types and must be extended as part of this change — see `design.md`.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `tournament-setup`: adds TourType 16 (youth-only round) and TourType 37 (double round) as supported types; removes the `Poland-4x70m` sub-rule now that TourType 37 covers the format.

## Known limitations

- The public tournament homepage (core `Main.php`) will show a raw `<b>[[Type_2x70mRound]@[pl]@[Tournament]]</b>`-style placeholder for TourType 16/37 tournaments to Polish-language visitors, because `Common/Languages/pl/Tournament.php` lacks translations for these two type names and the real fix is a core file this module can't touch. The admin creation dropdown is patched around this (`sets.php` overrides); the public homepage isn't. See `design.md`.

## Non-goals

- Half rounds (§2.3.1.10.9-10) and combined rounds (§2.3.1.10.8).
- Migrating existing `Poland-4x70m` tournaments to TourType 37.
- Changing elimination, finals or team configuration for any type — only the qualification round differs.
- U18 or adult classes in the TourType 16 youth round.

## Impact

- **New files:** `Setup_16_PL.php`, `Setup_37_PL.php`
- **Modified files:** `sets.php`, `Setup_3_PL.php`, `lib.php` (class-set filtering for the youth round), `LibTest.php`
- **Spec produced by:** Advisor agent → delta at `openspec/changes/add-setup-types-16-37/specs/tournament-setup/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/add-setup-types-16-37/design.md`
