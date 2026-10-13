## 1. Preset

- [x] 1.1 In `PointsRanking/Presets.php`, add preset `mw` ("Mistrzostwa Województwa") after `lzs`: empty scope, single `ind` classification (`IND`, `ELIM`, cutoff `false`, brackets `[1,1,3] [2,2,2] [3,3,1]`), reports `[CLUB 'Klasyfikacja klubowa']`, `three_of_four` `false`, `one_team_per_club` `false`, `min_participation` `null`

## 2. Tests

- [x] 2.1 In `PointsRanking/PresetsTest.php`, update `assertCount(6, ...)` to 7; confirm the generic bracket-integrity test covers `mw`
- [x] 2.2 In `PointsRanking/Fun_PointsRankingTest.php`, add a test running `pl_points_calculate` with `PL_POINTS_PRESETS['mw']` on stubbed individuals placed 1–4 across two categories of one club; assert the club total is 6 and that the 4th-place athlete adds nothing
- [x] 2.3 Run `tools\test.cmd` and confirm the full suite is green

## 3. Manual check

- [x] 3.1 In ianseo, select "Mistrzostwa Województwa" on a tournament; confirm only the club table renders (no "Województwo" column), the PDF matches, and only the club diploma button is shown

## 4. Spec sync

- [ ] 4.1 After code and tests are green, run `/opsx:sync` (or archive) so `openspec/specs/points-ranking/spec.md` picks up the delta
