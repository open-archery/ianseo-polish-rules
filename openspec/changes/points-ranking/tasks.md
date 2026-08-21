## 1. Preset Definitions

- [ ] 1.1 Create `PointsRanking/Presets.php` with `PL_POINTS_PRESETS`: 6 presets, each with `name`, `scope` (divisions/classes), `classifications` (subject, source, cutoff, brackets) and ordered `reports`
- [ ] 1.2 Transcribe all six bracket tables verbatim from the spec; all are confirmed against the 2026 annexes. Watch the two known annex typos: Młodzieżowe MP individual prints `3-3` (read `3-4`) and MP Juniorów mixed prints `6-7`/`7-10` (read `8-10`)
- [ ] 1.3 Create `PointsRanking/PresetsTest.php`: assert every bracket satisfies `rank_from <= rank_to`, no two brackets in a classification overlap, every report references a declared classification, and every `COMBINED` cap is >= 0

## 2. Data Layer

- [ ] 2.1 Create `Fun_PointsRanking.php` with auto-install for `PLPointsTournamentConfig` and `PLVoivodeshipMap` via the `SHOW TABLES LIKE` pattern
- [ ] 2.2 Implement `pl_points_get_tournament_preset($tourId)` / `pl_points_set_tournament_preset($tourId, $presetKey)`
- [ ] 2.3 Implement `pl_points_get_voivodeship_map()` (keyed by `CoCode`) and `pl_points_save_voivodeship($coCode, $voivodeship)`
- [ ] 2.4 Implement `pl_points_load_categories($tourId, $scope)`: events present in the tournament filtered by the preset scope, with `Divisions.DivDescription` / `Classes.ClDescription` labels and `ClViewOrder`
- [ ] 2.5 Implement `pl_points_load_individuals($tourId, $source, $categories)`: `Individuals JOIN Entries JOIN Countries`, place from `IndRank` or `IndRankFinal`, returning `EnCode`, name, club `CoId`/`CoCode`/`CoName`, category, place
- [ ] 2.6 Implement `pl_points_load_teams($tourId, $source, $mixed, $categories)`: `Teams JOIN Events`, place from `TeRank`/`TeRankFinal`, club via `IF(EnCountry2=0, EnCountry, EnCountry2)`, size from `EvMaxTeamPerson`, mixed filter on `EvMixedTeam`
- [ ] 2.7 Implement `pl_points_load_rosters($tourId, $source, $teams)`: `TeamComponent` when source is `QUAL`, `TeamFinComponent` when `ELIM`; join on the full `(club, sub-team, event)` triple
- [ ] 2.8 Implement `pl_points_load_parent_ranks($tourId, $enIds, $teamEvent)`: qualification place of each roster member in `Events.EvCodeParent`, for the 3-of-4 rule
- [ ] 2.9 Move `pl_cup_ranking_get_div_labels()` and the division display order out of `CupRanking/Fun_CupRanking.php` into `Fun_PointsRanking.php` as `pl_ranking_div_labels()` / `PL_RANKING_DIVISION_ORDER`; update `CupRanking` to call the shared version

## 3. Calculation Engine (pure functions, no DB)

- [ ] 3.1 Create `PointsRankingCalc.php`; implement `pl_points_bracket($brackets, $place)`: first bracket where `from <= place <= to`, else 0; 0 for place `0` or `>= 29999`
- [ ] 3.2 Implement `pl_points_apply_cutoff($rows, $maxRankTo)`: per category, zero the last-placed row when starters (place < 29999) is below `$maxRankTo`
- [ ] 3.3 Implement `pl_points_split_team($teamPoints, $roster, $threeOfFour, $parentRanks)`: drop the worst qualifier when `three_of_four` and roster size is 4; return `[club => full, members => [enId => share]]`; empty roster returns club credit only plus a warning
- [ ] 3.4 Implement `pl_points_combine($athleteValues, $cap)`: sort desc, keep top `$cap` (all when 0), sum
- [ ] 3.5 Implement `pl_points_rank($rows)`: sort by points desc then place asc; assign shared ranks on equal points
- [ ] 3.6 Implement `pl_points_build_reports($preset, $classified)`: produce the ordered report list, omitting any report with no rows
- [ ] 3.7 Implement `pl_points_calculate($tourId, $preset)`: orchestrate loaders + 3.1–3.6, return `['reports' => [...], 'warnings' => [...]]`
- [ ] 3.8 Create `PointsRankingCalcTest.php` covering: bracket edges (1, last, first-outside), DSQ, cutoff on/off, team split of 3, 3-of-4 drop, mixed halving, cap 0 / 2 / more-results-than-cap, tie sharing a rank, empty roster warning, `SEPARATE` never contributing to a `COMBINED` total

