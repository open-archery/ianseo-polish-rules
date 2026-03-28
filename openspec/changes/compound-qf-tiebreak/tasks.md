## 1. Data layer — storage

- [ ] 1.1 Add `pl_combined_ranking_install_qf_table()` to `Fun_CombinedRanking.php`: auto-installs `PLQfTiebreak` (PlQtId, PlQtTournament, PlQtEnCode, PlQtArrows; unique key on tournament+code) via `SHOW TABLES LIKE`
- [ ] 1.2 Add `pl_combined_ranking_load_qf_counts($t1Id, $t2Id)` to `Fun_CombinedRanking.php`: returns array keyed `[$tourId][$enCode] => int`
- [ ] 1.3 Add `pl_combined_ranking_save_qf_count($tourId, $enCode, $arrows)` to `Fun_CombinedRanking.php`: upserts one row via `INSERT … ON DUPLICATE KEY UPDATE`

## 2. Data layer — detection and correction

- [ ] 2.1 Add `pl_combined_ranking_detect_qf_ties($sections, $qfCounts)` to `Fun_CombinedRanking.php`: returns list of unresolved ties — each entry: `['athlete' => name, 'licence' => enCode, 'tourId' => int, 'tourName' => string, 'place' => int]`; flags Compound athletes sharing an elim_place in 5–8 with a Finals row and missing count
- [ ] 2.2 Add `pl_combined_ranking_apply_qf_counts($sections, $qfCounts)` to `Fun_CombinedRanking.php`: for each tied Compound group, reassigns elim places by count DESC (higher count = better place), recalculates elim pts, re-sorts and re-ranks the section

## 3. UI — combined ranking page

- [ ] 3.1 In `CombinedRanking.php`, call `pl_combined_ranking_install_qf_table()` on every page load
- [ ] 3.2 In `CombinedRanking.php`, handle `$_POST['qf_save']`: validate inputs, call `pl_combined_ranking_save_qf_count()` for each submitted pair, redirect to self (PRG pattern)
- [ ] 3.3 In `CombinedRanking.php`, after computing sections, load QF counts, apply corrections, detect remaining ties, and pass `$ties` list to the view
- [ ] 3.4 In `CombinedRanking.php`, render warning banner when `$ties` is non-empty: list each tied athlete+tournament pair with an integer input and a save button

## 4. PDF — unresolved tie marker

- [ ] 4.1 Extend `pl_combined_ranking_print()` signature to accept a per-section `$unresolvedTies` flag (or derive it from the sections data)
- [ ] 4.2 In `PLCombinedRankingPdf::renderSection()`, accept an `$hasUnresolvedTie` parameter; mark tied ranks with `*` and append footnote "* Remis nierozstrzygnięty — brak danych 10/X/9" at the bottom of the section
