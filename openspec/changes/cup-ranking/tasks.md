## 1. Groundwork

- [x] 1.1 Read `.github/agents/research/ianseo-internals.md`, `openspec/specs/points-ranking/spec.md`, `PointsRanking/PointsRankingCalc.php` and `Diplomas/PLDiplomaPdf.php`; verify by listing, in the change notes, the exact row shape `pl_points_calculate()` returns for `SEPARATE(ind)` and `SEPARATE(mix)`
- [x] 1.2 Add `pl_cup_ensure_tables()` in `PointsRanking/Fun_Cup.php` creating `PLCupConfig`, `PLCupRound` and `PLCupBarrage` per design D2/D6a; verify with a `FakeDb::executed()` test asserting all three `CREATE TABLE IF NOT EXISTS` statements and the composite primary keys
- [x] 1.3 Add cup config accessors (`pl_cup_get_config()` / `pl_cup_set_config()`: edition, round, diploma name, edition defaulting to the tournament's end year); verify with tests covering first write, update and the default edition

## 2. Round data

- [x] 2.1 Add `pl_cup_load_qual_scores($tourId)` reading `Individuals.IndScore` by `EnId` and `Teams.TeScore` by club + event (design D4); verify with a stubbed-SQL test asserting both maps
- [x] 2.2 Add `pl_cup_build_snapshot()` turning a `pp` calculation plus qual scores into normalised round rows (classification, category, identity, name, club, place, points, qual); verify with a test over a fixture calculation result
- [x] 2.3 Enforce identity rules in the snapshot builder — missing licence, missing club code, two mixed rows of one club — rejecting the whole set with the offending rows named (spec "Row identity"); verify with one test per rejection case
- [x] 2.4 Add `pl_cup_store_round()` replacing a round's rows in full inside a transaction; verify with a test asserting the DELETE-then-INSERT order and a `FakeDb::throwOn()` rollback test

## 3. Aggregation

- [x] 3.1 Implement `pl_cup_aggregate()` in `PointsRanking/CupCalc.php` — group stored rows per classification, category and identity, per-round points, sum, best place, best qual, display name/club from the most recent round; verify with a test over rows spanning rounds 1, 2 and 4
- [x] 3.2 Implement the per-series tie-break map and comparator per design D6, including the `SHARED` / `BARRAGE` terminators and their marks; verify with tests for each spec scenario under "Tie-breaking", including the senior, compound and mixed ties that must stay shared
- [x] 3.3 Apply recorded baraż outcomes as the comparator's final step per design D6a — only for `BARRAGE` categories and only when round 4 is stored — flagging the affected rows `barrage_resolved`; verify with tests covering a recorded outcome, a partially recorded group, a pre-final-round standing, a `SHARED` category, an outcome surviving a re-import and one whose tie no longer exists
- [x] 3.4 Implement `pl_cup_diff_snapshot()` comparing freshly built rows against stored rows and returning the differing-row count; verify with tests for identical, changed, added and removed rows

## 4. CSV transport

- [x] 4.1 Implement CSV writing in the design D7 format (semicolon, UTF-8 BOM, header); verify with a test asserting header and one round-trip row
- [x] 4.2 Implement CSV parsing with per-line validation — column count, classification key, known category code (design D8), numeric place/points/qual, both decimal separators — collecting errors with line numbers and writing nothing when any line fails; verify with tests for a good file and for each rejection case
- [x] 4.3 Verify import/export round-trip: export a fixture round, re-import it, assert the stored rows are byte-identical to the originals

## 5. UI

- [x] 5.1 Create `PointsRanking/Cup.php` with the `pp` preset gate, `CheckTourSession(true)`, head/tail template and the edition/round/diploma-name form; verify by loading the page under `pp` and under `lzs` in ianseo and observing the gate message
- [x] 5.2 Add the snapshot button, the staleness banner (Polish, with the differing-row count) and the import/export controls; verify in ianseo by snapshotting, editing a result and reloading to see the warning
- [x] 5.3 Render both cup classifications as sectioned HTML tables with per-round columns, sum and "baraż" marks; verify in ianseo against a two-round fixture
- [x] 5.4 Add the baraż form — shown only for tied groups in `BARRAGE` categories once round 4 is stored — ordering the group's rows and storing the outcome (`pl_cup_set_barrage()`); verify in ianseo by resolving a tie and seeing distinct ranks with the baraż note, and by confirming the form is absent after round 2
- [x] 5.5 Add the menu entry in `menu.php` per design D9; verify it appears under Printouts for a PL tournament

## 6. Print outputs

- [x] 6.1 Create `PointsRanking/PrnCupRanking.php` producing the cup PDF (both classifications, sections, round columns, sum, shared-place and baraż marks, and baraż-resolved ranks, cup name and edition in the header); verify by generating it for a fixture cup and checking the rendered pages
- [x] 6.2 Create `PointsRanking/PrnCupDipl.php` using `PLDiplomaPdf` and `pl_diploma_get_config()`, overriding only the competition name, filtering by the configured place range, printing club-only recipients for mixed rows and reporting when no row is in range; verify by generating individual and mixed diplomas for a fixture cup

## 7. Wrap-up

- [x] 7.1 Run `tools\test.cmd` and confirm the whole suite passes
- [ ] 7.2 Manually verify the full flow in ianseo: import rounds 1–3 from CSV, snapshot round 4, resolve a baraż, print the classification PDF and both diploma sets
- [x] 7.3 Add any new footgun found during implementation to `gotchas.md`, then self-review against `.github/agents/reviewer.prompt.md` before committing
