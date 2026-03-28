## ADDED Requirements

### Requirement: QF tiebreak data storage
The system SHALL persist 10/X/9 arrow counts for Compound athletes per tournament in a `PLQfTiebreak` table. The table SHALL be auto-installed on first use. Each (tournament, athlete licence) pair SHALL have at most one stored count. Submitting a count for an already-stored pair SHALL overwrite the previous value.

#### Scenario: Auto-install on first page load
- **WHEN** the combined ranking page is loaded and `PLQfTiebreak` does not exist
- **THEN** the system SHALL create the table automatically without error

#### Scenario: Store count for athlete
- **WHEN** the operator submits a 10/X/9 count for a given athlete and tournament
- **THEN** the system SHALL persist the count and associate it with that athlete's licence and tournament

#### Scenario: Overwrite existing count
- **WHEN** the operator submits a new count for an athlete+tournament pair that already has a stored count
- **THEN** the stored count SHALL be updated to the new value

---

### Requirement: QF tie detection
The system SHALL detect Compound athletes within the same division+class and tournament who share the same elimination place in the QF-loser range (places 5–8) AND have a confirmed `Finals` bracket entry. Each such group of two or more athletes with the same place SHALL be reported as an unresolved tie when no 10/X/9 count is stored for at least one athlete in the group.

#### Scenario: Two athletes share QF elimination place
- **WHEN** two Compound athletes in the same division+class both have `elim_rank = 6` in Tournament 1 and both have `Finals` rows
- **THEN** the system SHALL flag them as a QF tie requiring 10/X/9 data for Tournament 1

#### Scenario: Tie fully resolved
- **WHEN** all athletes in a tied group have stored 10/X/9 counts for that tournament
- **THEN** the system SHALL NOT flag them as unresolved

#### Scenario: Non-QF place not flagged
- **WHEN** two Compound athletes share `elim_rank = 3` (bronze medal place)
- **THEN** the system SHALL NOT flag them as a QF tie

---

### Requirement: Warning and data entry on combined ranking page
When unresolved QF ties are detected for the selected tournaments, the combined ranking page SHALL display a warning banner listing each ambiguous (athlete, tournament) pair. The page SHALL provide an input field per flagged athlete+tournament pair allowing the operator to enter the 10/X/9 count and save it. The page SHALL reload and recompute after saving.

#### Scenario: Warning shown when tie detected
- **WHEN** the combined ranking is computed and an unresolved QF tie exists
- **THEN** the page SHALL display a warning banner in Polish identifying the tied athletes and the tournament

#### Scenario: Form saves count and reloads
- **WHEN** the operator enters a count and submits the form
- **THEN** the count SHALL be saved to `PLQfTiebreak` and the page SHALL reload with an updated ranking

#### Scenario: No warning when no ties
- **WHEN** no unresolved QF ties exist
- **THEN** no warning banner or data-entry form SHALL be shown

---

### Requirement: Corrected elimination place assignment
When 10/X/9 counts are stored for all athletes in a tied group, the system SHALL reassign their elimination places in descending count order before computing points. The athlete with the highest count SHALL receive the lowest (best) place in the tied range.

#### Scenario: Counts resolve a two-athlete tie
- **WHEN** two Compound athletes both have `elim_rank = 5` and stored counts of 8 and 6
- **THEN** the athlete with count 8 SHALL receive place 5 and the athlete with count 6 SHALL receive place 6, and their elimination points SHALL reflect those corrected places

#### Scenario: Partial counts — counted athlete ranks above uncounted
- **WHEN** two athletes share a QF place and only one has a stored count
- **THEN** the athlete with the stored count SHALL rank above the athlete without a count within that tied group

---

### Requirement: PDF unresolved-tie marker
When a section of the combined ranking PDF contains athletes whose QF tie could not be resolved (missing 10/X/9 data), the PDF SHALL include a footnote for that section indicating the tie is unresolved.

#### Scenario: Footnote shown for unresolved tie
- **WHEN** the PDF is generated and a Compound section contains an unresolved QF tie
- **THEN** the affected ranks SHALL be marked with an asterisk and a footnote SHALL read "* Remis nierozstrzygnięty — brak danych 10/X/9"

#### Scenario: No footnote when all ties resolved
- **WHEN** all QF ties in a section are resolved by stored counts
- **THEN** no footnote SHALL appear in that section
