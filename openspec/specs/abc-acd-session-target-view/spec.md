# ABC/ACD Session Target View Specification

## Purpose

Gives organisers using the ABC/ACD target assignment tool an at-a-glance, session-wide view of which bosses are already occupied by which division/class and which are still free, without having to infer it from memory or re-run the assignment tool.

## Requirements

### Requirement: Grid reflects the session's real target capacity
The system SHALL derive the set of boss numbers shown in the grid from the selected session's actual configuration (`Session.SesFirstTarget` and `SesTar4Session`), not from any value the user has typed into the assignment form's `TgtFrom`/`TgtTo` fields.

#### Scenario: Grid spans the full session regardless of the typed range
- **WHEN** a session is configured with `SesFirstTarget=1` and `SesTar4Session=40`, and the user has typed `TgtFrom=5`/`TgtTo=10` into the assignment form
- **THEN** the grid displays all 40 bosses of the session, not just bosses 5–10

---

### Requirement: Grid is shown whenever a session is selected
The system SHALL display the grid as soon as a session is selected in the session dropdown, independent of whether `Event`, `TgtFrom`, `TgtTo`, or the save flag have been filled in or submitted.

#### Scenario: Grid appears before any assignment action
- **WHEN** the user selects a session and has not yet entered an `Event` filter or submitted the form
- **THEN** the grid for that session's current saved state is displayed

#### Scenario: Grid updates after a save
- **WHEN** the user assigns and saves a class, and the page reloads
- **THEN** the grid reflects the newly saved assignment for that session

---

### Requirement: One column per boss, with a colored occupancy row per contiguous range
The system SHALL render one table column per boss number in the session, and a second row beneath the boss-number header that uses `colspan` to merge consecutive bosses occupied by the same division+class into a single colored block, labeled with that division+class.

#### Scenario: Consecutive same-class bosses merge into one block
- **WHEN** bosses 1 through 20 are all occupied by class `RU15` and bosses 21 through 23 by class `RU18`
- **THEN** the label row shows one colored, colspan-merged cell spanning columns 1–20 labeled `RU15`, followed by one spanning columns 21–23 labeled `RU18`

#### Scenario: Different classes never merge into one block
- **WHEN** boss 20 is occupied by `RU15` and boss 21 by `RU18`
- **THEN** the two bosses render as separate colored blocks, never merged into one cell even though they are adjacent

---

### Requirement: Free bosses are labeled distinctly
The system SHALL render any boss with no assigned athlete on any letter as part of a distinctly labeled "free" block (e.g. "wolne"), using its own color, following the same consecutive-merge rule as occupied blocks.

#### Scenario: Trailing free bosses are labeled
- **WHEN** bosses 29 through 40 have no assigned athletes
- **THEN** the label row shows one colspan-merged block spanning columns 29–40 labeled as free

---

### Requirement: Mixed-occupancy bosses render distinctly
The system SHALL detect a boss whose assigned letters belong to more than one division/class (partial or inconsistent occupancy) and SHALL render it as its own single-boss cell — never merged with a neighboring block — labeled with the combined set of division/class values present on that boss, and styled with a visually distinct treatment (e.g. a striped/hatched background) rather than a flat color from the normal occupancy palette.

#### Scenario: A boss split between two classes is flagged
- **WHEN** boss 23 has letters A/B assigned to class `RU15` and letters C/D assigned to class `RU18`
- **THEN** boss 23 renders as its own cell labeled `RU15+RU18` with the distinct mixed-occupancy styling, not merged into either neighboring `RU15` or `RU18` block

---

### Requirement: Occupancy colors are keyed by division/class, independent of the club palette
The system SHALL assign colors to division/class labels in the grid from a palette independent of the per-club color palette used elsewhere on the same page (the assignment preview table), so the two views answer different questions without visual collision.

#### Scenario: Same division/class always gets the same color within one page render
- **WHEN** class `RMO` occupies two non-adjacent boss ranges within the same session's grid
- **THEN** both ranges are rendered in the same color, distinct from the color used for any other division/class shown in the same grid
