## 0. Close the open gaps

- [x] 0.1 Read `.github/agents/research/pzlucz-rules.md`, `ianseo-internals.md` §4-§6, `openspec/specs/tournament-setup/spec.md`, and this change's `design.md`.
- [x] 0.2 U12 children's-round distances (gap #1): 25m, 20m, 15m, 10m (Recurve), per `regulamin-lucznictwa.md` §2.1.2.3.1-2 — see `design.md`. No need to ask the competition owner.
- [x] 0.3 U12 is Recurve-only (gap #2): every U12 provision in `regulamin-lucznictwa.md` is paired with "łuków klasycznych"; none with Compound. See `design.md`.
- [x] 0.4 Children's round is qualification-only (gap #3): U12 has no elimination format defined anywhere in the regulation. See `design.md`.
- [x] 0.5 Check the install for tournaments with `ToTypeSubRule = 'Poland-4x70m'` (gap #4). Dev install: one, `KZLZS26` (2026-09-26, no entries, no scores) — recreate it as TourType 37 before the event.
- [x] 0.6 Waived by the competition owner (2026-09-14): production check skipped, `Poland-4x70m` removal proceeds regardless of what's live there. Any production tournament still on `Poland-4x70m` (including `KZLZS26` if it also exists in prod) will be handled by hand outside this change.

## 1. Shared session-multiplier helper

- [x] 1.1 Add `pl_setup_70m_family($TourId, $TourType, $Multiplier, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES)` to `lib.php`: explicit `$TourId`/`$TourType`/class-name-table params (verified not true globals — see `design.md`), `global $tourDetGolds, $tourDetXNine, $tourDetGoldsChars, $tourDetXNineChars;` at the top (these ARE true globals, confirmed against `GetSetupFile()`), `$DistanceInfoArray` computed from `$Multiplier` internally. Move `pl_double_legs()` into `lib.php` as its own top-level function (remove it from `Setup_3_PL.php` — leaving both copies loaded is a redeclaration fatal).
- [x] 1.2 Move everything between "Divisions & Classes" and "Tour Update" out of `Setup_3_PL.php` and into the helper. Rewrite `Setup_3_PL.php` down to `$TourType`, the `$tourDetXxx` assignments, the `require_once`s, one call to `pl_setup_70m_family($TourId, 3, 1, $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES)`, and the `$tourDetails`/`UpdateTourDetails()` call. Drop `$isDouble` and every branch on it.
- [x] 1.3 Unit tests: multiplier 1 reproduces today's `Setup_3_PL.php` distances, sessions and events exactly. `testPlSetup70mFamilyMultiplierOneMatchesSingleDistanceRound` in `LibTest.php`.

## 2. Setup_37_PL.php

- [x] 2.1 Add `Setup_37_PL.php` calling the helper with multiplier 2, `$tourDetTypeName = 'Type_2x70mRound'`, `$tourDetNumDist = 4`, `$tourDetNumEnds = 24`, `$tourDetDouble = '1'` (see `design.md` — this drives the results-PDF pairing layout, not cosmetic).
- [x] 2.2 Unit tests: every class gets 4 sessions, U15 order is 40m/40m/20m/20m — `testPlSetup70mFamilyMultiplierTwoDoublesEverySession`. `tourDetMaxDistScore`/`tourDetDouble` are static one-line assignments in `Setup_37_PL.php` with no branching logic (verified by direct reading: `360` and `'1'`); not unit-tested since `Setup_37_PL.php` itself can't be safely `require`d in a test (see task 4.1's note) — covered instead by the manual check in 6.1.
- [x] 2.5 Moot: `Setup_3_PL.php` was rewritten from scratch in task 1.2 with no `$isDouble` branch left at all, so the pre-existing bug has nothing left to fix.
- [x] 2.3 Extend the youth type gates in `lib.php` to cover TourType 37 wherever they cover 3 — done as part of the task 1.1 rewrite (`in_array($TourType, array(3, 6, 37))` in both `CreateStandardClasses()` and `InsertStandardEvents()`).
- [x] 2.4 Full parity test, TourType 37 against TourType 3 — `testPlSetup70mFamilyType37MatchesType3ExceptDistances`: identical `CreateDivision`/`CreateClass`/`CreateEventNew`/`CreateTargetFace`/`InsertClassEvent` call logs between a multiplier-1/TourType-3 run and a multiplier-2/TourType-37 run (none of those five builders take `$TourType` as an argument, so this is a legitimate byte-for-byte comparison, not an approximation).

## 3. Class-set filtering

- [x] 3.5 Spike: mixed-arity distances — **fails, done 2026-09-14**. Built a throwaway TourType 16 tournament (dev install, IT ruleset), one class at 2 legs, one at 4 legs, `ToNumDist=4`, one athlete each. `Qualification/index.php` shows both athletes under all 4 distance selections regardless of leg count — no per-class filtering exists in that screen. Confirmed independently by the official ianseo PL module's own pattern (always matches leg-counts within a tournament, never mixes them). See `design.md`, "Scope change: TourType 16 is U12-only". **Result: TourType 16 is U12-only; U15 stays on TourType 3/37.** Section 4 below reflects this — no mixed-arity handling needed.
- [x] 3.1 Add `$KidsOnly = false` to `CreateStandardClasses($TourId, $TourType, $KidsOnly = false)`. When true, skip the ten base `CreateClass()` calls and the U15 block, and emit only U12M/U12W, ignoring the `$TourType`-based gates. Default preserves today's behavior for types 1/3/6/37 — do not build this against the `class-presets` change on the other branch; it isn't landed and this change doesn't wait on it.
- [x] 3.2 Add the same `$KidsOnly = false` param to `InsertStandardEvents()`. When true, seed `$rClasses = array('U12M', 'U12W')` and empty `$cClasses/$bClasses/$rMixedAges/$cMixedAges/$bMixedAges` (see `design.md`) instead of the base roster, then fall through to the existing Individual/Team/Mixed loops unchanged.
- [x] 3.3 Unit tests: `testCreateStandardClassesKidsOnlyCreatesU12OnlyRecurve`, `testInsertStandardEventsKidsOnlyBindsU12RecurveOnlyNoMixedTeam` ($KidsOnly=true), `testCreateStandardClassesKidsOnlyDefaultFalseUnchanged` plus the existing (still-passing, unmodified) type 1/3/6 tests ($KidsOnly=false/omitted).
- [x] 3.4 `testPlSetupKidsRoundEveryEventHasBindingNoOrphans`: the set of `CreateEventNew` codes and the set of `InsertClassEvent` codes are both exactly `{RU12M, RU12W}` — no orphans either direction.

## 4. Setup_16_PL.php

- [x] 4.1 Add `Setup_16_PL.php`: type name, outdoor category, 3-arrow ends, `$tourDetNumDist = 4`, `$tourDetNumEnds = 24`, `$tourDetMaxDistScore = 180`. Body extracted into `pl_setup_kids_round($TourId, $TourType, $PL_CLASS_NAMES)` in `lib.php` (not in the original plan — done for the same testability reason as `pl_setup_70m_family`, see `design.md`), using `CreateStandardClasses`/`InsertStandardEvents` with the `$KidsOnly` flag from section 3.
- [x] 4.2 U12 four distances and faces: 25m and 20m on 122cm, 15m and 10m on 80cm (task 0.2), Recurve only.
- [x] 4.3 Unit tests: `testPlSetupKidsRoundDistancesAndFaces` (distances + target face), `testPlSetupKidsRoundThreeArrowEndsSeventyTwoArrowsTotal` (6 ends × 3 arrows × 4 legs = 72), `testPlSetupKidsRoundNoElimination` (`EvFinalFirstPhase=0` on all 4 events).

## 5. Register the types

- [x] 5.1 `sets.php`: add 16 and 37 to `$AllowedTypes`, remove the `Poland-4x70m` line.
- [x] 5.2 `sets.php`: after building `$SetType['PL']['types']` from the core `$TourTypes` array, override entries `'16'` and `'37'` with hardcoded Polish labels ("Runda dziecięca", "Podwójna runda 70m/50m").
- [x] 5.3 Manual check: confirmed by the competition owner — both new types appear in the rule dropdown with proper Polish labels ("Runda dziecięca", "Podwójna runda 70m/50m").
- [x] 5.4 Known limitation, documented not fixed: the public tournament homepage (`Main.php:70`) independently translates `ToTypeName` via core `get_text()`, with no PL-module hook — a Polish-language visitor will see the raw placeholder there for TourType 16/37 tournaments until ianseo core adds the missing `pl` translations. Out of scope (core file); not patched, per `design.md`/`proposal.md`.

## 6. Verify end to end

**Bug found and fixed during 6.2, 2026-09-14:** the `$KidsOnly` branch of `CreateStandardClasses()` initially copied the U12 `ClValidClass` chain verbatim from the base (`in_array($TourType, [6])`) definition — `'U12M,U15M,U18M,U21M,M'`. That chain is only meaningful when U15/U18/U21/M actually exist as classes in the same tournament (TourType 6); on U12-only TourType 16 they don't. The competition owner spotted this live on `16Test` (the participant screen offered U15/U18/U21/M as assignable classes for a U12 archer). Fixed to self-only chains (`'U12M'`/`'U12W'`), matching the existing pattern for top-of-chain classes like base `M`/`W`. `lib.php` and `LibTest.php` updated; re-verified live after resetting `16Test`'s setup — see `design.md`.

- [x] 6.1 Created `37Test` (ToId 158, TourType 37, PL). Verified directly in the DB: `ToNumDist=4, ToNumEnds=24, ToMaxDistScore=360, ToDouble=1, ToElimination=0`; 12 classes (matches TourType 3); distances doubled correctly (`RM` = 70m×4, `RU15M`/`RU15W` = 40m,40m,20m,20m); `DistanceInformation` = 4×(6 ends, 6 arrows); 70 events total, `RU15*` at `EvFinalFirstPhase=0` (no elimination) with team/individual/mixed all present.
- [x] 6.2 Created `16Test` (ToId 159, TourType 16, PL). Verified directly in the DB: only `U12M`/`U12W` classes, Recurve only; `TournamentDistances` = 25m/20m/15m/10m for both; target face 122/122/80/80 (`TGT_OUT_FULL`); `DistanceInformation` = 4×(6 ends, 3 arrows) = 72 arrows total; 4 events (individual + team for each class), all `EvFinalFirstPhase=0`; `EventClass` bindings match exactly, no orphans. Caught and fixed a real bug along the way (see below) — re-verified after the fix.
- [x] 6.3 Ran the full suite via the app container (`tools/phpunit.phar`, bind-mounted `Modules/Sets/PL`): 382 tests, 1444 assertions, all pass.
- [x] 6.4 Self-reviewed against `.github/agents/reviewer.prompt.md`. Security/scope/conventions/completeness clean — no core files touched, no new tables, `pl_` prefix on new functions, Polish text throughout, `ianseo-internals.md`-worthy findings recorded in `gotchas.md` (GetSetupFile's real-global promotion, Setup script test-ability, session-vs-leg model, `ClValidClass` scoping). One honest gap against the checklist: tests were written alongside implementation, not strict red-green (no failing-test-first transcript) — the checklist's own live-DB verification (6.1/6.2) substitutes as evidence the tests exercise real behavior, but it isn't the same discipline. Full suite green (382 tests, 1446 assertions) after every change including the `ClValidClass` fix.
