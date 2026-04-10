## MODIFIED Requirements

### Requirement: Same-round losers are sub-ranked by average arrow value, shoot-off average, and qualification score

Athletes, teams, and mixed teams eliminated in the same round of the
bracket SHALL be assigned unique sequential places. The ordering within the
group SHALL be determined by the following criteria in order (§2.6.6.2):

1. Average arrow value in the last match (total match score ÷ arrows shot,
   shoot-off excluded) — higher is better
2. Average arrow value in the shoot-off (total shoot-off score ÷ shoot-off
   arrows; 0 when no shoot-off occurred) — higher is better
3. Qualification score (total points from the qualification round) — higher
   is better
4. Athletes share a position only when all three criteria are identical

The same criteria apply regardless of match format (set system for R, B;
cumulative for C). The match score used in criterion 1 is always the
cumulative arrow total (`FinScore`), not set points.

This replaces the previous criteria of (match score total → qualification rank).

#### Scenario: Two QF losers differ by match average

- **WHEN** two athletes are eliminated in the quarter-finals with different
  average arrow values in their respective matches
- **THEN** the athlete with the higher average is ranked above the other,
  and both receive unique places (e.g., 5th and 6th)

#### Scenario: QF losers tied on match average, differ by shoot-off average

- **WHEN** two quarter-final losers have the same average match arrow value
  but different shoot-off averages
- **THEN** the athlete with the higher shoot-off average is ranked higher

#### Scenario: QF loser had no shoot-off

- **WHEN** a quarter-final match ended without a shoot-off (decisive result)
- **THEN** that athlete's shoot-off average is treated as 0 for tiebreaking
  purposes

#### Scenario: Two losers tied on match and shoot-off average, differ by qual score

- **WHEN** two athletes have identical match averages and identical
  shoot-off averages (or both 0) but different qualification scores
- **THEN** the athlete with the higher qualification score is ranked higher

#### Scenario: All three criteria identical

- **WHEN** two athletes have identical match average, shoot-off average,
  and qualification score
- **THEN** both athletes share the same place

#### Scenario: Team bracket sub-ranking

- **WHEN** teams are eliminated in the same round
- **THEN** the same three criteria are applied using team match scores
  and team qualification score (`TeScore`)

#### Scenario: Average written to Finals table

- **WHEN** ranking is calculated for any elimination phase
- **THEN** `FinAverageMatch` and `FinAverageTie` are written to the
  `Finals` table for every participant in that phase (including gold,
  bronze, and semifinal participants)
