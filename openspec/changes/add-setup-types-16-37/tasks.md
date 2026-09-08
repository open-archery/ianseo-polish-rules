## 0. Close the open gaps

- [ ] 0.1 Read `.github/agents/research/pzlucz-rules.md`, `ianseo-internals.md` §4-§6, `openspec/specs/tournament-setup/spec.md`, and this change's `design.md`.
- [ ] 0.2 Get the four U12 children's-round distances from the competition owner (gap #1) and record them in `design.md`.
- [ ] 0.3 Confirm U12 is Recurve-only in the youth round (gap #2).
- [ ] 0.4 Confirm the youth round is qualification-only, no eliminations (gap #3).
- [x] 0.5 Check the install for tournaments with `ToTypeSubRule = 'Poland-4x70m'` (gap #4). Dev install: one, `KZLZS26` (2026-09-26, no entries, no scores) — recreate it as TourType 37 before the event.
- [ ] 0.6 Repeat the 0.5 check on the production install, which the dev database does not necessarily mirror.

## 1. Shared session-multiplier helper

- [ ] 1.1 Extract the body of `Setup_3_PL.php` into a `lib.php` helper taking a session multiplier (1 = single, 2 = double).
- [ ] 1.2 Rewrite `Setup_3_PL.php` to call the helper with multiplier 1 and drop `$isDouble` and every branch on it.
- [ ] 1.3 Unit tests: multiplier 1 reproduces today's `Setup_3_PL.php` distances, sessions and events exactly.

## 2. Setup_37_PL.php

- [ ] 2.1 Add `Setup_37_PL.php` calling the helper with multiplier 2, `$tourDetTypeName = 'Type_2x70mRound'`, `$tourDetNumDist = 4`.
- [ ] 2.2 Unit tests: every class gets 4 sessions; U15 order is 40m, 40m, 20m, 20m; `tourDetMaxDistScore` stays 360.
- [ ] 2.3 Extend the youth type gates in `lib.php` to cover TourType 37 wherever they cover 3 — `CreateStandardClasses()`'s `$hasU15 = in_array($TourType, array(3, 6))` and the matching `in_array()` in `InsertStandardEvents()`. Without this, TourType 37 silently has no U15 classes, events or bindings.
- [ ] 2.4 Full parity test, TourType 37 against TourType 3: identical divisions, identical class list (U15 included), identical event codes and their class bindings, identical target faces, identical team scoring configuration, identical elimination and finals configuration. Only the session count and `tourDetNumDist` may differ.

## 3. Class-set filtering

- [ ] 3.1 Add a class-set parameter to `CreateStandardClasses()` so a setup script can request the U15/U12 subset. Coordinate with the `class-presets` change — build it once, reuse it in both.
- [ ] 3.2 Apply the same filter in `InsertStandardEvents()`. Its U15 bindings are gated on TourTypes 3 and 6 and its U12 bindings on TourType 6, so TourType 16 would otherwise create `RU15*`, `CU15*` and `RU12*` events with no class bindings at all.
- [ ] 3.3 Unit tests: the youth subset creates U15M/W and U12M/W only, in eligible divisions; the default set is unchanged.
- [ ] 3.4 Unit test: every event created for TourType 16 has at least one class binding, and every youth class created has at least one event — no orphans in either direction.

## 4. Setup_16_PL.php

- [ ] 4.1 Add `Setup_16_PL.php`: type name, outdoor category, 3-arrow ends, `$tourDetNumDist = 4`, youth class set.
- [ ] 4.2 U15 distances and target faces (40m/20m; R 122/80, C 80/60).
- [ ] 4.3 U12 four distances and faces (two long on 122cm, two short on 80cm), using the values from task 0.2.
- [ ] 4.4 Unit tests for both class groups' distances, faces and end size.
- [ ] 4.5 Unit test on distance representation: U15 categories get exactly two distance entries (40m, 20m) and U12 exactly four, with no empty third or fourth entry written for U15 despite `$tourDetNumDist = 4`. Events bind classes, not distance columns, so no separate U15 events are needed for this.

## 5. Register the types

- [ ] 5.1 `sets.php`: add 16 and 37 to `$AllowedTypes`, remove the `Poland-4x70m` line.
- [ ] 5.2 Manual check: both new types appear in the rule dropdown when creating a tournament.

## 6. Verify end to end

- [ ] 6.1 Create a TourType 37 tournament on the dev install; confirm 4 sessions, correct distances per class, eliminations intact. Create a fresh tournament — never re-run setup on an existing one.
- [ ] 6.2 Create a TourType 16 tournament; confirm only U15/U12 classes exist with the right distances and faces.
- [ ] 6.3 Run `tools/test.cmd` (or `tools/test.sh`); full suite passes.
- [ ] 6.4 Self-review against the Reviewer agent checklist, then commit.
