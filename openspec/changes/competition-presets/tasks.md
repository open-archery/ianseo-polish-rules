## 0. Confirm remaining open items before coding

- [x] 0.1 Confirm Masters elimination bracket size (top-104/top-24 like other adult classes, or something narrower) with the domain owner; design.md defaults to top-104/top-24 if unanswered. **Confirmed: top-104/top-24, same as other adult classes.**
- [x] 0.2 Confirm or adjust the proposed Masters class codes (`40M/40W`...`80M/80W`) with the domain owner before wiring them into `ClValidClass` chains and event codes. **Confirmed as proposed.**
- [x] 0.3 Verify live, on the dev install, that the existing `Poland-Full` sub-rule already renders as a `[[Poland-Full]@[pl]@[Install]]` placeholder today (design.md's inference from `get_text()`, not yet browser-confirmed) — informs whether task 6.1's expectation is "already broken, unchanged" or "newly broken, needs a heads-up." **Confirmed live via curl against the dev install (`New=&d_Rule=PL&d_ToType=3`): renders `<b>[[Poland-Full]@[en]@[Install]]</b>`.**
- [x] 0.4 Re-read `.github/agents/research/pzlucz-rules.md` §1.4 (category matrix) and this change's `design.md`/`specs/tournament-setup/spec.md` in full before starting section 1.

## 1. Preset mechanism in lib.php

- [x] 1.1 Add the preset table (sub-rule value → `{divisions?, classes?}`) and a resolver mapping `$subRuleName` to a preset, defaulting to the unfiltered one when empty/unrecognised. `pl_preset_table()`/`pl_resolve_preset()` in `lib.php`.
- [x] 1.2 Apply the filter in `CreateStandardDivisions()`.
- [x] 1.3 Apply the filter in `CreateStandardClasses()`, keeping the existing division-eligibility matrix on top of it; retire the `$KidsOnly` parameter, converting TourType 16's kids round to resolve its fixed preset through the same signature. Structural (TourType 16's candidate list is U12-only regardless of preset content), not a passed-in fixed preset — simpler and equally correct.
- [x] 1.4 Apply the filter in `InsertStandardEvents()` so only events for surviving division/class pairs are created; retire its `$KidsOnly` parameter the same way.
- [x] 1.4a **(discovered during implementation, not in the original scope)** `CreateEventNew()` (core) unconditionally inserts an `Events` row regardless of whether any class is ever bound to it — filtering only the 3 functions above would leave orphaned Events for filtered-out classes, violating the spec's "SHALL NOT create... events outside the preset". Added the same `pl_class_in_preset()` guard to every per-class `CreateEventNew()` loop in `pl_setup_70m_family()`, `pl_setup_1440()` and `pl_setup_indoor()` (see design.md's own note on this, added alongside).
- [x] 1.4b **(discovered during implementation)** To make TourType 1 and 6's own event-creation loops unit-testable at all (a `Setup_*_PL.php` file can't be `require`d directly from PHPUnit — gotchas.md), extracted their bodies into `pl_setup_1440()` and `pl_setup_indoor()` in `lib.php`, mirroring the existing `pl_setup_70m_family()`/`pl_setup_kids_round()` pattern. `Setup_1_PL.php`/`Setup_6_PL.php` are now thin wrappers.
- [x] 1.5 Unit test: the unfiltered preset produces byte-identical divisions/classes/events to this change's new baseline (not the old one — TourType 1 now excludes B and TourType 3/6 now include U12/PU12).
- [x] 1.6 Unit test: a Recurve-only class preset (e.g. `Poland-RU21`) creates the class only in R, not in C/B even where normally eligible.
- [x] 1.7 Unit test: `SetAllClass`/`SetSeniorClass`/`SetYouthClass`/`SetMasterClass` resolve to their documented filters per TourType.
- [x] 1.8 Unit test: events follow the surviving categories, individual and team, for at least one division-restricted and one class-restricted preset.
- [x] 1.9 Unit test: TourType 16 still produces exactly U12M/U12W in R, unaffected by the mechanism swap (regression check for the `$KidsOnly` retirement).

## 2. TourType 1 — division B removal, Compound mirrors Recurve

