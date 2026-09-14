## Why

Two PZŁucz formats have no proper home in the module. The double round (§2.3.1.10.7) is currently faked as the `Poland-4x70m` sub-rule of TourType 3, which reports four qualification sessions on a tournament type ianseo believes has two; ianseo already ships TourType 37 (`Type_2x70mRound`, four distances) for exactly this. The children's round (§2.3.1.10.11) has no setup at all, so a U12-only competition has to be built by creating a full tournament and deleting every other class by hand.

## What Changes

- New `Setup_37_PL.php`: the double round on its native TourType 37 — every class shoots its `Poland-Full` distances twice, 144 arrows in four sessions.
- **BREAKING** `Poland-4x70m` is removed from `sets.php` and from `Setup_3_PL.php`. TourType 3 goes back to a single sub-rule. Tournaments already created under `Poland-4x70m` keep their stored `ToTypeSubRule`, which the module no longer registers; they must not be re-run through setup.
- New `Setup_16_PL.php`: a U12-only round on TourType 16 — the children's round (§2.3.1.10.11 — 18 arrows at each of four distances, 25m/20m/15m/10m, the two longer on 122cm, the two shorter on 80cm), 3-arrow ends per §2.4.1.1. **U15 is not part of this type** — see "Scope change" below.
- `sets.php` registers types 16 and 37 alongside 1, 3 and 6, each with the single `Poland-Full` sub-rule.

## Scope change: TourType 16 dropped from "youth round" to "U12-only round"

The original idea was one TourType 16 tournament covering both U15 and U12 — a genuine "youth round" needing no adult-class deletion. That's not viable: U15's round is 2 distance-legs, U12's is 4, and `Qualification/index.php` (ianseo core) has no per-class leg-count awareness — confirmed by hand-running the scenario on the dev install (see `design.md`, "Scope change: TourType 16 is U12-only"). Both classes ended up listed under every distance selection, with a live editable score box for legs that don't apply to them and no protection against a stray entry corrupting a total.

Fix chosen: TourType 16 becomes **U12-only**. Every class in that tournament uses the same 4 legs, so the mixed-arity problem doesn't arise. U15 keeps using TourType 3/37 exactly as it does today — a genuinely combined U12+U15 event still needs two separate ianseo tournaments, which regains the original manual-deletion pain point for U15's half, but nothing in `regulamin-lucznictwa.md` mandates or forbids using two tournaments for one real-world event; that's purely a tooling boundary, not a rules one.

## Open gaps

Deliberately unresolved — decide before implementing, see `design.md` for detail:

1. ~~The four U12 children's-round distances.~~ **Answered:** 25m, 20m, 15m, 10m (Recurve), 18 arrows each — 72 total, same as every other class — `regulamin-lucznictwa.md` §2.1.2.3.1-2. See `design.md`.
2. ~~Whether U12 Compound exists in the youth round.~~ **Answered:** no — every U12 provision in the regulation is Recurve-only, confirmed by exhaustive check, not assumption. See `design.md`.
3. ~~Whether the youth round has eliminations at all.~~ **Answered:** no — U12 has no elimination format defined anywhere in the regulation. (U15's elimination question is moot for this file now that TourType 16 is U12-only — U15 keeps whatever `Setup_3_PL.php`/`Setup_37_PL.php` already do.) See `design.md`.
4. ~~Whether any live tournament still uses `Poland-4x70m`.~~ **Answered:** one does — `KZLZS26` (Mistrzostwa Krajowego Zrzeszenia LZS), dated 2026-09-26, with no entries and no scores yet. **Waived by the competition owner:** production check skipped, removal proceeds regardless; handled by hand if it also exists there.
5. ~~Whether TourType 16 can host both U15 and U12 in one tournament.~~ **Answered: no** — confirmed by hand-running the mixed-arity scenario on the dev install; see "Scope change" above and `design.md`. TourType 16 is U12-only.

The class and event type gates in `lib.php` (`in_array($TourType, array(3, 6))` for U15, `== 6` for U12) exclude TourType 37 and must be extended for it — see `design.md`. TourType 16 doesn't need U15's gate touched at all, since it never creates U15 classes.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `tournament-setup`: adds TourType 16 (U12-only children's round) and TourType 37 (double round) as supported types; removes the `Poland-4x70m` sub-rule now that TourType 37 covers the format.

## Known limitations

- The public tournament homepage (core `Main.php`) will show a raw `<b>[[Type_2x70mRound]@[pl]@[Tournament]]</b>`-style placeholder for TourType 16/37 tournaments to Polish-language visitors, because `Common/Languages/pl/Tournament.php` lacks translations for these two type names and the real fix is a core file this module can't touch. The admin creation dropdown is patched around this (`sets.php` overrides); the public homepage isn't. See `design.md`.

## Non-goals

- Half rounds (§2.3.1.10.9-10) and combined rounds (§2.3.1.10.8).
- Migrating existing `Poland-4x70m` tournaments to TourType 37.
- Changing elimination, finals or team configuration for any type — only the qualification round differs.
- U15, U18, or any adult class in TourType 16 — it's U12-only (see "Scope change" above).
- A single ianseo tournament covering both U12 and U15 in one youth event — needs two tournaments (TourType 16 + TourType 3/37) instead.

## Impact

- **New files:** `Setup_16_PL.php`, `Setup_37_PL.php`
- **Modified files:** `sets.php`, `Setup_3_PL.php`, `lib.php` (class-set filtering for the U12-only round), `LibTest.php`
- **Spec produced by:** Advisor agent → delta at `openspec/changes/add-setup-types-16-37/specs/tournament-setup/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/add-setup-types-16-37/design.md`
