## 0. Confirm remaining open items before coding

- [ ] 0.1 Confirm Masters elimination bracket size (top-104/top-24 like other adult classes, or something narrower) with the domain owner; design.md defaults to top-104/top-24 if unanswered.
- [ ] 0.2 Confirm or adjust the proposed Masters class codes (`40M/40W`...`80M/80W`) with the domain owner before wiring them into `ClValidClass` chains and event codes.
- [ ] 0.3 Verify live, on the dev install, that the existing `Poland-Full` sub-rule already renders as a `[[Poland-Full]@[pl]@[Install]]` placeholder today (design.md's inference from `get_text()`, not yet browser-confirmed) — informs whether task 6.1's expectation is "already broken, unchanged" or "newly broken, needs a heads-up."
- [ ] 0.4 Re-read `.github/agents/research/pzlucz-rules.md` §1.4 (category matrix) and this change's `design.md`/`specs/tournament-setup/spec.md` in full before starting section 1.

## 1. Preset mechanism in lib.php

- [ ] 1.1 Add the preset table (sub-rule value → `{divisions?, classes?}`) and a resolver mapping `$subRuleName` to a preset, defaulting to the unfiltered one when empty/unrecognised.
- [ ] 1.2 Apply the filter in `CreateStandardDivisions()`.
- [ ] 1.3 Apply the filter in `CreateStandardClasses()`, keeping the existing division-eligibility matrix on top of it; retire the `$KidsOnly` parameter, converting TourType 16's kids round to resolve its fixed preset through the same signature.
- [ ] 1.4 Apply the filter in `InsertStandardEvents()` so only events for surviving division/class pairs are created; retire its `$KidsOnly` parameter the same way.
- [ ] 1.5 Unit test: the unfiltered preset produces byte-identical divisions/classes/events to this change's new baseline (not the old one — TourType 1 now excludes B and TourType 3/6 now include U12/PU12).
- [ ] 1.6 Unit test: a Recurve-only class preset (e.g. `Poland-RU21`) creates the class only in R, not in C/B even where normally eligible.
- [ ] 1.7 Unit test: `SetAllClass`/`SetSeniorClass`/`SetYouthClass`/`SetMasterClass` resolve to their documented filters per TourType.
- [ ] 1.8 Unit test: events follow the surviving categories, individual and team, for at least one division-restricted and one class-restricted preset.
- [ ] 1.9 Unit test: TourType 16 still produces exactly U12M/U12W in R, unaffected by the mechanism swap (regression check for the `$KidsOnly` retirement).

## 2. TourType 1 — division B removal, Compound mirrors Recurve

- [ ] 2.1 `Setup_1_PL.php`: remove all Barebow (`B`) division/class/distance/event/target-face creation.
- [ ] 2.2 `Setup_1_PL.php`: replace Compound's flat 4×50m distance table with Recurve's per-class table (90/70/50/30, 70/60/50/30, 60/50/40/30 as documented in spec.md); keep Compound's 80cm 6-ring target face.
- [ ] 2.3 Register `SetAllClass`, `SetSeniorClass`, `Poland-RU24`, `Poland-RU21`, `Poland-RU18` in `sets.php` for TourType 1.
- [ ] 2.4 Unit test: TourType 1's unfiltered default creates only R and C, never B.
- [ ] 2.5 Unit test: Compound's distances equal Recurve's distances per class on TourType 1.

## 3. Masters age-band classes (TourType 3 only)

- [ ] 3.1 Add the 5-band × gender × R/C/B class definitions to `lib.php` (codes per task 0.2), each self-only `ClValidClass`.
- [ ] 3.2 Add per-band distances/target faces to `Setup_3_PL.php`'s distance/event setup: R 70m (40-49/50-59) / 60m (60-69) / 50m (70+/80+), all 122cm; C 50m all bands, 80cm 6-ring for 40-49/50-59/60-69 and 80cm full face for 70+/80+; B 50m all bands, 122cm.
- [ ] 3.3 Configure elimination for every Masters class per task 0.1's confirmed bracket size.
- [ ] 3.4 Register `SetMasterClass` in `sets.php` for TourType 3 only.
- [ ] 3.5 Unit test: Masters preset creates exactly 30 classes (5 bands × 2 genders × 3 divisions).
- [ ] 3.6 Unit test: 70+/80+ Compound classes use the full-face target, not the 6-ring face.
- [ ] 3.7 Unit test: Masters sub-rule is absent from TourType 1/6/16/37's registered rules.

## 4. PU12 and U12-on-TourType-3

- [ ] 4.1 Add `PU12M`/`PU12W` class definitions (age 9-12, division R only) to `lib.php`.
- [ ] 4.2 `Setup_3_PL.php`: add U12 (2×15m, 122cm, no elimination) and PU12 (2×10m, 122cm, no elimination).
- [ ] 4.3 `Setup_6_PL.php`: add PU12 (10m, 122cm, no elimination) alongside existing U12 (15m, 80cm, unchanged).
- [ ] 4.4 Confirm `Setup_37_PL.php`/`pl_setup_70m_family`'s TourType-37 path does not create U12/PU12/Masters (explicit exclusion, not an accidental inheritance from TourType 3's shared body).
- [ ] 4.5 Unit test: TourType 3 and 6 each create both U12 and PU12 with their distinct distances; neither has elimination.
- [ ] 4.6 Unit test: TourType 16 and 37 never create PU12.

