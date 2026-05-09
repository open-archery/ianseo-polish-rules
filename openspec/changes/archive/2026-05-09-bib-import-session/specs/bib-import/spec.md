## ADDED Requirements

### Requirement: Session selector in import form
The import form SHALL include a mandatory session dropdown listing all
qualification sessions (`SesType = 'Q'`) for the current tournament, ordered
by `SesOrder` ascending. The dropdown SHALL display session number and name
(e.g. "1 – Sesja poranna"). The operator MUST select a session before
submitting — no "unassigned" option is offered.

#### Scenario: Q sessions exist
- **WHEN** the tournament has one or more sessions with `SesType = 'Q'`
- **THEN** the form renders a session dropdown with those sessions listed

#### Scenario: No Q sessions exist
- **WHEN** the tournament has no sessions with `SesType = 'Q'`
- **THEN** the form is hidden and a warning is shown instructing the operator
  to configure sessions before importing

#### Scenario: Session not selected (invalid POST)
- **WHEN** a POST is submitted with a session value that does not match any
  Q session in the tournament
- **THEN** the import is rejected with a validation error and no athletes
  are imported

---

### Requirement: Qualifications row created per imported athlete
For each successfully imported athlete, the system SHALL create a row in
the `Qualifications` table with `QuId = EnId` and `QuSession` set to the
session number selected in the form. This insert SHALL occur inside the same
transaction as the corresponding `Entries` insert.

#### Scenario: Successful import with session selected
- **WHEN** the operator selects a valid Q session and submits a list of
  valid licence numbers
- **THEN** each imported athlete has both an `Entries` row and a
  `Qualifications` row with the correct `QuSession` value

#### Scenario: Transaction rollback on DB error
- **WHEN** any DB write (Entries or Qualifications) fails during the batch
- **THEN** the entire transaction is rolled back, leaving no partial data

---

## REMOVED Requirements

### Requirement: Session assignment is out of scope
**Reason**: Session assignment at import time is now in scope as of this change.
**Migration**: Session is selected in the import form; no manual editing needed
after import to assign athletes to a session.
