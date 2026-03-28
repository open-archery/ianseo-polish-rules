## ADDED Requirements

### Requirement: Diploma generation from combined ranking
The system SHALL generate individual PDF diplomas for athletes ranked within the configured place range in the combined ranking. Each athlete receives one diploma per division+class section in which they appear.

#### Scenario: Generate diplomas for top-3 athletes
- **WHEN** organizer selects two tournaments, enters a diploma date, and clicks "Generuj dyplomy"
- **THEN** the system generates a PDF with one diploma page per athlete ranked 1–3 (or configured range) per div+class section

#### Scenario: Generate diplomas with only one tournament
- **WHEN** organizer selects only Tournament 1 (Tournament 2 left blank) and clicks "Generuj dyplomy"
- **THEN** the system generates diplomas based on the single-tournament combined ranking

#### Scenario: No athletes in place range
- **WHEN** no athletes fall within the configured place range across all sections
- **THEN** the system SHALL display a Polish error message and not generate a PDF

### Requirement: Diploma content from combined ranking data
Each diploma SHALL display the athlete's name, club, rank, division+class label, competition name, date, location, head judge, and organizer. Title lines SHALL NOT appear on combined ranking diplomas.

#### Scenario: Diploma fields populated correctly
- **WHEN** a diploma is generated for rank 1 in "Recurve Mężczyźni"
- **THEN** the diploma shows: athlete name, club, "I miejsca", competition name from diploma config, the date entered on the form, location from diploma config, and "Recurve Mężczyźni" as the category

#### Scenario: Title line absent
- **WHEN** a diploma is generated for rank 1
- **THEN** no "i zdobywa tytuł..." line appears on the diploma

### Requirement: Diploma date entered separately on the form
The system SHALL accept a diploma date as a separate text input on the combined ranking page. This date SHALL be used on the diploma instead of the date from `PLDiplomaConfig`.

#### Scenario: Date field required
- **WHEN** organizer clicks "Generuj dyplomy" without entering a date
- **THEN** the system SHALL not proceed and SHALL indicate the date field is required

#### Scenario: Date appears on diploma
- **WHEN** organizer enters "22.03.2026" as the diploma date
- **THEN** "22.03.2026" appears in the date/location line at the bottom of each diploma

### Requirement: Diploma config reused from active session tournament
The system SHALL read competition name, location, head judge, organizer, body text, place range (PlaceFrom/PlaceTo) from `PLDiplomaConfig` for the active session tournament.

#### Scenario: Config missing warning
- **WHEN** diploma config has not been set up for the active tournament
- **THEN** the system SHALL show a warning consistent with the Diplomas module warning

#### Scenario: Place range from config applied
- **WHEN** diploma config has PlaceFrom=1, PlaceTo=3
- **THEN** only athletes ranked 1, 2, or 3 receive diplomas
