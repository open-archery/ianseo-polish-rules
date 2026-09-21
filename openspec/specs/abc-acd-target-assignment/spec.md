# ABC/ACD Target Assignment Specification

## Purpose

Assigns athletes to target bosses using the alternating ABC/ACD slot pattern
PZŁucz requires for 4-athlete-per-target sessions, grouping by class and/or
division, keeping clubs together on consecutive bosses, and balancing
cross-class club waves — with preview-before-save and an unassigned-athlete
report for overflow.

## Requirements

### Requirement: ABC/ACD slot pattern
The system SHALL assign target positions using a staggered alternating pattern based on the absolute boss number: odd-numbered bosses use positions A, B, C (D left empty); even-numbered bosses use positions A, C, D (B left empty). The session MUST be configured with `SesAth4Target = 4`.

#### Scenario: Odd boss gets ABC slots
- **WHEN** building the slot list and the current boss number is odd
- **THEN** the system appends slots `{N}A`, `{N}B`, `{N}C` for boss N and omits `{N}D`

#### Scenario: Even boss gets ACD slots
- **WHEN** building the slot list and the current boss number is even
- **THEN** the system appends slots `{N}A`, `{N}C`, `{N}D` for boss N and omits `{N}B`

#### Scenario: Pattern is field-global
- **WHEN** two classes are assigned to abutting target ranges (e.g. 1–5 and 6–10)
- **THEN** boss parity is determined by the boss number, not the position within each range, so boss 6 is always ACD regardless of which class was assigned first

---

### Requirement: One class at a time
The system SHALL accept a single division/class filter (text input matching `CONCAT(TRIM(EnDivision), TRIM(EnClass))`) and assign only athletes matching that filter in the selected session. The system SHALL additionally accept two independent flags, "separate divisions" and "separate classes" (both enabled by default), that control whether the matched athletes are split into groups by `EnDivision` and/or `EnClass` before assignment. The two flags are independently combinable, matching native ianseo's own `GroupByDiv`/`GroupByClass` grouping semantics: enabling only "separate classes" groups by class regardless of division; enabling only "separate divisions" groups by division regardless of class; enabling both groups by the combination of the two; enabling neither pools every athlete matching the filter into a single group, as today.

#### Scenario: Single class assigned
- **WHEN** the user enters class `"RMO"` and clicks assign
- **THEN** only athletes whose combined division+class equals `RMO` are assigned; athletes of other classes are unaffected

#### Scenario: Both flags disabled pools everything matching the filter
- **WHEN** both "separate divisions" and "separate classes" are unchecked and `Event` matches more than one division/class combination
- **THEN** all matching athletes are pooled into a single group and assigned as one unit, exactly as before this feature existed

#### Scenario: Separate classes only groups by class
- **WHEN** "separate classes" is checked and "separate divisions" is unchecked, and `Event` matches both `RMO` and `CMO`
- **THEN** athletes are split into a group per distinct `EnClass` value; `RMO` and `CMO` athletes (same class `MO`, different division) remain in the same group, but a class-`MO` group is never merged with a class-`WO` group

#### Scenario: Separate divisions only groups by division
- **WHEN** "separate divisions" is checked and "separate classes" is unchecked, and `Event` matches both `RMO` and `RWO`
- **THEN** athletes are split into a group per distinct `EnDivision` value; `RMO` and `RWO` (same division `R`, different class) remain in the same group, but division `R` is never merged with division `C`

#### Scenario: Both flags enabled groups by division and class together
- **WHEN** both flags are checked and `Event` matches `RMO`, `RWO`, and `CMO`
- **THEN** each distinct division+class combination forms its own group: `RMO`, `RWO`, and `CMO` are each assigned independently

---

### Requirement: Club grouping — one per boss, consecutive bosses
The system SHALL group athletes by `EnCountry` (used as club code in domestic tournaments), sort clubs by size descending (largest first), and assign same-club athletes to the same index-within-boss across consecutive bosses such that at most one athlete from a given club appears on each boss.

#### Scenario: Club athletes land on consecutive bosses
- **WHEN** club `AZS` has 3 athletes and is the largest club in the class
- **THEN** the three athletes are assigned to the A slot on three consecutive bosses (e.g. T1A, T2A, T3A)

#### Scenario: Two clubs share the same bosses at different positions
- **WHEN** club `AZS` (3 athletes) and club `ŁLKS` (3 athletes) are in the same class
- **THEN** AZS occupies one column across bosses 1–3 and ŁLKS occupies another column across the same bosses, with no boss having two athletes from the same club

#### Scenario: Club boundary advances to next A slot
- **WHEN** the previous club's column is exhausted
- **THEN** the next club starts at the first available `A` slot, skipping any remaining slots in the previous column

---

### Requirement: Erase before save
The system SHALL erase all existing target assignments (`QuTarget`, `QuLetter`) for the selected class and session before writing the new assignment.

