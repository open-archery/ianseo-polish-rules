## Why

Every PZŁucz competition uses a subset of the categories `Poland-Full` creates — an organiser deletes divisions/classes/events by hand before every event. ianseo's sub-rule dropdown (§ n/a — ianseo mechanism, not a PZŁucz rule) already exists for exactly this and sits unused beyond one entry. Separately, three category-taxonomy gaps surfaced while scoping this: the flat "Master 50+" class (§1.2.1/1.2.7) is not how PZŁucz Masters competitions are actually run (five age bands, §-unreferenced organiser-supplied table), U12 has an unregulated equipment split ("łuk popularny", not named in `regulamin-lucznictwa.md`), and the merged `add-setup-types-16-37` change introduced a one-off `$KidsOnly` filter instead of a reusable mechanism. Supersedes the abandoned `class-presets` proposal (PR #44, closed) — that one scoped only the selection mechanism with competition-named presets; this folds in the taxonomy changes it didn't anticipate and switches to abstract naming.

## What Changes

- `sets.php` registers abstract-named sub-rules per TourType (reusing ianseo's own translated generic keys — `SetAllClass`, `SetSeniorClass`, `SetYouthClass`, `SetMasterClass` — where they fit; inventing new keys, untranslated by ianseo core, where they don't).
- `lib.php` gains a `{divisions?, classes?}` preset resolver consumed by `CreateStandardDivisions`/`CreateStandardClasses`/`InsertStandardEvents`, replacing `$KidsOnly`.
- **BREAKING**: flat `50M`/`50W` removed from every TourType (1, 3, 6, 37) and from `Poland-Full`.
- New Masters class family: 5 age bands (40-49/50-59/60-69/70+/80+) × R/C/B, own sub-rule, TourType 3 only, with elimination.
- New `PU12M`/`PU12W` class (simplified recurve, same 9-12 bracket as U12) — TourType 3 and 6 only.
- `U12M`/`U12W` extended to TourType 3 (previously TourType 6 and 16 only).
- **BREAKING**: TourType 1 drops division B (Barebow) entirely; division C's distances change from flat 4×50m to mirroring R's per-class table.
- Target face labels translated to this module's Polish convention (`Łuk klasyczny/bloczkowy/barebow`) across `Setup_1_PL.php`, `Setup_6_PL.php`, `lib.php`.

## Capabilities

### Modified Capabilities

- `tournament-setup`: setup scripts create a filtered subset of divisions/classes/events per selected sub-rule; TourType 1 loses division B and gains R-matching C distances; new Masters and PU12 classes; flat Master class removed.

## Non-goals

- A UI for creating/editing presets (fixed list in the module, same as before).
- Masters or PU12 support in TourType 1 or 16 (no data supplied for those).
- Fixing the `get_text()`/`Install.php` translation gap upstream — accepted as a known limitation; some sub-rules render untranslated.
- Retrofitting existing tournaments.

## Impact

- **Modified:** `sets.php`, `lib.php`, `Setup_1_PL.php`, `Setup_3_PL.php`, `Setup_6_PL.php`, `LibTest.php`
- **Touched for Masters-code removal/rename:** `PointsRanking/CupCalc.php`, `PointsRanking/CupCalcTest.php`, `Diplomas/DiplomaSetup.php`, `Diplomas/DiplomaTest.php`
- **DB:** none
- **Spec:** Advisor agent → `specs/tournament-setup/spec.md` delta. **Design:** Developer agent → `design.md`.
