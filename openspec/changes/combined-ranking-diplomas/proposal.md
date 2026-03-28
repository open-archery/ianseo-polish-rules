## Why

The combined ranking feature merges two tournaments into a season-level ranking, but currently only produces a PDF table — there is no way to generate diplomas for the ranked athletes. Tournament organizers need to award diplomas to finalists based on this combined ranking.

## What Changes

- New printer page `CombinedRanking/PrnCombinedRankingDipl.php` — generates one diploma per qualifying athlete per division+class section
- Extended `CombinedRanking/CombinedRanking.php` UI — adds a date input and a "Generuj dyplomy" button alongside the existing "Generuj ranking PDF" button
- Reuses `PLDiplomaConfig` (competition name, location, judge, organizer, place range) from the active session tournament
- Date printed on the diploma comes from a separate input field on the form, not from `PLDiplomaConfig.Dates`
- No title lines ("Mistrza Polski...") on combined ranking diplomas
- Reuses `PLDiplomaPdf` and `DiplomaSetup` from the existing Diplomas module

## Capabilities

### New Capabilities

- `combined-ranking-diplomas`: Generation of PDF diplomas for athletes placed within the configured place range in the cross-tournament combined ranking

### Modified Capabilities

- `diplomas`: No requirement changes — only reused as a dependency (PDF class + config reader)

## Non-goals

- No new database tables or config screens
- No title/championship phrase generation for combined ranking diplomas
- No per-divClass text overrides (unlike `PLDiplomaEventText` for individual events)
- No changes to the existing individual/team diploma flow

## Impact

- **New file:** `CombinedRanking/PrnCombinedRankingDipl.php`
- **Modified file:** `CombinedRanking/CombinedRanking.php`
- **Dependencies:** `Diplomas/PLDiplomaPdf.php`, `Diplomas/DiplomaSetup.php`, `CombinedRanking/Fun_CombinedRanking.php`
- **No DB changes**
- **Spec produced by:** Advisor agent — `openspec/specs/combined-ranking-diplomas/spec.md`
- **Design produced by:** Developer agent — `openspec/changes/combined-ranking-diplomas/design.md`
