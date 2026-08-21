## 1. Preset Definitions

- [x] 1.1 Create `PointsRanking/Presets.php` with `PL_POINTS_PRESETS`: 6 presets, each with `name`, `scope` (divisions/classes), `classifications` (subject, source, cutoff, brackets), ordered `reports` and flags (`three_of_four`, `one_team_per_club`, `min_participation`)
- [x] 1.2 Transcribe all six bracket tables verbatim from the spec; all are confirmed against the 2026 annexes (July 2026 update). Watch the two known annex typos: Młodzieżowe MP individual prints `3-3` (read `3-4`) and MP Juniorów mixed prints `6-7`/`7-10` (read `8-10`). Preset 2 cap is **3** ("trzykrotnie"); preset 4 has no `three_of_four` (declared 3-person rosters) and declares `min_participation` 3 clubs / 2 voivodeships
- [x] 1.3 Create `PointsRanking/PresetsTest.php`: assert every bracket satisfies `rank_from <= rank_to`, no two brackets in a classification overlap, every report references a declared classification, and every `COMBINED` cap is >= 0

## 2. Data Layer

- [x] 2.1 Create `Fun_PointsRanking.php` with auto-install for `PLPointsTournamentConfig` and `PLVoivodeshipMap` via the `SHOW TABLES LIKE` pattern
- [x] 2.2 Implement `pl_points_get_tournament_preset($tourId)` / `pl_points_set_tournament_preset($tourId, $presetKey)`
- [x] 2.3 Implement `pl_points_get_voivodeship_map()` (keyed by `CoCode`) and `pl_points_save_voivodeship($coCode, $voivodeship)`
- [x] 2.4 Implement `pl_points_load_categories($tourId, $scope)`: events present in the tournament filtered by the preset scope — individual events by `EnDivision`/`EnClass`, team events via their `EventClass` division/class pairs — with `Divisions.DivDescription` / `Classes.ClDescription` labels and `ClViewOrder`
- [x] 2.5 Implement `pl_points_load_individuals($tourId, $source, $categories)`: `Individuals JOIN Entries JOIN Countries`, place from `IndRank` or `IF(EvFinalFirstPhase=0, IndRank, ABS(IndRankFinal))` (Diplomas pattern), club via `IF(EnCountry2=0, EnCountry, EnCountry2)`, returning `EnCode`, name, club `CoId`/`CoCode`/`CoName`, category, place
- [x] 2.6 Implement `pl_points_load_teams($tourId, $source, $mixed, $categories)`: `Teams JOIN Events`, place from `TeRank` or `IF(EvFinalFirstPhase=0, TeRank, ABS(TeRankFinal))`, club via `IF(EnCountry2=0, EnCountry, EnCountry2)`, size from `EvMaxTeamPerson`, mixed filter on `EvMixedTeam`
- [x] 2.7 Implement `pl_points_load_rosters($tourId, $source, $teams)`: `TeamComponent` when source is `QUAL`, `TeamFinComponent` when `ELIM`; join on the full `(club, sub-team, event)` triple
- [x] 2.8 Implement `pl_points_load_parent_ranks($tourId, $enIds, $teamEvent)`: qualification place of each roster member — **deviation from the design doc**: uses the individual event with the same `EvCode` (`EvTeamEvent=0`), not `Events.EvCodeParent`. Verified `EvCodeParent` is never populated for the team↔individual link in this module's setup scripts or in FITA core (it chains sub-events within the same kind, e.g. finals brackets); team and individual events of one category share an `EvCode` (see the `I:`/`T:` composite-key convention in `Diplomas/Fun_Diploma.php`), which is a safe, verified-in-code lookup
- [x] 2.9 Move `pl_cup_ranking_get_div_labels()` and the division display order out of `CupRanking/Fun_CupRanking.php` into `Fun_PointsRanking.php` as `pl_ranking_div_labels()` / `PL_RANKING_DIVISION_ORDER`; update `CupRanking` to call the shared version — **`CupRanking/` does not exist on this branch** (it lives on the unmerged `feat/cup-ranking` branch, based on a pre-test-harness commit); implemented the shared helper standalone per design's own conditional note ("if that branch lands first"). Nothing to update.
- [x] 2.10 Implement `pl_points_load_starters($tourId, $categories)`: per-category counts of subjects with a valid **qualification** place (`IndRank`/`TeRank` > 0 and < 29999), used by the cutoff regardless of the classification's rank source

## 3. Calculation Engine (pure functions, no DB)

- [x] 3.1 Create `PointsRankingCalc.php`; implement `pl_points_bracket($brackets, $place)`: first bracket where `from <= place <= to`, else 0; 0 for place `0` or `>= 29999`
- [x] 3.2 Implement `pl_points_apply_cutoff($rows, $maxRankTo, $starters)`: per category, when the qualification-starter count is below `$maxRankTo`, zero **every** row sharing the worst place
- [x] 3.3 Implement `pl_points_split_team($teamPoints, $roster, $threeOfFour, $parentRanks)`: drop the worst qualifier when `three_of_four` and roster size is 4 (tie → higher entry id dropped); return `[members => [enId => share]]`; empty roster returns full club credit plus a warning
- [x] 3.4 Implement `pl_points_combine($athleteValues, $cap)`: sort desc, keep top `$cap` positive values (all when 0), sum; return kept **and** dropped values — dropped values reach no club/voivodeship total; post-cap total 0 → athlete omitted
- [x] 3.5 Implement `pl_points_rank($rows)`: sort by points desc then place asc (for combined rows: best place among contributing classifications); assign shared ranks on equal points
- [x] 3.6 Implement `pl_points_build_reports($preset, $classified)`: produce the ordered report list, omitting any report with no rows; `CLUB` sums post-cap athlete values (uncapped when no `COMBINED` declared) with exact team arithmetic (team value once minus dropped shares) plus full credit for roster-less teams; `VOIVODESHIP` groups club totals via the map, empty `CoCode` = unmapped
- [x] 3.7 Implement `pl_points_calculate($tourId, $preset)`: orchestrate loaders + 3.1–3.6, return `['reports' => [...], 'warnings' => [...]]`
- [x] 3.8 Create `PointsRankingCalcTest.php` covering: bracket edges (1, last, first-outside), DSQ, cutoff on/off, cutoff zeroing all rows tied at the worst place, starters counted from qualification, team split of 3, 3-of-4 drop (incl. tie), mixed halving, cap 0 / 2 / more-results-than-cap, cap-dropped value absent from the club total, exact share arithmetic (`22/3` shares summing back to 22), tie sharing a rank, empty roster warning, `SEPARATE` never contributing to a `COMBINED` total

