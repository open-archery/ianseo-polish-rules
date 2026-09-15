## ADDED Requirements

### Requirement: Senior class age range has no upper bound

The system SHALL create Senior M/W classes (`M`, `W`) with an open-ended upper
age bound on every TourType that offers them (1, 3, 6, 37), so that an archer
aged 50 or older still resolves to a class even in a tournament where no
Masters classes were created (Masters is an opt-in `SetMasterClass` preset,
TourType 3 only). Widening Senior's age ceiling SHALL NOT change which class a
narrowest-range match picks when Masters classes are present in the same
tournament — every Masters band remains narrower than Senior's range and wins
on its own age window.

#### Scenario: 50+ archer resolves to Senior when no Masters classes exist

- **WHEN** an archer aged 55 is auto-resolved to a class (`pl_resolve_age_class`) in a tournament created without the `SetMasterClass` preset (e.g. TourType 1, TourType 6, or TourType 3 under `SetSeniorClass`/`SetAllClass` without Masters)
- **THEN** the archer resolves to Senior M/W, not "no class found"

#### Scenario: Masters band still wins when Masters classes exist

- **WHEN** an archer aged 55 is auto-resolved to a class in a TourType 3 tournament created under `SetMasterClass` (both Senior and the `50M`/`50W` Masters band exist)
- **THEN** the archer resolves to the `50M`/`50W` Masters band, not Senior — the narrowest matching age range still wins

### Requirement: Upward class eligibility is restricted below U21

The `ClValidClass` upward-eligibility chain (which classes an archer's entry
may be reassigned to for event participation) SHALL only let U21 and U24
classes reach Senior (`M`/`W`). U18 SHALL be allowed to reach U21 only (one
tier up), never Senior directly. U12 and U15 SHALL be self-only chains with no
upward eligibility at all, matching the existing self-only pattern already
used by Masters bands and PU12 (a parallel track, not a step in the main
progression).

#### Scenario: U12 has no upward eligibility

- **WHEN** a `U12M` archer's assignable-class options are read from `ClValidClass`
- **THEN** the only assignable class is `U12M`

#### Scenario: U15 has no upward eligibility

- **WHEN** a `U15W` archer's assignable-class options are read from `ClValidClass`
- **THEN** the only assignable class is `U15W`

#### Scenario: U18 may opt up to U21 but not to Senior

- **WHEN** a `U18M` archer's assignable-class options are read from `ClValidClass`
- **THEN** the assignable classes are `U18M` and `U21M`
- **AND** `M` (Senior) is not among them

#### Scenario: U21 and U24 remain able to opt up to Senior

- **WHEN** a `U21M` or `U24W` archer's assignable-class options are read from `ClValidClass`
- **THEN** the assignable classes are `U21M, M` (for U21M) and `U24W, W` (for U24W), unchanged from before this change
