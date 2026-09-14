## 0. Close the open gaps

- [ ] 0.1 Read `.github/agents/research/pzlucz-rules.md`, `ianseo-internals.md` §4-§6, `openspec/specs/tournament-setup/spec.md`, and this change's `design.md`.
- [x] 0.2 U12 children's-round distances (gap #1): 25m, 20m, 15m, 10m (Recurve), per `regulamin-lucznictwa.md` §2.1.2.3.1-2 — see `design.md`. No need to ask the competition owner.
- [x] 0.3 U12 is Recurve-only (gap #2): every U12 provision in `regulamin-lucznictwa.md` is paired with "łuków klasycznych"; none with Compound. See `design.md`.
- [x] 0.4 Children's round is qualification-only (gap #3): U12 has no elimination format defined anywhere in the regulation. See `design.md`.
- [x] 0.5 Check the install for tournaments with `ToTypeSubRule = 'Poland-4x70m'` (gap #4). Dev install: one, `KZLZS26` (2026-09-26, no entries, no scores) — recreate it as TourType 37 before the event.
- [x] 0.6 Waived by the competition owner (2026-09-14): production check skipped, `Poland-4x70m` removal proceeds regardless of what's live there. Any production tournament still on `Poland-4x70m` (including `KZLZS26` if it also exists in prod) will be handled by hand outside this change.

## 1. Shared session-multiplier helper

- [ ] 1.1 Add `pl_setup_70m_family($TourId, $TourType, $Multiplier)` to `lib.php`: explicit `$TourId`/`$TourType` params (not inherited scope), `global $PL_CLASS_NAMES, $PL_MIXED_CLASS_NAMES;` at the top, `$DistanceInfoArray` computed from `$Multiplier` internally. Move `pl_double_legs()` into `lib.php` as its own top-level function (remove it from `Setup_3_PL.php` — leaving both copies loaded is a redeclaration fatal).
- [ ] 1.2 Move everything between "Divisions & Classes" and "Tour Update" out of `Setup_3_PL.php` and into the helper. Rewrite `Setup_3_PL.php` down to `$TourType`, the `$tourDetXxx` assignments, the `require_once`s, one call to `pl_setup_70m_family($TourId, 3, 1)`, and the `$tourDetails`/`UpdateTourDetails()` call. Drop `$isDouble` and every branch on it.
- [ ] 1.3 Unit tests: multiplier 1 reproduces today's `Setup_3_PL.php` distances, sessions and events exactly.

## 2. Setup_37_PL.php

- [ ] 2.1 Add `Setup_37_PL.php` calling the helper with multiplier 2, `$tourDetTypeName = 'Type_2x70mRound'`, `$tourDetNumDist = 4`, `$tourDetDouble = '1'` (see `design.md` — this drives the results-PDF pairing layout, not cosmetic).
- [ ] 2.2 Unit tests: every class gets 4 sessions; U15 order is 40m, 40m, 20m, 20m; `tourDetMaxDistScore` stays 360; `tourDetDouble` is `'1'`.
- [ ] 2.5 While touching `Setup_3_PL.php`: consider fixing the pre-existing bug where `$tourDetDouble` stays `'0'` even under today's `Poland-4x70m` `$isDouble` path (moot once that sub-rule is removed in section 5, but worth a one-line note in the commit if left as dead code until then).
- [ ] 2.3 Extend the youth type gates in `lib.php` to cover TourType 37 wherever they cover 3 — `CreateStandardClasses()`'s `$hasU15 = in_array($TourType, array(3, 6))` and the matching `in_array()` in `InsertStandardEvents()`. Without this, TourType 37 silently has no U15 classes, events or bindings.
- [ ] 2.4 Full parity test, TourType 37 against TourType 3: identical divisions, identical class list (U15 included), identical event codes and their class bindings, identical target faces, identical team scoring configuration, identical elimination and finals configuration. Only the session count and `tourDetNumDist` may differ.

## 3. Class-set filtering

- [x] 3.5 Spike: mixed-arity distances — **fails, done 2026-09-14**. Built a throwaway TourType 16 tournament (dev install, IT ruleset), one class at 2 legs, one at 4 legs, `ToNumDist=4`, one athlete each. `Qualification/index.php` shows both athletes under all 4 distance selections regardless of leg count — no per-class filtering exists in that screen. Confirmed independently by the official ianseo PL module's own pattern (always matches leg-counts within a tournament, never mixes them). See `design.md`, "Scope change: TourType 16 is U12-only". **Result: TourType 16 is U12-only; U15 stays on TourType 3/37.** Section 4 below reflects this — no mixed-arity handling needed.
- [ ] 3.1 Add `$KidsOnly = false` to `CreateStandardClasses($TourId, $TourType, $KidsOnly = false)`. When true, skip the ten base `CreateClass()` calls and the U15 block, and emit only U12M/U12W, ignoring the `$TourType`-based gates. Default preserves today's behavior for types 1/3/6/37 — do not build this against the `class-presets` change on the other branch; it isn't landed and this change doesn't wait on it.
- [ ] 3.2 Add the same `$KidsOnly = false` param to `InsertStandardEvents()`. When true, seed `$rClasses = array('U12M', 'U12W')` and empty `$cClasses/$bClasses/$rMixedAges/$cMixedAges/$bMixedAges` (see `design.md`) instead of the base roster, then fall through to the existing Individual/Team/Mixed loops unchanged.
- [ ] 3.3 Unit tests: `$KidsOnly = true` creates U12M/W only, Recurve only, no mixed team; `$KidsOnly = false` (or omitted) is byte-for-byte unchanged from today for types 1/3/6/37.
- [ ] 3.4 Unit test: every event created for TourType 16 has at least one class binding, and U12M/U12W each have at least one event — no orphans in either direction.

## 4. Setup_16_PL.php

- [ ] 4.1 Add `Setup_16_PL.php`: type name, outdoor category, 3-arrow ends, `$tourDetNumDist = 4`, `$tourDetMaxDistScore = 180`, `CreateStandardClasses($TourId, 16, true)` / `InsertStandardEvents($TourId, 16, true)` (the `$KidsOnly` flag from section 3).
- [ ] 4.2 U12 four distances and faces: 25m and 20m on 122cm, 15m and 10m on 80cm (task 0.2), Recurve only.
- [ ] 4.3 Unit tests for U12's distances, faces and 3-arrow end size.

## 5. Register the types

- [ ] 5.1 `sets.php`: add 16 and 37 to `$AllowedTypes`, remove the `Poland-4x70m` line.
- [ ] 5.2 `sets.php`: after building `$SetType['PL']['types']` from the core `$TourTypes` array, override entries `'16'` and `'37'` with hardcoded Polish labels (e.g. "Runda dziecięca", "Podwójna runda 70m/50m") — `Common/Languages/pl/Tournament.php` has no translation for `Type_2x70mRound`/`Type_GiochiGioventuW`, so `get_text()` would otherwise render a raw `<b>[[...]]</b>` placeholder in the creation dropdown. See `design.md`.
- [ ] 5.3 Manual check: both new types appear in the rule dropdown with proper Polish labels when creating a tournament.
- [ ] 5.4 Known limitation, documented not fixed: the public tournament homepage (`Main.php:70`) independently translates `ToTypeName` via core `get_text()`, with no PL-module hook — a Polish-language visitor will see the raw placeholder there for TourType 16/37 tournaments until ianseo core adds the missing `pl` translations. Out of scope (core file); do not patch `Common/Languages/pl/Tournament.php` as part of this change.

## 6. Verify end to end

- [ ] 6.1 Create a TourType 37 tournament on the dev install; confirm 4 sessions, correct distances per class, eliminations intact. Create a fresh tournament — never re-run setup on an existing one.
- [ ] 6.2 Create a TourType 16 tournament; confirm only U12M/U12W exist with the right distances and faces.
- [ ] 6.3 Run `tools/test.cmd` (or `tools/test.sh`); full suite passes.
- [ ] 6.4 Self-review against the Reviewer agent checklist, then commit.
