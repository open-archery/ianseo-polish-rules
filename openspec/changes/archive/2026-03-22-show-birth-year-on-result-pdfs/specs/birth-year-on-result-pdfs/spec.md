## ADDED Requirements

### Requirement: Birth year column on Individual Qualification PDF
The Individual Qualification ranking PDF for PL tournaments SHALL display the athlete's year of birth as a dedicated 10mm column labelled "Rok ur.", positioned immediately after the "Nr lic." column. The year SHALL be derived from `EnDob` (format `YYYY-MM-DD`). The cell SHALL render blank when `EnDob` is `0` (unknown) or when the year portion equals `1900` (placeholder).

#### Scenario: Qualification PDF shows birth year
- **WHEN** a PL tournament's Individual Qualification ranking PDF is generated
- **THEN** each athlete row SHALL include their 4-digit birth year in the "Rok ur." column

#### Scenario: Unknown DOB renders as blank on qualification PDF
- **WHEN** an athlete's `EnDob` is `0` or year portion is `1900`
- **THEN** the "Rok ur." cell SHALL render as empty (no error, no placeholder text)

#### Scenario: Non-PL tournaments unaffected
- **WHEN** a non-PL tournament's Individual Qualification ranking PDF is generated
- **THEN** no "Rok ur." column SHALL appear

### Requirement: Birth year column on Individual Finals ranking PDF
The Individual Finals ranking PDF for PL tournaments SHALL display the athlete's year of birth as a dedicated 10mm column labelled "Rok ur.", positioned immediately after the "Nr lic." column. The same blank-rendering rules for `EnDob = 0` and year `1900` apply.

#### Scenario: Finals PDF shows birth year
- **WHEN** a PL tournament's Individual Finals ranking PDF is generated
- **THEN** each athlete row SHALL include their 4-digit birth year in the "Rok ur." column

#### Scenario: Unknown DOB renders as blank on finals PDF
- **WHEN** an athlete's `EnDob` is `0` or year portion is `1900`
- **THEN** the "Rok ur." cell SHALL render as empty

#### Scenario: Non-PL tournaments unaffected
- **WHEN** a non-PL tournament's Individual Finals ranking PDF is generated
- **THEN** no "Rok ur." column SHALL appear

### Requirement: Birth year data available in qualification rank items
The PL qualification ranking data object SHALL expose each athlete's `birthdate` field so that the PDF chunk can render it. The value SHALL be the raw `EnDob` string from the `Entries` table.

#### Scenario: birthdate present after read
- **WHEN** `Obj_Rank_DivClass_PL::read()` completes for a PL tournament
- **THEN** every item in `rankData['sections'][*]['items']` SHALL contain a `birthdate` key

#### Scenario: Athletes with no DOB have empty birthdate
- **WHEN** an athlete has `EnDob = 0` in the database
- **THEN** the `birthdate` field in their item SHALL be `'0'` or empty (not null/missing)
