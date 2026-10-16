## MODIFIED Requirements

### Requirement: Team events are configured with a maximum roster size of 4
Setup scripts SHALL set `EvMaxTeamPerson=4` for all team events created by Setup_1_PL.php, Setup_3_PL.php, and Setup_6_PL.php. This allows ianseo's finals team management UI (`ChangeComponents.php`) to accommodate 4-person rosters with per-end substitution.

#### Scenario: New tournament created with PL ruleset
- **WHEN** an organiser creates a new tournament using any of the three PL setup scripts
- **THEN** all team events in that tournament have `EvMaxTeamPerson=4` in the `Events` table