- [x] 2.1 Remove all Barebow (`B`) division/class/distance/event/target-face creation (in `pl_setup_1440()`, see 1.4b).
- [x] 2.2 Replace Compound's flat 4×50m distance table with Recurve's per-class table (90/70/50/30, 70/60/50/30, 60/50/40/30); keep Compound's 80cm 6-ring target face unchanged.
- [x] 2.3 Register `SetAllClass`, `SetSeniorClass`, `Poland-RU24`, `Poland-RU21`, `Poland-RU18` in `sets.php` for TourType 1.
- [x] 2.4 Unit test: TourType 1's unfiltered default creates only R and C, never B.
- [x] 2.5 Unit test: Compound's distances equal Recurve's distances per class on TourType 1. (Made possible by the 1.4b extraction.)

## 3. Masters age-band classes (TourType 3 only)

- [x] 3.1 Add the 5-band × gender × R/C/B class definitions to `lib.php` (`40M/40W`...`80M/80W`), each self-only `ClValidClass`.
- [x] 3.2 Add per-band distances/target faces to `pl_setup_70m_family()`: R 70m (40-49/50-59) / 60m (60-69) / 50m (70+/80+), all 122cm; C 50m all bands, 80cm 6-ring for 40-49/50-59/60-69 and 80cm full face for 70+/80+; B 50m all bands, 122cm.
- [x] 3.3 Configure elimination (top-104 ind. / top-24 team, per task 0.1) for every Masters class.
- [x] 3.4 Register `SetMasterClass` in `sets.php` for TourType 3 only.
- [x] 3.5 Unit test: Masters preset creates exactly 30 classes (5 bands × 2 genders × 3 divisions).
- [x] 3.6 Unit test: 70+/80+ Compound classes use the full-face target, not the 6-ring face.
- [x] 3.7 Unit test: Masters sub-rule is absent from TourType 1/6/16/37's registered rules (`pl_resolve_preset` test) and from TourType 37's created classes (parity test).
- [x] 3.8 **(discovered during implementation)** `PointsRanking/CupCalc.php`'s age-series cup-title parsing (`pl_cup_split_category`) derives a cup series name from a class code's age prefix — the new `40M`/`50M`/.../`80M` codes parse through this unchanged, so `PL_CUP_AGE_SERIES`/`PL_CUP_DIPLOMA_AGE_SERIES` needed 5 band entries replacing the old flat `'50'` one, or 4 of 5 bands would silently mis-title (see §5 below; folded into task 5.3).

## 4. PU12 and U12-on-TourType-3

- [x] 4.1 Add `PU12M`/`PU12W` class definitions (age 9-12, division R only) to `lib.php`.
- [x] 4.2 Add U12 (2×15m, 122cm, no elimination) and PU12 (2×10m, 122cm, no elimination) to `pl_setup_70m_family()` (TourType 3 only, never 37).
- [x] 4.3 Add PU12 (10m, 122cm, no elimination) to `pl_setup_indoor()` alongside existing U12 (15m, 80cm, unchanged).
- [x] 4.4 Confirmed `pl_setup_70m_family()`'s TourType-37 path does not create U12/PU12/Masters — enforced structurally in `pl_standard_class_candidates()` (`hasU12`/`hasPU12`/`hasMasters` flags exclude 37) and covered by `testPlSetup70mFamilyType37ExcludesU12PU12AndMasters`. **Correction (post-PR code review):** that test only checked `CreateClass()` — the U12/PU12 `CreateDistanceNew()`/`CreateEventNew()` calls (individual and team) were *not* actually TourType-guarded, only preset-guarded, so `pl_class_in_preset()` (which only checks the preset axis) let them through unconditionally on 37 too, leaving orphaned `Events`/`TournamentDistances` rows. Fixed by wrapping all three blocks in `if ($TourType == 3)`, matching the pattern the Masters block already used correctly. Added `testPlSetup70mFamilyType37CreatesNoU12PU12OrMastersEventsOrDistances` (checks events/distances, not just classes) and re-verified live on the dev install (fresh TourType 37 tournament, zero stray events/distances, zero orphans).
- [x] 4.5 Unit test: TourType 3 and 6 each create both U12 and PU12 with their distinct distances; neither has elimination.
- [x] 4.6 Unit test: TourType 16 and 37 never create PU12.

## 5. Remove flat 50M/50W and update dependents

