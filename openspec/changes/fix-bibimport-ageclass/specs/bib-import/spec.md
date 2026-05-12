## ADDED Requirements

### Requirement: Entry creation sets both age-class columns
When creating an `Entries` row the system SHALL write the resolved class ID to
**both** `EnClass` and `EnAgeClass`. ianseo uses these as distinct columns:
`EnAgeClass` holds the athlete's age class as displayed in the participant list
(`Classes.ClId`, e.g. `'U18M'`); `EnClass` holds the competition class used for
ranking (a value from `Classes.ClValidClass`). In PL's class configuration
`ClId` is always the first element of `ClValidClass`, so both columns receive the
same value for an athlete competing in their natural class. If class resolution
fails (Step 3 returns no match), both columns SHALL be set to an empty string.

#### Scenario: Resolved age class — both columns populated
- **WHEN** `pl_bibimport_resolve_class()` returns a `Classes` row with `ClId = 'U18M'`
- **THEN** the `Entries` INSERT sets `EnClass = 'U18M'` AND `EnAgeClass = 'U18M'`
- **AND** the athlete's age class is visible in the participant list without manual intervention

#### Scenario: Unresolved age class — both columns empty
- **WHEN** `pl_bibimport_resolve_class()` returns `null`
- **THEN** the `Entries` INSERT sets `EnClass = ''` AND `EnAgeClass = ''`
- **AND** the athlete appears in the class-unresolved report
