## 1. Preset code change

- [ ] 1.1 In `PointsRanking/Presets.php`, remove the `tea` entry from the `lzs` preset's `classifications` array, leaving only `ind`
- [ ] 1.2 In the `lzs` preset's `reports` array, remove the `SEPARATE(tea)` ("Klasyfikacja drużynowa") entry and replace the `COMBINED(ind, tea, cap 0)` entry with `['kind' => 'SEPARATE', 'classification' => 'ind', 'label' => 'Klasyfikacja indywidualna']`, keeping `CLUB` and `VOIVODESHIP` after it
- [ ] 1.3 Remove the `'one_team_per_club' => true` line from the `lzs` preset (dead flag once no classification is a `TEAM`); verify by grepping `Presets.php` for `one_team_per_club` and confirming only the doc comment at the top of the file still mentions it
- [ ] 1.4 Run `tools/test.cmd --filter Presets` and confirm `PresetsTest.php` still passes unchanged (it iterates all presets generically and does not hardcode `lzs`'s classification count)

## 2. Test fixture rewrite

- [ ] 2.1 In `PointsRanking/Fun_PointsRankingTest.php`, rewrite `testCalculateWiresLoadersIntoClubTotalsForAQualOnlyTeamPreset` (lines ~401-442) to drop every `Teams`/`TeamComponent` stub and the team-classification comment, asserting the `CLUB` report total is `9` (individual points only, not `18`); rename the test to reflect the individual-only scenario (e.g. `testCalculateWiresLoadersIntoClubTotalsForAQualOnlyIndividualPreset`)
- [ ] 2.2 Run `tools/test.cmd --filter Fun_PointsRanking` and confirm the rewritten test passes and no other test in that file references `PL_POINTS_PRESETS['lzs']` with team fixtures

## 3. Full verification

- [ ] 3.1 Run the full suite (`tools/test.cmd`) and confirm all tests pass, including `PointsRankingCalcTest.php`'s generic `tea`-classification unit tests (those exercise the shared team-crediting mechanism directly with synthetic fixtures, not the `lzs` preset, and must stay green untouched)
- [ ] 3.2 Manually verify in ianseo with the `lzs` preset active on a tournament with `R`/`U21` entries and a team event configured: the points-ranking page shows only the individual table plus `CLUB`/`VOIVODESHIP`, with no "Klasyfikacja drużynowa" section and no "Zespołowo" column

## 4. Spec sync

- [ ] 4.1 After code and tests are green, run `/opsx:sync` (or the archive step) so `openspec/specs/points-ranking/spec.md` picks up this change's delta before archiving