#### Scenario: Fresh assignment overwrites previous
- **WHEN** the user submits the save form for class `RMO` session 1
- **THEN** all existing `QuTarget`/`QuLetter` values for `RMO` athletes in session 1 are cleared and the new ABC/ACD assignment is written

---

### Requirement: Preview before save
The system SHALL display the proposed assignment (athlete name, club, target+letter) without writing to the database when the user submits without the save flag.

#### Scenario: Preview shows proposed layout
- **WHEN** the user submits the form without checking "Zapisz"
- **THEN** the page displays the proposed slot→athlete mapping and an unassigned count but makes no database changes

#### Scenario: Save commits the assignment
- **WHEN** the user submits the form with "Zapisz" checked
- **THEN** the assignment is erased for that class+session and the new assignment is written to `Qualifications` and `Entries`

---

### Requirement: SesAth4Target validation
The system SHALL check that the selected session has `SesAth4Target = 4` and display a warning if it does not, blocking assignment.

#### Scenario: Wrong session configuration blocked
- **WHEN** the user selects a session with `SesAth4Target = 3`
- **THEN** the system displays a Polish-language warning that the session must be set to 4 archers per boss and does not proceed with assignment

---

### Requirement: Unassigned athlete report
The system SHALL list any athletes that could not be assigned (e.g. the target range has fewer slots than athletes) after the preview or save.

#### Scenario: Overflow athletes reported
- **WHEN** there are more athletes in the selected class than slots in the target range
- **THEN** the unassigned athletes are listed by name and club beneath the assignment preview

---

### Requirement: Cross-class wave balance within a session
When assigning a class, the system SHALL compute each club's existing wave1 (letters A/B) vs wave2 (letters C/D) tally from other classes already saved in the same `QuSession`, and SHALL bias which column (A vs C) a club's block starts in toward reducing that club's imbalance. Behavior within a single class run (which club occupies which column when no prior session history distinguishes them) is unchanged from the existing largest-club-first default. Balancing never considers other `QuSession` values. When a single request assigns multiple groups (because "separate divisions" and/or "separate classes" split the matched athletes), the tally used by each group after the first SHALL also include the assignments already computed for earlier groups within that same request, whether or not the request will end up saving them.

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

#### Scenario: Unsaved preview does not affect the tally across separate requests
- **WHEN** a class has been previewed (not saved) in a prior, separate request
- **THEN** that preview's slot assignments do not count toward any club's wave tally for a class assigned in a later request in the same session

#### Scenario: Groups within the same multi-group request do affect each other's tally
- **WHEN** "separate classes" splits one request into groups `RMO` then `RWO` (in that order), club `AZS` is the largest club in both, and neither group has been saved yet
- **THEN** `RWO`'s tally already reflects `RMO`'s just-computed (not-yet-saved) column choice for `AZS` within that same request, so `AZS` is biased toward the opposite column for `RWO`

#### Scenario: Overflow clubs are biased too
- **WHEN** a club placed via the "first column that fits" overflow rule (not the largest or second-largest club in the class) already has an imbalanced wave tally from earlier classes in the same session
- **THEN** the system evaluates the columns in the order matching that club's greater need: remaining C before remaining A when the club is wave1-heavy, or remaining A before remaining C otherwise

#### Scenario: Bias also reorders the B/D fallback columns
- **WHEN** a club placed via the overflow rule is wave1-heavy and does not fit into a single remaining A or C column
- **THEN** the system tries remaining D (wave2) before remaining B (wave1), and the slot-by-slot fallback (used when no single column has enough room) walks columns in that same club-specific order

---

### Requirement: Boss-aligned group carving
When the matched athletes are split into more than one group, the system SHALL allocate each group its own contiguous sub-range of bosses within `TgtFrom`–`TgtTo`, sized to hold at least that group's athlete count, and SHALL never split a single boss's letters between two different groups. Groups SHALL be carved in the order given by the existing `Divisions.DivViewOrder`/`Classes.ClViewOrder` display order (matching native ianseo's own group ordering convention). Any boss capacity left over when a group's carved range exceeds what its athletes fill remains unused rather than being offered to the next group.

#### Scenario: Two groups never share a boss
- **WHEN** "separate classes" splits a request into groups `RMO` (5 athletes) and `RWO` (2 athletes) within range 1–10
- **THEN** `RMO` is carved a boss-aligned sub-range large enough for 5 athletes and `RWO` starts at the next unused boss, never at a partially-used boss from `RMO`'s range

#### Scenario: Groups are ordered by view order
- **WHEN** the matched filter spans classes `RWO` and `RMO` (in that order of appearance in the query) but `RMO` has a lower `ClViewOrder` than `RWO`
- **THEN** `RMO` is carved the earlier (lower-numbered) sub-range of the target range and `RWO` the later one

#### Scenario: Range too small for all groups reports overflow
- **WHEN** the combined boss-aligned space required by all groups exceeds the bosses available in `TgtFrom`–`TgtTo`
- **THEN** the groups that fit are assigned normally and the athletes belonging to groups (or the portion of a group) that do not fit are listed in the existing unassigned-athlete report
