## Why

The LZS preset (`lzs`, Mistrzostwa Krajowego Zrzeszenia LZS) currently scores a `TEAM` classification built from ianseo's auto-generated qualification teams, and folds those team points into the "Podsumowanie punktacji" combined ranking, a standalone team table, and the club/voivodeship totals. This was a deliberate design call (D4 in the archived `points-ranking` design) based on an annex reading that assumed LZS teams exist the same way they do for MMP/MPJ/OOM. That assumption is wrong: LZS competitions have no team event at all — only individual athletes are classified. Scoring and displaying a team ranking that doesn't exist in the actual competition misrepresents the LZS classification and inflates club/voivodeship totals with points from a fictitious event.

## What Changes

- **BREAKING**: Remove the `tea` (TEAM) classification from the `lzs` preset in `Presets.php` — no bracket table, no `TeamComponent` roster read, no `one_team_per_club` scoring for LZS.
- **BREAKING**: Remove the `SEPARATE(tea)` "Klasyfikacja drużynowa" report from the `lzs` preset — no team table is rendered.
- **BREAKING**: Collapse the `lzs` preset's combined report to the `ind` classification alone — the "Podsumowanie punktacji" / individual ranking no longer has a "Zespołowo" column, and CLUB/VOIVODESHIP totals sum only individual athlete points.
- Update the `points-ranking` spec's preset-6 (LZS) row, its scope/team-assignment requirements, and the "Brackets — preset 6 (LZS)" reference table to describe LZS as individual-only.
- No other preset (`mmp`, `mpj`, `mmm`, `oom`) is touched — their real team classifications are unaffected.
- No change to the Cup module (`Cup.php`/`CupCalc.php`, Puchar Polski only): confirmed during exploration that it already never scores or displays team data, since the `pp` preset has no `tea` classification.

## Capabilities

### New Capabilities

(none)

### Modified Capabilities

- `points-ranking`: the LZS preset's requirements change — its classification set drops `TEAM`, its report list drops `SEPARATE(tea)` and the `tea` leg of `COMBINED`, and its club/voivodeship totals are individual-only. General requirements (team points assignment, report composition scenarios) that cite the LZS preset as their example need their LZS-specific scenarios corrected; the underlying team-scoring mechanism itself still applies to the other presets.

## Impact

- `PointsRanking/Presets.php` — `lzs` entry: drop `tea` classification, drop `SEPARATE(tea)` and `tea` from `COMBINED`, drop `one_team_per_club`.
- `PointsRanking/PresetsTest.php`, `PointsRanking/PointsRankingCalcTest.php` — any fixtures/assertions keyed to `lzs` having a `tea` classification.
- `PointsRanking/Fun_PointsRankingTest.php:399-437` — the LZS end-to-end fixture currently asserts a scored team roster; it must be rewritten as individual-only.
- `openspec/specs/points-ranking/spec.md` — preset definitions table (row 6), "Brackets — preset 6 (LZS)" section, and any scenario using LZS as its example of team scoring, cutoff, or `COMBINED(ind,tea)`.
- No DB migration: presets are read-only PHP constants (D1), so this takes effect on next deploy with no schema or data changes. Any tournament with `lzs` already selected simply stops showing/scoring the team leg from that point on.