## 4. Main UI Page

- [x] 4.1 Create `PointsRanking/PointsRanking.php`: bootstrap `config.php`, `CheckTourSession(true)`, auto-install, load the selected preset
- [x] 4.2 Preset selection form: dropdown over `PL_POINTS_PRESETS`, POST handler calling `pl_points_set_tournament_preset()`
- [x] 4.3 No preset selected: render only the selection form; suppress ranking, PDF and diploma controls
- [x] 4.4 Preset selected: run `pl_points_calculate()` and render each declared report as an HTML table, sectioned per category, in declared order
- [x] 4.5 Render `SEPARATE` columns (Miejsce, Zawodnik/Zespół, Klub, Nr licencji, Miejsce w zawodach, Punkty) and `COMBINED` columns (one per classification plus Suma)
- [x] 4.6 Render `CLUB` (Miejsce, Klub, Województwo when applicable, Suma) and `VOIVODESHIP` (Miejsce, Województwo, Suma) tables
- [x] 4.7 "Generuj PDF" button linking to `PrnPointsRanking.php`
- [x] 4.8 Diploma buttons for `CLUB` and `VOIVODESHIP`, rendered only when the active preset declares that report
- [x] 4.9 Warning banner listing any team scored without a roster; `min_participation` warning (annex voids the classification) when the preset declares it and the threshold is unmet

## 5. Voivodeship Mapping UI

- [x] 5.1 Create `VoivodeshipMap.php`: bootstrap, `CheckTourSession(true)`, auto-install
- [x] 5.2 List every club in the current tournament (`Entries JOIN Countries`) with a dropdown of the 16 Polish voivodeships plus a blank option; pre-select the stored mapping
- [x] 5.3 POST handler: upsert each submitted mapping by `CoCode`, reload with a confirmation message

## 6. PDF Output

- [x] 6.1 Create `PrnPointsRanking.php`: bootstrap, `CheckTourSession(true)`, calculate, stream A4 PDF — **deviation from the design doc**, which said `CheckTourSession(false)`. Code review caught that the boolean return was discarded, so a missing session fell through into broken code instead of stopping; every other PL entry point (`Diplomas/Prn*.php`, `PointsRanking.php`, `VoivodeshipMap.php`, `PrnPointsRankingDipl.php`) uses `true`, so switched to match rather than add a manual `if (!CheckTourSession(false))` special case
- [x] 6.2 Implement the PDF header: tournament name, tournament date, preset name on every page — `PointsRankingPdf.php` extends the core `IanseoPdf` (`Common/pdf/IanseoPdf.php`), the same base every other ianseo report PDF uses (standard header with tournament name/date/logos, page numbers, version footer), overriding `Header()` only to append the preset name. Reworked from an initial `TCPDF`-direct version after review: `CupRanking/PrnCupRanking.php` (`feat/cup-ranking`) was the reference for both the base class and the table look (grey title bar, grey shaded column headers, alternating row fill, bordered cells)
- [x] 6.3 Render each declared report in order, with a bold centred category title per section and page-break handling
- [x] 6.4 Format points to at most 2 decimals with a comma separator; suppress `,00` on whole values

## 7. Ranking Diplomas

- [x] 7.1 Create `PrnPointsRankingDipl.php`: bootstrap, `CheckTourSession(true)`, `pl_diploma_ensure_tables()`, `pl_diploma_get_config()`
- [x] 7.2 Accept a `Report` GET parameter (`CLUB` or `VOIVODESHIP`); reject anything else
- [x] 7.3 Filter ranking rows to `PlaceFrom`..`PlaceTo` from the diploma config; if nothing is in range, report it instead of emitting an empty PDF
- [x] 7.4 Drive `PLDiplomaPdf::printDiploma()` per row: club/voivodeship name in the recipient slot, empty club line, category line "Klasyfikacja klubowa" / "Klasyfikacja województw", body text and signatures from the diploma config

## 8. Menu Registration

- [x] 8.1 Add "Klasyfikacja punktowa" and "Mapa województw" to `$ret['PRNT']` in `menu.php`, inside the existing `$_SESSION["TourLocRule"] == 'PL'` guard

## 9. Verification

- [x] 9.1 `tools/test.cmd` green (verified via the portable-PHP + phpunit.phar path from memory — no `php` on PATH on this machine; 199 tests, 964 assertions, all green)
- [ ] 9.2 Manual: LZS preset on a qualification-only recurve tournament — check the team ranking matches ianseo's own qualification team ranking, the club totals match hand calculation, and out-of-scope categories are absent
- [ ] 9.3 Manual: Puchar Polski preset on a compound/barebow tournament with a mixed event — check the individual and mixed tables are independent and no athlete total sums them
- [ ] 9.4 Manual: club and voivodeship diplomas print for places 1-3 with correct names and headers
