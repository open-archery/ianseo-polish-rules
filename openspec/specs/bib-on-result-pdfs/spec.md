### Requirement: Bib column on Individual Qualification PDF
The Individual Qualification ranking PDF for PL tournaments SHALL display the athlete's licence number (bib) as a dedicated column labelled "Nr lic.", positioned between the category columns (ageclass/subclass) and the country/club column.

#### Scenario: Qualification PDF shows licence number
- **WHEN** a PL tournament's Individual Qualification ranking PDF is generated
- **THEN** each athlete row SHALL include their licence number in the "Nr lic." column

#### Scenario: Empty bib renders as blank
- **WHEN** an athlete has no bib/EnCode assigned
- **THEN** the "Nr lic." cell SHALL render as empty (no error, no placeholder)

#### Scenario: Non-PL tournaments unaffected
- **WHEN** a non-PL tournament's Individual Qualification ranking PDF is generated
- **THEN** no "Nr lic." column SHALL appear (core chunk used)

### Requirement: Bib column on Individual Finals ranking PDF
The Individual Finals ranking PDF for PL tournaments SHALL display the athlete's licence number (bib) as a dedicated column labelled "Nr lic.", positioned between the athlete name column and the country/club column.

#### Scenario: Finals PDF shows licence number
- **WHEN** a PL tournament's Individual Finals ranking PDF is generated
- **THEN** each athlete row SHALL include their licence number in the "Nr lic." column

#### Scenario: Empty bib renders as blank
- **WHEN** an athlete has no bib/EnCode assigned
- **THEN** the "Nr lic." cell SHALL render as empty

#### Scenario: Non-PL tournaments unaffected
- **WHEN** a non-PL tournament's Individual Finals ranking PDF is generated
- **THEN** no "Nr lic." column SHALL appear (core chunk used)
