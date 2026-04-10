## 1. Update `Obj_Rank_FinalInd_calc.php`

- [ ] 1.1 Expand the losers SELECT query to fetch `DiEndArrows`, `DiArrows`, `FinArrowstring`, `FinSetPoints`, `FinTiebreak`, `i.IndScore AS QualScore`, `f.FinMatchNo AS RealMatchNo`, `f2.FinMatchNo AS OppRealMatchNo`; add opponent equivalents (`f2.FinArrowstring AS OppArrowstring`, `f2.FinSetPoints AS OppSetPoints`, `f2.FinTiebreak AS OppTiebreak`); change `ORDER BY` to `least(f.FinMatchNo, f2.FinMatchNo)` only
- [ ] 1.2 Add `FinAverageMatch`/`FinAverageTie` writes to `Finals` in the phase 0 (gold) branch, for both athlete and opponent, using the core average formula
- [ ] 1.3 Add `FinAverageMatch`/`FinAverageTie` writes to `Finals` in the phase 1 (bronze) branch, for both athlete and opponent
- [ ] 1.4 Add `FinAverageMatch`/`FinAverageTie` writes to `Finals` in the phase 2 / SubCodes loop, for both athlete and opponent
- [ ] 1.5 Replace the phases-≥-4 `else` branch: build `$matchData` array with `avgMatch`, `avgTie`, `qualScore` per row; write `FinAverageMatch`/`FinAverageTie` for each row and opponent in the loop
- [ ] 1.6 Sort `$matchData` using `usort()` with three-level comparator: `avgMatch DESC` → `avgTie DESC` → `qualScore DESC`
- [ ] 1.7 Assign unique sequential ranks from the sorted array (same `$pos` start logic as before: `4` for QF, `numMatchesByPhase + SavedInPhase` for deeper)
- [ ] 1.8 Remove the `EvElimType == 3 || EvElimType == 4` pool-phase rank override block
- [ ] 1.9 Update the file-level docblock to describe the new tiebreaker criteria

## 2. Update `Obj_Rank_FinalTeam_calc.php`

- [ ] 2.1 Expand the team losers SELECT query to fetch `DiEndArrows`, `DiArrows`, `tf.TfArrowstring`, `tf.TfSetPoints`, `tf.TfTiebreak`, `te.TeScore AS QualScore`, `tf.TfMatchNo AS RealMatchNo`, `tf2.TfMatchNo AS OppRealMatchNo`; add opponent equivalents; change `ORDER BY` to `least(tf.TfMatchNo, tf2.TfMatchNo)` only
- [ ] 2.2 Add `TfAverageMatch`/`TfAverageTie` writes to `TeamFinals` in the phase 0 (gold) branch, for team and opponent
- [ ] 2.3 Add `TfAverageMatch`/`TfAverageTie` writes to `TeamFinals` in the phase 1 (bronze) branch, for team and opponent
- [ ] 2.4 Add `TfAverageMatch`/`TfAverageTie` writes to `TeamFinals` in the phase 2 / SubCodes loop, for team and opponent
- [ ] 2.5 Replace the phases-≥-4 `else` branch: build `$matchData` array; write `TfAverageMatch`/`TfAverageTie` for each row and opponent in the loop
- [ ] 2.6 Sort `$matchData` using `usort()` with three-level comparator: `avgMatch DESC` → `avgTie DESC` → `qualScore DESC`
- [ ] 2.7 Assign unique sequential ranks from sorted array (same `$pos` start logic)
- [ ] 2.8 Remove the `EvElimType == 3 || EvElimType == 4` pool-phase rank override block (not present in team file — verify and skip if absent)
- [ ] 2.9 Update the file-level docblock to describe the new tiebreaker criteria

## 3. Verify

- [ ] 3.1 Run a PL outdoor tournament through the full ranking calculation; confirm QF losers receive unique places 5–8 ordered by average match score
- [ ] 3.2 Confirm that `FinAverageMatch` and `FinAverageTie` are populated in `Finals` for gold, bronze, semifinal, and QF participants after recalculation
- [ ] 3.3 Confirm no-bronze-match detection still works: a 0-0 bronze match assigns shared 3rd to both semifinal losers
- [ ] 3.4 Confirm team bracket sub-ranking produces unique places using `TeScore` as third tiebreaker
