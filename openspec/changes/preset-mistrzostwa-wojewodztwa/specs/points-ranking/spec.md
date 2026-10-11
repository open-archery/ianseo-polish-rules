## ADDED Requirements

### Requirement: Mistrzostwa Województwa preset
The system SHALL provide a seventh read-only preset, "Mistrzostwa Województwa", that scores the individual classification only and reports a club ranking only.

| # | Preset | Scope | Reports | Cutoff | Source |
|---|---|---|---|---|---|
| 7 | Mistrzostwa Województwa | all | `CLUB` | NO | ELIM |

The preset SHALL declare a single `ind` classification with the bracket table:

| 1 | 2 | 3 |
|---|---|---|
| 3 | 2 | 1 |

Places beyond 3 SHALL score 0 and SHALL NOT appear in the club totals. The preset SHALL declare no team, mixed, `COMBINED` or `VOIVODESHIP` report, so no voivodeship column, table or diploma button is rendered and `PLVoivodeshipMap` is not consulted. Club totals are the uncapped sum of the individual points of the club's athletes across all categories.

#### Scenario: Club total from medallists
- **WHEN** the preset is active and a club's athletes finish 1st in one category, 2nd in another and 3rd in a third
- **THEN** the club total is 6 (3 + 2 + 1)

#### Scenario: Fourth place scores nothing
- **WHEN** an athlete finishes 4th in a category
- **THEN** the athlete receives 0 points and contributes nothing to the club total

#### Scenario: Category without an elimination round
- **WHEN** a category's event has `EvFinalFirstPhase = 0`
- **THEN** that category is scored from qualification ranks and its medallists still earn 3, 2 and 1 points

#### Scenario: Category with an elimination round
- **WHEN** a category's event has an elimination phase
- **THEN** places are read from the final rank (`IndRankFinal`)

#### Scenario: Club report only
- **WHEN** the preset is active
- **THEN** the output contains the club table only, the club table has no "Województwo" column, and no voivodeship diploma button is shown

#### Scenario: No cutoff
- **WHEN** a category has fewer starters than the last bracket place (e.g. 2 athletes)
- **THEN** every athlete placed 1st to 3rd keeps their bracket points
