## ADDED Requirements

### Requirement: Cross-class wave balance within a session
When assigning a class, the system SHALL compute each club's existing wave1 (letters A/B) vs wave2 (letters C/D) tally from other classes already saved in the same `QuSession`, and SHALL bias which column (A vs C) a club's block starts in toward reducing that club's imbalance. Behavior within a single class run (which club occupies which column when no prior session history distinguishes them) is unchanged from the existing largest-club-first default. Balancing never considers other `QuSession` values.

#### Scenario: First class in a session uses the default order
- **WHEN** a class is assigned in a session with no other class previously saved in it
- **THEN** the largest club is assigned column A and the second-largest column C, exactly as without this feature

#### Scenario: Second class in the same session balances a repeat club
- **WHEN** class `RMO` has already been saved in session 1 with club `AZS` assigned entirely to column A (wave1), and class `RWO` is now assigned in the same session 1 with `AZS` again the largest club
- **THEN** `AZS` is assigned column C (wave2) for `RWO` instead of column A

#### Scenario: Tie or no history keeps the default order
- **WHEN** two clubs present in the class being assigned have equal (including zero) existing wave1/wave2 tallies in the session
- **THEN** the largest club is assigned column A and the second-largest column C, matching today's default

#### Scenario: Single club present is also biased
- **WHEN** only one club has athletes in the class being assigned, and that club already has more wave1 (A/B) than wave2 (C/D) assignments saved elsewhere in the same session
- **THEN** the club is assigned column C for this class instead of the default column A

#### Scenario: Different sessions do not influence each other
- **WHEN** club `AZS` was assigned entirely to column A for class `RMO` in session 1, and class `RWO` is assigned in session 2 with `AZS` again the largest club
- **THEN** `AZS` is assigned column A for `RWO`, since session 2 has no prior history of its own

#### Scenario: Unsaved preview does not affect the tally
- **WHEN** a class has been previewed (not saved) in the current session
- **THEN** that preview's slot assignments do not count toward any club's wave tally for a subsequently assigned class in the same session

#### Scenario: Overflow clubs are biased too
- **WHEN** a club placed via the "first column that fits" overflow rule (not the largest or second-largest club in the class) already has an imbalanced wave tally from earlier classes in the same session
- **THEN** the system searches the column matching that club's greater need (remaining C before remaining A, or vice versa) before falling back to the default remaining-A-then-remaining-C order
