## ADDED Requirements

### Requirement: Operator can create a qualification team roster
The system SHALL allow an operator to manually create a qualification team roster for any team event in the current PL tournament, specifying the club (country), sub-team number, and 2–4 athletes. The roster SHALL be persisted in `PLTeamDeclaration` and immediately synced to `Teams` + `TeamComponent`.

#### Scenario: Save a 3-athlete roster
- **WHEN** operator selects a team event, a club, and exactly 3 athletes, then saves
- **THEN** one row appears in `PLTeamDeclaration` per athlete, one row in `Teams` (TeFinEvent=0), and three rows in `TeamComponent` (TcOrder 1–3, TcFinEvent=0)

#### Scenario: Save a 4-athlete roster
- **WHEN** operator selects a team event, a club, and exactly 4 athletes, then saves
- **THEN** one row appears in `PLTeamDeclaration` per athlete, one row in `Teams`, and four rows in `TeamComponent` (TcOrder 1–4)

#### Scenario: Roster entry before qualification scores exist
- **WHEN** operator saves a roster where all selected athletes have QuScore=0
- **THEN** the roster is saved without error; team appears unranked (TeScore=0) until qualification scores are entered

### Requirement: Operator can create multiple teams per club per event
The system SHALL support sub-team numbering (`TeSubTeam`) so that a single club can have more than one team in the same event/category.

#### Scenario: Add second team for a club
- **WHEN** operator creates a second roster for the same club and event with sub-team = 2
- **THEN** a separate team row exists in `Teams` with TeSubTeam=2, distinct from sub-team 1

#### Scenario: Athlete cannot appear in two sub-teams of the same club/event
- **WHEN** operator attempts to add an athlete already assigned to sub-team 1 of the same club/event into sub-team 2
- **THEN** the system rejects the save with an error message

### Requirement: Operator can delete a qualification team roster
The system SHALL allow deletion of an entire team roster (all athletes for a given tournament/event/club/sub-team).

#### Scenario: Delete a roster
- **WHEN** operator deletes a team
- **THEN** all `PLTeamDeclaration` rows for that team are deleted, and the corresponding `Teams` + `TeamComponent` rows are removed

### Requirement: Operator can restore rosters after accidental core overwrite
The system SHALL provide a "Przywróć skład" (restore) action that re-syncs all team rosters from `PLTeamDeclaration` into `Teams` + `TeamComponent` for the current tournament.

#### Scenario: Restore after MakeTeams overwrites
- **WHEN** ianseo's core `MakeTeams` runs and deletes manually entered teams, and the operator clicks "Przywróć skład"
- **THEN** all teams declared in `PLTeamDeclaration` are re-inserted into `Teams` + `TeamComponent`, and team qualification ranking is recalculated

### Requirement: Rank recalculation runs automatically after roster changes
The system SHALL trigger `Obj_RankFactory::create('DivClassTeam')→calculate()` automatically whenever a team roster is saved or deleted, without requiring additional operator action.

#### Scenario: Automatic recalc on save
- **WHEN** operator saves a roster that includes at least one athlete with a non-zero qualification score
- **THEN** `Teams.TeScore`, `TeGold`, `TeXnine`, and `TeRank` are updated before the AJAX response is returned

### Requirement: Team qualification score uses best-3-of-4 rule
For teams with 4 athletes in `TeamComponent`, the system SHALL compute the team qualification score as the sum of the best 3 individual qualification scores (ordered by QuScore DESC, QuGold DESC, QuXnine DESC). For 3-athlete teams, all 3 scores are summed.

#### Scenario: 4-athlete team scoring
- **WHEN** rank calculation runs for a team with 4 athletes having scores 650, 630, 620, 590
- **THEN** `Teams.TeScore` = 650+630+620 = 1900 (the lowest score is excluded)

#### Scenario: 3-athlete team scoring
- **WHEN** rank calculation runs for a team with 3 athletes having scores 650, 630, 620
- **THEN** `Teams.TeScore` = 650+630+620 = 1900 (all 3 are summed)

#### Scenario: Pre-qual team (all scores zero)
- **WHEN** rank calculation runs for a team where all athletes have QuScore=0
- **THEN** `Teams.TeScore` = 0 and the team is excluded from ranked output (consistent with core behaviour `WHERE TeScore<>0`)

### Requirement: Only athletes eligible for the event can be added to a team
The system SHALL only offer athletes who are registered in the selected division/class event (`EnAthlete=1`, `EnTeamClEvent=1`, `EnStatus<=1`) when building a roster.

#### Scenario: Ineligible athlete not shown
- **WHEN** operator opens the athlete picker for Recurve Men team event
- **THEN** only athletes registered in Recurve Men with active status are listed

### Requirement: PLTeamDeclaration table is auto-installed
The system SHALL create the `PLTeamDeclaration` table on the first page load if it does not exist, without requiring changes to ianseo's install scripts.

#### Scenario: First load on a new installation
- **WHEN** `ManualTeams.php` is loaded and `PLTeamDeclaration` does not exist
- **THEN** the table is created and the page renders without error
