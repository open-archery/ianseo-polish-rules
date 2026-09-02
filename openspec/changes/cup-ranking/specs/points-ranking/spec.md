## MODIFIED Requirements

### Requirement: Individual points assignment
The system SHALL assign points to each athlete based on their place in the classification's rank source, using the classification's rank→points bracket table. Brackets are inclusive ranges: an athlete at place P receives the points of the bracket where `rank_from ≤ P ≤ rank_to`. Athletes with a special rank (DSQ, DNS, DNF — place value ≥ 29999) SHALL receive 0 points. Athletes outside all brackets SHALL receive 0 points and SHALL be omitted from that classification's table.

Omission from the table SHALL be decided by the athlete's **bracket** value, not by their final points: an athlete whose points were zeroed by the cutoff rule SHALL still be listed with 0, because that zero is a result, while an athlete who never matched a bracket — no place, a DSQ/DNS/DNF, or a place beyond the last bracket — SHALL NOT be listed at all. The same rule SHALL apply to the team and mixed tables.

Omitted rows SHALL remain part of the calculation itself, so that consumers of the calculation — the cup classification reads their place and qualification score for its tie-breaks — still see them.

#### Scenario: Place falls within a bracket
- **WHEN** an athlete's place is 5 and the bracket 5-5 awards 13 points
- **THEN** the athlete receives 13 points

#### Scenario: Place inside a shared bracket
- **WHEN** an athlete's place is 12 and the bracket 9-16 awards 5 points
- **THEN** the athlete receives 5 points

#### Scenario: Place outside all brackets
- **WHEN** an athlete's place is 70 and no bracket covers place 70
- **THEN** the athlete receives 0 points and does not appear in that classification's table

#### Scenario: DSQ athlete
- **WHEN** an athlete has a place value ≥ 29999 (DSQ/DNS/DNF)
- **THEN** the athlete receives 0 points regardless of place and does not appear in that classification's table

#### Scenario: Athlete without a result
- **WHEN** an athlete has no place at all (place 0) because they did not finish
- **THEN** the athlete does not appear in that classification's table

#### Scenario: Cutoff-zeroed athlete stays listed
- **WHEN** the cutoff rule zeroes the points of the last athlete in a category
- **THEN** that athlete is still listed, with 0 points

#### Scenario: Empty section omitted
- **WHEN** every subject in a category is omitted from the table
- **THEN** that category's section is not rendered at all