## 5. Remove flat 50M/50W and update dependents

- [ ] 5.1 Remove `50M`/`50W` class creation from `lib.php`'s `CreateStandardClasses()`/`InsertStandardEvents()` for every remaining TourType (1 already covered by §2, 3/6/37 here).
- [ ] 5.2 Remove the `'50'` mixed-age entries from `$PL_CLASS_NAMES`/`$PL_MIXED_CLASS_NAMES` in `lib.php`; add the new Masters band entries (M/W name pairs per band).
- [ ] 5.3 Update `PointsRanking/CupCalc.php`'s two `'50'`-keyed ranking-label maps — remove the flat entry, decide whether Masters bands need ranking labels (likely out of scope if Masters isn't part of the Puchar Polski cup structure — confirm) and update `CupCalcTest.php` accordingly.
- [ ] 5.4 Update `Diplomas/DiplomaSetup.php`'s `case '50M': case '50W':` diploma-title branch — remove or replace per whether Masters diplomas are in scope (check with domain owner if unclear); update `DiplomaTest.php`.
- [ ] 5.5 Grep the whole module for any other `50M`/`50W`/`'50'` reference missed by the above and resolve it.

## 6. Target face Polish translation

- [ ] 6.1 `Setup_6_PL.php`: rewrite all 8 `CreateTargetFace()` labels to use `Łuk klasyczny`/`Łuk bloczkowy`/`Łuk barebow` instead of `Recurve`/`Compound`/`Barebow`/`Master`/`Senior`.
- [ ] 6.2 `Setup_1_PL.php`, `lib.php`: same pass on the remaining English-worded labels (`'Recurve domyślna'` etc. → `'Łuk klasyczny domyślna'` etc.).
- [ ] 6.3 Apply the same Polish convention to every new target face this change adds (Masters, PU12).

## 7. sets.php registration and verify

- [ ] 7.1 Register the full sub-rule list from `design.md`'s table for TourTypes 3, 37, 6 (1 already covered by §2.3).
- [ ] 7.2 Create a fresh tournament per sub-rule on the dev install (all TourTypes); confirm the category list matches design.md's table and nothing needs deleting by hand. Never re-run setup on an existing competition.
- [ ] 7.3 Confirm TourType 1's `Poland-Full`-equivalent tournament has no Barebow division and Compound distances matching Recurve, by inspection on the dev install.
- [ ] 7.4 Run `tools/test.sh` (or `tools/test.cmd`); full suite passes.
- [ ] 7.5 Add a `gotchas.md` entry for the `get_text()`/`Install.php` sub-rule-label dead end if task 0.3 confirms it live and it isn't already documented there.
- [ ] 7.6 Self-review against the Reviewer agent checklist, then commit.
