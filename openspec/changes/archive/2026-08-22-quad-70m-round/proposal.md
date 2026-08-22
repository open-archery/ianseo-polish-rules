## Why

PZŁucz regulations recognise "Podwójna runda 70 m" / "Podwójna runda 50 m" (Double Round) as official record categories (§2.11.1.1, §2.11.1.2), alongside the standard 72-arrow Single-Distance Round. ianseo's PL module (`Setup_3_PL.php`) only implements the standard round today — there is no way to run a Double Round tournament under the Poland ruleset.

## What Changes

- New sub-rule `Poland-4x70m` registered under TourType 3 only, alongside the existing `Poland-Full`.
- `Setup_3_PL.php` branches on `$SubRule`: when `Poland-4x70m` is selected, every class shoots its existing distance(s) **twice as many sessions** instead of the standard round's session count — R Senior/U24/U21 shoot 70m×4 (was 70m×2), R U18/50+ shoot 60m×4 (was 60m×2), R U15 shoots 40m,40m,20m,20m (was 40m,20m), C/B all classes shoot 50m×4 (was 50m×2).
- `tourDetNumDist` doubles (2→4) for this sub-rule; `tourDetMaxDistScore` is unchanged (360 — a per-session cap, session length doesn't change).
- Elimination/finals configuration (`EvFinalFirstPhase`, set/cumulative match structure, first-phase cut counts) is **unchanged** — doubling only affects the qualification round.
- Team scoring (best-3-of-4, [[manual-team-composition]]) is untouched — it operates on whichever scores qualification produces, regardless of session count.

## Capabilities

### Modified Capabilities

- `tournament-setup`: `Setup_3_PL.php` gains a second sub-rule (`Poland-4x70m`) that doubles the qualification session count for every class/division, while reusing the existing distance, target-face, and elimination configuration.

## Impact

- **Modified files:** `PL/sets.php` (register `Poland-4x70m` under type 3's rules), `PL/Setup_3_PL.php` (branch on `$SubRule` to build the doubled `$DistanceInfoArray` and per-class `CreateDistanceNew` calls)
- **No new files, no new DB tables, no core changes.**
- **Regulation reference:** §2.11.1.1 (Podwójna runda 70 m — Recurve/Barebow records), §2.11.1.2 (Podwójna runda 50 m — Compound records)
- **Spec owner:** Advisor confirms the doubled-session structure and scope (all classes, all divisions) against regulation; Developer implements the `$SubRule` branch in `Setup_3_PL.php` and the `sets.php` registration.

## Non-goals

- No new TourType ID — this reuses TourType 3 with a second sub-rule, matching the pattern other national rulesets (e.g. `FR/sets.php`) use for multiple 70m-family formats under one type.
- No changes to elimination/finals structure, target faces, or team scoring logic.
- No UI label translation — the sub-rule renders as an unresolved `[[Poland-4x70m]]@[lang]@[Install]` placeholder, matching current `Poland-Full` behaviour (accepted).
- Does not address the two-consecutive-days scheduling implied by §2.4.1.4's analogous 1440 rule — that is a tournament-scheduling concern outside setup-script scope.
