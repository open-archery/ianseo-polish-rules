## ADDED Requirements

### Requirement: Division-aware tiebreaker column label
The tiebreaker column header SHALL reflect the qualifying distance for the division. For Compound sections the column SHALL be labelled "Najl. 2x50m". For all other divisions (Recurve, etc.) the column SHALL remain "Najl. 2x70m".

#### Scenario: Compound section tiebreaker label
- **WHEN** the PDF contains a Compound Men or Compound Women section
- **THEN** the tiebreaker column header SHALL read "Najl. 2x50m"

#### Scenario: Recurve section tiebreaker label
- **WHEN** the PDF contains a Recurve Men or Recurve Women section
- **THEN** the tiebreaker column header SHALL read "Najl. 2x70m"

---

## MODIFIED Requirements

### Requirement: Qualification data per tournament
For each tournament, the system SHALL retrieve each athlete's qualification rank (`Qualifications.QuRank`) and qualification score (`Qualifications.QuScore`) for their individual event. Qualification rank SHALL be mapped to qualification points using a division-specific formula:

- **Recurve and all other divisions**: `points = max(0, 16 - QuRank)` for rank 1–15; 0 for rank 16 and above.
- **Compound**: points SHALL be determined by the following lookup table; 0 for rank 10 and above.

| Miejsce | Punkty |
|---------|--------|
| 1       | 20     |
| 2       | 19     |
| 3       | 18     |
| 4       | 17     |
| 5       | 11     |
| 6       | 10     |
| 7       | 9      |
| 8       | 8      |
| 9       | 1      |

#### Scenario: Recurve qualification points mapping
- **WHEN** a Recurve athlete finishes qualification in place 1
- **THEN** they SHALL receive 15 qualification points
- **WHEN** a Recurve athlete finishes qualification in place 15
- **THEN** they SHALL receive 1 qualification point
- **WHEN** a Recurve athlete finishes qualification in place 16 or lower
- **THEN** they SHALL receive 0 qualification points

#### Scenario: Compound qualification points mapping
- **WHEN** a Compound athlete finishes qualification in place 1
- **THEN** they SHALL receive 20 qualification points
- **WHEN** a Compound athlete finishes qualification in place 4
- **THEN** they SHALL receive 17 qualification points
- **WHEN** a Compound athlete finishes qualification in place 5
- **THEN** they SHALL receive 11 qualification points
- **WHEN** a Compound athlete finishes qualification in place 9
- **THEN** they SHALL receive 1 qualification point
- **WHEN** a Compound athlete finishes qualification in place 10 or lower
- **THEN** they SHALL receive 0 qualification points

---

### Requirement: Elimination data per tournament
For each tournament, the system SHALL retrieve the athlete's final elimination rank (`Individuals.IndRankFinal`) only when the athlete participated in the elimination bracket. Bracket participation SHALL be determined by the existence of a row in the `Finals` table for that athlete and tournament. Athletes without a `Finals` entry SHALL have their elimination place shown as blank and SHALL receive 0 elimination points. Elimination rank SHALL be mapped to elimination points using a division-specific formula:

- **Recurve and all other divisions**: `points = max(0, (16 - IndRankFinal) * 2)` for rank 1–15; 0 for rank 16 and above.
- **Compound**: points SHALL be determined by the following lookup table; 0 for rank 10 and above.

| Miejsce | Punkty |
|---------|--------|
| 1       | 30     |
| 2       | 26     |
| 3       | 25     |
| 4       | 21     |
| 5       | 20     |
| 6       | 18     |
| 7       | 15     |
| 8       | 11     |
| 9       | 5      |

#### Scenario: Recurve elimination points mapping
- **WHEN** a Recurve athlete finishes in elimination place 1
- **THEN** they SHALL receive 30 elimination points
- **WHEN** a Recurve athlete finishes in elimination place 15
- **THEN** they SHALL receive 2 elimination points
- **WHEN** a Recurve athlete finishes in elimination place 16 or lower
- **THEN** they SHALL receive 0 elimination points
- **WHEN** a Recurve athlete has no `Finals` row for that tournament
- **THEN** their elimination place SHALL be blank and they SHALL receive 0 elimination points

#### Scenario: Compound elimination points mapping
- **WHEN** a Compound athlete finishes in elimination place 1
- **THEN** they SHALL receive 30 elimination points
- **WHEN** a Compound athlete finishes in elimination place 4
- **THEN** they SHALL receive 21 elimination points
- **WHEN** a Compound athlete finishes in elimination place 9
- **THEN** they SHALL receive 5 elimination points
- **WHEN** a Compound athlete finishes in elimination place 10 or lower
- **THEN** they SHALL receive 0 elimination points
- **WHEN** a Compound athlete has no `Finals` row for that tournament
- **THEN** their elimination place SHALL be blank and they SHALL receive 0 elimination points
