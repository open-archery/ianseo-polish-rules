## 1. Database & Preset Infrastructure

- [ ] 1.1 Create `Fun_PointsRanking.php` with auto-install logic: create `PLPointsPreset`, `PLPointsEventConfig`, `PLPointsTable`, `PLPointsTournamentConfig`, `PLVoivodeshipMap` tables via `SHOW TABLES LIKE` pattern
- [ ] 1.2 Create `Presets.php` with PHP constant arrays for all 7 presets (names, flags, event configs, and full rank→points bracket tables)
- [ ] 1.3 Implement `pl_seed_presets()` in `Fun_PointsRanking.php`: insert all preset rows from `Presets.php` constants into the three preset tables (idempotent — skip if already seeded)
- [ ] 1.4 Implement `pl_get_presets()`: return all presets from `PLPointsPreset`
- [ ] 1.5 Implement `pl_get_tournament_preset($tournamentId)`: return selected preset ID or null
- [ ] 1.6 Implement `pl_set_tournament_preset($tournamentId, $presetId)`: upsert into `PLPointsTournamentConfig`
- [ ] 1.7 Implement `pl_get_preset_config($presetId)`: return preset row + event configs + bracket table rows
- [ ] 1.8 Implement `pl_get_voivodeship_map()`: return all `PLVoivodeshipMap` rows keyed by `CoId`
- [ ] 1.9 Implement `pl_save_voivodeship($coId, $voivodeship)`: upsert into `PLVoivodeshipMap`

## 2. Calculation Engine

- [ ] 2.1 Create `PointsRankingCalc.php` with class `PLPointsCalculator`; constructor accepts `$tournamentId` and `$presetConfig` array
- [ ] 2.2 Implement `lookupPoints($rank, $eventType)`: SQL query on `PLPointsTable` to find bracket where `PlptRankFrom <= $rank AND PlptRankTo >= $rank`; return 0 if no match
- [ ] 2.3 Implement `calcIndividual()`: query `Individuals` using `IndRank`/`IndRankFinal` (per event config source), call `lookupPoints()` per athlete, apply cutoff rule (zero athlete at rank = total_starters when starters < max bracket rank and cutoff enabled)
- [ ] 2.4 Implement `calcTeam()`: query `Teams` for team ranks, call `lookupPoints()` per team, query `TeamFinComponent JOIN Individuals` for roster, apply 3-of-4 drop (worst `IndRank` when count = 4 and `three_of_four` enabled), divide points by counting member count, accumulate per athlete `EnId`
- [ ] 2.5 Implement `calcMixed()`: same as `calcTeam()` but always divide by 2; no 3-of-4 logic
- [ ] 2.6 Implement `applyMaxEvents(array $athleteEventPoints)`: for each athlete collect `[IND, TEAM, MIXED]` points, sort descending, keep top `max_events` (or all if max_events = 0), sum
- [ ] 2.7 Implement `buildAthleteRanking(array $totals)`: join with `Entries` and `Companies` for names/clubs, sort by total desc, assign shared ranks on equal totals; return array of athlete rows
- [ ] 2.8 Implement `buildClubRanking(array $athleteRows)`: group by `CoId`, sum totals; skip if `club_rank_enabled = false`
- [ ] 2.9 Implement `buildVoivodeshipRanking(array $clubRows)`: join with `PLVoivodeshipMap`, group by voivodeship, sum club totals; label unmapped clubs "Nieprzypisane" and exclude from voivodeship totals; skip if `voiv_rank_enabled = false`
- [ ] 2.10 Implement public `calculate()`: orchestrate steps 2.3–2.9, return `['athletes' => [], 'clubs' => [], 'voivodeships' => []]`

## 3. Main UI Page

- [ ] 3.1 Create `PointsRanking/PointsRanking.php`: bootstrap (`config.php`), `CheckTourSession(true)`, call auto-install, load preset config
- [ ] 3.2 Implement preset selection form: dropdown of all presets, POST handler calling `pl_set_tournament_preset()`
- [ ] 3.3 If no preset selected: render only the selection form, suppress ranking output
- [ ] 3.4 If preset selected: instantiate `PLPointsCalculator`, call `calculate()`, render individual ranking HTML table (Miejsce, Zawodnik, Klub, IND pts, TEAM pts, MIXED pts, Suma)
- [ ] 3.5 Render club ranking HTML table below individual ranking (if `club_rank_enabled`)
- [ ] 3.6 Render voivodeship ranking HTML table below club ranking (if `voiv_rank_enabled`)
- [ ] 3.7 Add "Generuj PDF" button linking to `PointsRankingPdf.php`
- [ ] 3.8 Show warning banner if any team has an empty `TeamFinComponent` roster

## 4. Voivodeship Mapping UI

- [ ] 4.1 Create `VoivodeshipMap.php`: bootstrap, `CheckTourSession(true)`, call auto-install
- [ ] 4.2 Query all clubs (`Companies`) present in the current tournament via `Entries`; display table with club name and voivodeship dropdown (16 Polish voivodeships + blank)
- [ ] 4.3 Handle POST: iterate submitted mappings, call `pl_save_voivodeship()` for each, reload page with success message

## 5. PDF Generation

- [ ] 5.1 Create `PointsRankingPdf.php`: bootstrap, `CheckTourSession(false)`, load preset + calculate, output PDF inline
- [ ] 5.2 Implement `PLPointsRankingPdf` class (extends TCPDF): header method outputs tournament name, date, preset name
- [ ] 5.3 Implement `addIndividualSection()`: render individual ranking as TCPDF table with column headers matching HTML view; handle page breaks
- [ ] 5.4 Implement `addClubSection()`: render club ranking table (only if `club_rank_enabled`); include voivodeship column if `voiv_rank_enabled`
- [ ] 5.5 Implement `addVoivodeshipSection()`: render voivodeship ranking table (only if `voiv_rank_enabled`)
- [ ] 5.6 Format fractional points to 2 decimal places throughout PDF; suppress trailing ".00" for whole numbers

## 6. Menu Registration

- [ ] 6.1 Add two entries to `menu.php`: "Klasyfikacja punktowa" → `PointsRanking/PointsRanking.php` and "Mapa województw" → `PointsRanking/VoivodeshipMap.php`, guarded by `$_SESSION["TourLocRule"] == 'PL'`
