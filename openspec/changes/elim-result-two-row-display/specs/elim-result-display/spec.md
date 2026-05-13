## ADDED Requirements

### Requirement: Each phase cell shows match score and arrow average
Each finals phase cell in the elimination results PDF SHALL display two pieces
of information stacked vertically:
1. The **match score** on the upper line — set points total for set-system
   events (R, B), or cumulative match score for compound (C).
2. The **arrow average** on the lower line — the average arrow value for the
   match, formatted to 3 decimal places.

Set-system detection: if `$v['setPoints']` is non-empty, the event uses the
set system and `$v['setScore']` is displayed; otherwise `$v['score']` is
displayed.

#### Scenario: Set-system match (recurve/barebow), no tiebreak
- **WHEN** the results PDF is rendered for a phase cell where `setPoints` is
  non-empty, `avgMatch` is set, and `avgTie` is 0
- **THEN** the top sub-row shows `setScore` (e.g. `6`) right-aligned in the
  left 7px sub-cell
- **THEN** the top-right 5px sub-cell is empty
- **THEN** the bottom sub-row shows `avgMatch` formatted to 3 dp (e.g. `6.933`)
  right-aligned in the left 7px sub-cell
- **THEN** the bottom-right 5px sub-cell is empty

#### Scenario: Compound match (cumulative), no tiebreak
- **WHEN** the results PDF is rendered for a phase cell where `setPoints` is
  empty/null and `avgTie` is 0
- **THEN** the top sub-row shows `score` (cumulative total, e.g. `158`)
  right-aligned in the left 7px sub-cell
- **THEN** the bottom sub-row shows `avgMatch` formatted to 3 dp

### Requirement: Tiebreak annotation on the last phase cell
On the **last** finals phase for an archer, if a shoot-off occurred
(`avgTie > 0`), the phase cell SHALL additionally display tiebreak information:

- **Top-right** (5px): label `T.X` where X is the tiebreak arrow value(s)
  — derived from `$v['tiebreak']` with pipe characters removed.
- **Bottom-right** (5px): `avgTie` formatted to 3 decimal places.

For all phases that are NOT the last phase, the right sub-cells SHALL remain
empty regardless of tiebreak data.

#### Scenario: Last phase cell with tiebreak
- **WHEN** it is the last finals phase for an archer AND `avgTie > 0`
- **THEN** the top-right sub-cell shows `T.` followed by the tiebreak arrow
  value(s) (e.g. `T.6`, `T.X9`)
- **THEN** the bottom-right sub-cell shows `avgTie` to 3 dp (e.g. `7.333`)

#### Scenario: Non-last phase cell, even if tiebreak data present
- **WHEN** it is NOT the last finals phase for an archer
- **THEN** both right sub-cells are empty

#### Scenario: Last phase, no tiebreak
- **WHEN** it is the last finals phase AND `avgTie` is 0
- **THEN** both right sub-cells are empty

### Requirement: Bye cell spans full row height
When an archer received a bye in a phase, the phase cell SHALL display the
bye label (`-Wolne-`) spanning the full double row height (8px).

#### Scenario: Bye cell
- **WHEN** `$v['tie'] == 2` for a phase
- **THEN** a single 12×8 cell is drawn containing `-Wolne-`, with no sub-row
  division

### Requirement: Non-finals cells double in height
All cells to the left of the finals columns (rank, athlete name, bib, birth
year, country code, country name, qualification score, elimination rounds)
SHALL have their row height doubled from 4 to 8 to match the finals cells.

#### Scenario: Data row rendered with doubled height
- **WHEN** a data row is rendered
- **THEN** all cells in the row are 8px tall (non-finals cells span the full 8;
  finals cells contain two 4px sub-rows)