## 4. Main UI Page

- [ ] 4.1 Create `PointsRanking/PointsRanking.php`: bootstrap `config.php`, `CheckTourSession(true)`, auto-install, load the selected preset
- [ ] 4.2 Preset selection form: dropdown over `PL_POINTS_PRESETS`, POST handler calling `pl_points_set_tournament_preset()`
- [ ] 4.3 No preset selected: render only the selection form; suppress ranking, PDF and diploma controls
- [ ] 4.4 Preset selected: run `pl_points_calculate()` and render each declared report as an HTML table, sectioned per category, in declared order
- [ ] 4.5 Render `SEPARATE` columns (Miejsce, Zawodnik/Zespół, Klub, Nr licencji, Miejsce w zawodach, Punkty) and `COMBINED` columns (one per classification plus Suma)
- [ ] 4.6 Render `CLUB` (Miejsce, Klub, Województwo when applicable, Suma) and `VOIVODESHIP` (Miejsce, Województwo, Suma) tables
- [ ] 4.7 "Generuj PDF" button linking to `PrnPointsRanking.php`
- [ ] 4.8 Diploma buttons for `CLUB` and `VOIVODESHIP`, rendered only when the active preset declares that report
- [ ] 4.9 Warning banner listing any team scored without a roster

## 5. Voivodeship Mapping UI

- [ ] 5.1 Create `VoivodeshipMap.php`: bootstrap, `CheckTourSession(true)`, auto-install
- [ ] 5.2 List every club in the current tournament (`Entries JOIN Countries`) with a dropdown of the 16 Polish voivodeships plus a blank option; pre-select the stored mapping
- [ ] 5.3 POST handler: upsert each submitted mapping by `CoCode`, reload with a confirmation message

## 6. PDF Output

- [ ] 6.1 Create `PrnPointsRanking.php`: bootstrap, `CheckTourSession(false)`, calculate, stream A4 PDF
- [ ] 6.2 Implement the TCPDF subclass header: tournament name, tournament date, preset name on every page
- [ ] 6.3 Render each declared report in order, with a bold centred category title per section and page-break handling
- [ ] 6.4 Format points to at most 2 decimals with a comma separator; suppress `,00` on whole values

## 7. Ranking Diplomas

- [ ] 7.1 Create `PrnPointsRankingDipl.php`: bootstrap, `CheckTourSession(true)`, `pl_diploma_ensure_tables()`, `pl_diploma_get_config()`
- [ ] 7.2 Accept a `Report` GET parameter (`CLUB` or `VOIVODESHIP`); reject anything else
- [ ] 7.3 Filter ranking rows to `PlaceFrom`..`PlaceTo` from the diploma config; if nothing is in range, report it instead of emitting an empty PDF
- [ ] 7.4 Drive `PLDiplomaPdf::printDiploma()` per row: club/voivodeship name in the recipient slot, empty club line, category line "Klasyfikacja klubowa" / "Klasyfikacja województw", body text and signatures from the diploma config

## 8. Menu Registration

- [ ] 8.1 Add "Klasyfikacja punktowa" and "Mapa województw" to `$ret['PRNT']` in `menu.php`, inside the existing `$_SESSION["TourLocRule"] == 'PL'` guard

## 9. Verification

- [ ] 9.1 `tools/test.cmd` green
- [ ] 9.2 Manual: LZS preset on a qualification-only recurve tournament — check the team ranking matches ianseo's own qualification team ranking, the club totals match hand calculation, and out-of-scope categories are absent
- [ ] 9.3 Manual: Puchar Polski preset on a compound/barebow tournament with a mixed event — check the individual and mixed tables are independent and no athlete total sums them
- [ ] 9.4 Manual: club and voivodeship diplomas print for places 1-3 with correct names and headers
