## ADDED Requirements

### Requirement: QF tie warning on combined ranking page
The combined ranking page SHALL detect and surface unresolved Compound QF place ties for the selected tournaments. When detected, the page SHALL display a warning banner and a data-entry form before allowing PDF generation to proceed. PDF generation SHALL still be possible even when ties are unresolved; unresolved ties are marked in the PDF.

#### Scenario: Warning shown before PDF generation
- **WHEN** the operator selects tournaments and an unresolved QF tie exists in the Compound division
- **THEN** the page SHALL display a warning banner identifying the tied athletes and tournament before the PDF is streamed

#### Scenario: PDF generated with unresolved tie marker
- **WHEN** the operator generates the PDF while an unresolved QF tie remains
- **THEN** the PDF SHALL include the unresolved-tie footnote and generation SHALL NOT be blocked
