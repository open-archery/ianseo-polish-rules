## 1. Database — PLTeamDeclaration table

- [ ] 1.1 Write `pl_ensure_team_declaration_table()` helper in `Teams/ManualTeams-data.php` that auto-installs `PLTeamDeclaration` via `SHOW TABLES LIKE` pattern
- [ ] 1.2 Verify column set matches design schema: `PltdTournament`, `PltdEvent`, `PltdCoId`, `PltdSubTeam`, `PltdId`, `PltdOrder`, `PltdTimestamp` with composite PK

## 2. Rank override — best-3-of-4

- [ ] 2.1 Create `Rank/Obj_Rank_DivClassTeam_calc.php` extending `Obj_Rank_DivClassTeam`
- [ ] 2.2 Implement `calculate()`: for each team in `TeamComponent`, query `Qualifications` and sum the best 3 athletes' `QuScore`, `QuGold`, `QuXnine`
- [ ] 2.3 `UPDATE Teams` with recalculated scores before ranking
- [ ] 2.4 Rank teams per event by `TeScore DESC, TeGold DESC, TeXnine DESC`, update `TeRank` and `TeTimeStamp`
- [ ] 2.5 Verify 3-athlete teams (all 3 summed) and 4-athlete teams (best 3) both work correctly

## 3. AJAX backend — ManualTeams-data.php

- [ ] 3.1 Create `Teams/ManualTeams-data.php` with bootstrap, `CheckTourSession(false)`, ACL check, and `pl_ensure_team_declaration_table()` call
- [ ] 3.2 Implement `action=list`: return all declared teams for the current tournament (with athlete names, scores, sub-teams), grouped by event
- [ ] 3.3 Implement `action=athletes`: return eligible athletes for a given event (filter: `EnAthlete=1`, `EnTeamClEvent=1`, `EnStatus<=1`, joined with `Qualifications` for score display)
- [ ] 3.4 Implement `action=save`: validate input (event, club, 3–4 distinct athletes, no duplicate athlete across sub-teams for same event+club), write to `PLTeamDeclaration`, sync to `Teams`+`TeamComponent`, trigger rank calc
- [ ] 3.5 Implement `action=delete`: remove from `PLTeamDeclaration`, remove from `Teams`+`TeamComponent`, trigger rank calc
- [ ] 3.6 Implement `action=restore`: re-sync all `PLTeamDeclaration` rows for current tournament into `Teams`+`TeamComponent`, then trigger rank calc for all affected events
- [ ] 3.7 Wrap all write operations in `safe_w_BeginTransaction()` / `safe_w_Commit()` / `safe_w_Rollback()`

## 4. UI — ManualTeams.php

- [ ] 4.1 Create `Teams/ManualTeams.php` with standard page template (`head.php` / `tail.php`), `$IncludeJquery = true`
- [ ] 4.2 Render event selector (dropdown: all team events for the current tournament from `Events` where `EvTeamEvent=1`)
- [ ] 4.3 Render club/country selector (dropdown: all clubs with at least one registered athlete for the selected event, from `Entries` JOIN `Countries`)
- [ ] 4.4 Render sub-team selector (1..N based on existing declared teams + 1 for new)
- [ ] 4.5 Render available athletes list (from `action=athletes` AJAX call) with qualification score displayed
- [ ] 4.6 Render current team members panel (populated from `action=list` for selected event+club+sub-team)
- [ ] 4.7 Implement save/delete roster via AJAX calls to `ManualTeams-data.php`, refresh team list on success
- [ ] 4.8 Add "Przywróć skład" (Restore rosters) button wired to `action=restore`; show confirmation dialog before executing
- [ ] 4.9 Display current team qualification scores and ranks (read from `Teams` after each recalc)

## 5. Menu registration

- [ ] 5.1 Add "Składy drużyn" entry to `menu.php` under `$ret['QUAL']`, pointing to `Modules/Sets/PL/Teams/ManualTeams.php`

## 6. Setup script updates — EvMaxTeamPerson=4

- [ ] 6.1 In `Setup_1_PL.php`: update `EvMaxTeamPerson` to `4` for all `CreateEventNew()` calls where `EvTeamEvent=1`
- [ ] 6.2 In `Setup_3_PL.php`: update `EvMaxTeamPerson` to `4` for all team event calls
- [ ] 6.3 In `Setup_6_PL.php`: update `EvMaxTeamPerson` to `4` for all team event calls
