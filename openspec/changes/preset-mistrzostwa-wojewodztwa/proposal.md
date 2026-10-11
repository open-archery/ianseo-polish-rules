## Why

Regional championships ("Mistrzostwa Województwa") need a club points ranking, but the
points-ranking module has no preset for them. Operators currently cannot score these events
without borrowing an unrelated preset whose brackets and reports are wrong.

## What Changes

- Add preset `mw` — "Mistrzostwa Województwa" — to `PointsRanking/Presets.php`.
- Individual classification only: place 1 → 3 pts, place 2 → 2 pts, place 3 → 1 pt.
- Rank source `ELIM`; categories without an elimination round fall back to qualification
  rank through the existing `EvFinalFirstPhase = 0` rule (no new code).
- Cutoff disabled; no scope restriction (all divisions and classes).
- Single report: `CLUB`. No `VOIVODESHIP` report, so no voivodeship column, table or
  diploma button, and `PLVoivodeshipMap` is not consulted.
- No team or mixed classification, no `COMBINED` report (club totals are uncapped).
- Update the preset-count assertion in `PresetsTest.php` (6 → 7) and add a calculation test.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `points-ranking`: adds preset 7 (Mistrzostwa Województwa) to the read-only preset
  definitions, with its bracket table.

## Impact

- Code: `PointsRanking/Presets.php`, `PointsRanking/PresetsTest.php`,
  `PointsRanking/Fun_PointsRankingTest.php`.
- No schema, UI or calculation-engine change; the preset dropdown iterates `PL_POINTS_PRESETS`.
- Regulation: brackets are set by the operator for this event; they are not an annex of the
  PZŁucz regulations (no § reference).
- Roles: Advisor owns the delta spec; Developer owns design and code.

## Non-goals

- Voivodeship ranking or mapping changes.
- Team, mixed or combined classifications.
- A cutoff rule or a minimum-participation warning.
- Making the brackets operator-editable (presets stay read-only constants).