- [x] 5.1 Remove `50M`/`50W` class creation from `lib.php`'s `CreateStandardClasses()`/`InsertStandardEvents()` for every remaining TourType.
- [x] 5.2 Remove the `'50'` mixed-age entries from `$PL_CLASS_NAMES`/`$PL_MIXED_CLASS_NAMES` in `lib.php`; add the new Masters band entries.
- [x] 5.3 Updated `PointsRanking/CupCalc.php`'s two age-series maps: removed the flat `'50'` entry, added `'40'/'50'/'60'/'70'/'80'` band entries (see 3.8) so every band gets a correct cup/diploma title instead of only `'50'` coincidentally half-working; updated `CupCalcTest.php`'s diploma-name assertion accordingly.
- [x] 5.4 `Diplomas/DiplomaSetup.php`'s `case '50M': case '50W':`/`case '50':` branches removed — the `default:` case already returned the identical `['prefix' => '', 'text' => '']`, so no replacement cases were needed (verified: `DiplomaTest.php`'s existing `R50M` assertion still passes unchanged, now via `default:`).
- [x] 5.5 Grepped the whole module for `50M`/`50W`/`'50'` — remaining matches are the new Masters band code and its tests, nothing stale.

## 6. Target face Polish translation

- [x] 6.1 Rewrote all `CreateTargetFace()` labels in `pl_setup_indoor()` (was `Setup_6_PL.php`) to use `Łuk klasyczny`/`Łuk bloczkowy`/`Łuk barebow` instead of `Recurve`/`Compound`/`Barebow`/`Master`/`Senior`.
- [x] 6.2 Same pass on `pl_setup_1440()`/`pl_setup_70m_family()`/`pl_setup_kids_round()`'s remaining English-worded labels.
- [x] 6.3 Applied the same Polish convention to every new target face this change adds (Masters, PU12, łuk popularny).

## 7. sets.php registration and verify

- [x] 7.1 Registered the full sub-rule list from `design.md`'s table for TourTypes 1, 3, 37, 6. Also: renamed `Poland-RU18Only` → `Poland-RU18` (the "Only" suffix was never functionally required — same string, same meaning, on every TourType that has it, consistent with `Poland-RU15`/`Poland-RU24`/`Poland-RU21`'s naming) and switched TourType 16 from `Poland-Full` to `SetAllClass` so its one fixed sub-rule also gets a translated label instead of a placeholder.
- [x] 7.2 Created fresh tournaments on the dev install via a real HTTP POST flow (`curl` — see gotchas.md) and verified via direct DB query, no manual deletion needed:
  - TourType 3, `SetMasterClass`: 10 Masters classes, 60 events (30 ind + 30 team), correct per-band R distances (70/70/60/50/50m) and C target-face split (6-ring 40-49→60-69, full face 70+/80+), elimination on every event, **zero orphaned Events** (`Events LEFT JOIN EventClass` check).
  - TourType 6, default: PU12 at 10m/122cm full face and U12 at 15m/80cm (unchanged) both present as distinct classes, zero orphans.
  - Sub-rule dropdown rendering confirmed live for TourType 1 and 3: `SetAllClass`→"Every Classes", `SetSeniorClass`→"Only senior classes", `SetYouthClass`→"Youth Categories", `SetMasterClass`→"Only master classes" all render translated; every `Poland-*` key renders as the expected `[[key]@[lang]@[Install]]` placeholder, exactly as designed.
- [x] 7.3 Confirmed TourType 1's unfiltered tournament (dev install, `ToId=163`): only R/C divisions created (no B), Compound's per-class distances byte-identical to Recurve's (`CM`/`RM` both 90/70/50/30, `CU18W`/`RU18W` both 60/50/40/30), zero orphaned events.
- [x] 7.4 Ran the full suite via `docker exec` into the app container (`php tools/phpunit.phar`, PHP 8.5.9) — 427 tests, 1654 assertions, 0 failures. Re-ran after the 7.1 renames — still 427/427.
- [x] 7.5 Added `gotchas.md` entries for: the `get_text()`/`Install.php` sub-rule-label dead end (confirmed live), the `CreateEventNew()`-orphans-events discovery (task 1.4a), and the curl/cookie-jar tournament-ID-reuse trap hit while doing 7.2/7.3.
- [x] 7.6 Self-review against the Reviewer agent checklist (`.github/agents/reviewer.prompt.md`): security/scope/conventions/completeness/code-quality all clean. One honest gap on the Testing section: this was implemented as a large structural rewrite first, tests fixed to match after (not strict red-green TDD, given how much of `lib.php`'s shape changed at once) — full suite is green and colocated tests exist/were updated, but the failing-test-first discipline the checklist asks for wasn't followed here.
