## MODIFIED Requirements

### Requirement: Same-phase tiebreaking criteria (§2.6.6.2)

For archers, teams or mixed teams eliminated in the same phase, the system SHALL order them by, in priority order:

| Priority | Criterion | Source |
| -------- | --------- | ------ |
| 1 | Average arrow value in the match, shoot-off excluded | `FinScore` / `TfScore` ÷ arrows shot |
| 2 | Average arrow value in the shoot-off; 0 when no shoot-off was needed | `FinTiebreak` / `TfTiebreak` |
| 3 | **Qualification rank, ascending** | `Individuals.IndRank` / `Teams.TeRank` |
| 4 | Share the position | last resort |

Criterion 3 SHALL use the qualification **rank**, not the qualification score. Rank already incorporates ianseo's own qualification tiebreaks (golds, X/9), so competitors level on all of criteria 1-3 are effectively never tied and criterion 4 becomes unreachable in practice.

A competitor with no qualification rank — rank `0`, or a DNS/DSQ status code — SHALL sort **after** every ranked competitor on criterion 3, never before them.

The criteria apply identically to set-system (R, B) and cumulative-system (C) events, and identically to individual and team events.

#### Scenario: Two losers separated by qualification rank

- **WHEN** two archers are eliminated in the same phase with the same match average and the same shoot-off average
- **AND** one qualified 3rd and the other 11th
- **THEN** the archer who qualified 3rd is placed ahead
- **AND** the two do not share a position

#### Scenario: Equal qualification scores no longer share a place

- **WHEN** two archers eliminated in the same phase are level on criteria 1 and 2 and shot the same qualification score
- **AND** ianseo's qualification ranking separated them on golds or X/9
- **THEN** their final placement follows that qualification ranking

#### Scenario: Competitor without a qualification result

- **WHEN** a competitor eliminated in a phase has no qualification rank (rank 0, DNS or DSQ)
- **THEN** they are placed after every competitor of that phase who has a qualification rank

#### Scenario: Criteria 1 and 2 still take precedence

- **WHEN** two archers eliminated in the same phase have different match averages
- **THEN** the higher match average is placed ahead, regardless of qualification rank
