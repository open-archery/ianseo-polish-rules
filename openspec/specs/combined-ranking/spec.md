### Requirement: Tournament selection
The page SHALL allow the user to select up to two tournaments via HTML select elements. Tournament 1 SHALL be pre-selected with the current session tournament. Tournament 2 SHALL default to empty (no second tournament). Both selects SHALL list all tournaments in the database (all `Tournament` rows). Selecting only Tournament 1 (leaving Tournament 2 empty) SHALL produce a valid ranking using data from Tournament 1 alone.

#### Scenario: Current tournament pre-selected
- **WHEN** the user opens the combined ranking page
- **THEN** Tournament 1 select SHALL be pre-selected with `$_SESSION['TourId']`

#### Scenario: Single tournament ranking
- **WHEN** Tournament 2 is left empty and the user generates the PDF
- **THEN** the ranking SHALL include only Day 1 columns; Day 2 columns SHALL be shown as blank

#### Scenario: Two tournament ranking
- **WHEN** both Tournament 1 and Tournament 2 are selected and the user generates the PDF
- **THEN** the ranking SHALL include data from both tournaments

---

### Requirement: Athlete matching across tournaments
Athletes SHALL be matched across tournaments by their licence number stored in `Entries.EnCode`. An athlete appearing in both tournaments SHALL be treated as a single row in the combined ranking. An athlete appearing in only one tournament SHALL still appear in the combined ranking, with the other tournament's columns blank.

#### Scenario: Same athlete in both tournaments
- **WHEN** an athlete has `EnCode = 'POL-123'` in Tournament 1 and `EnCode = 'POL-123'` in Tournament 2
- **THEN** they SHALL appear as a single row in the combined ranking with data from both tournaments

#### Scenario: Athlete in one tournament only
- **WHEN** an athlete appears only in Tournament 1
- **THEN** they SHALL appear in the ranking with Day 2 Kwalifikacje and Eliminacje columns blank, contributing 0 points from Day 2

---

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

#### Scenario: Compound athlete not in elimination bracket
- **WHEN** a Compound athlete has no `Finals` row for that tournament
- **THEN** their elimination place SHALL default to their qualification rank and elimination points SHALL be calculated from that place using the Compound lookup table
- **WHEN** a Compound athlete ranked 9th in qualification has no `Finals` row
- **THEN** their elimination place SHALL be shown as 9 and they SHALL receive 5 elimination points

---

### Requirement: Total points and tiebreaker
Each athlete's total points SHALL equal the sum of all four components: Day 1 qualification points + Day 1 elimination points + Day 2 qualification points + Day 2 elimination points. The tiebreaker column SHALL contain the higher of the two qualification scores (`QuScore`) across both tournaments. When only one tournament is provided, the tiebreaker column SHALL equal that tournament's qualification score. The combined ranking SHALL be sorted by total points descending; ties SHALL be broken by the tiebreaker column descending.

#### Scenario: Total points calculation
- **WHEN** an athlete earns 13 qual pts and 22 elim pts on Day 1, and 15 qual pts and 28 elim pts on Day 2
- **THEN** their total SHALL be 78 points

#### Scenario: Tiebreaker
- **WHEN** two athletes have equal total points
- **THEN** the athlete with the higher tiebreaker score SHALL be ranked higher

#### Scenario: Best score with one tournament
- **WHEN** only Tournament 1 is provided and an athlete scored 672 in qualification
- **THEN** the tiebreaker column SHALL display 672

---

### Requirement: Division-aware tiebreaker column label
The tiebreaker column header SHALL reflect the qualifying distance for the division. For Compound sections the column SHALL be labelled "Najl. 2x50m". For all other divisions (Recurve, etc.) the column SHALL remain "Najl. 2x70m".

#### Scenario: Compound section tiebreaker label
- **WHEN** the PDF contains a Compound Men or Compound Women section
- **THEN** the tiebreaker column header SHALL read "Najl. 2x50m"

#### Scenario: Recurve section tiebreaker label
- **WHEN** the PDF contains a Recurve Men or Recurve Women section
- **THEN** the tiebreaker column header SHALL read "Najl. 2x70m"

---

### Requirement: Per division+class tables
The combined ranking SHALL be split into separate tables, one per division+class combination present in the data (e.g. RM, RW, CM, CW). Each table SHALL include only athletes registered in that division+class. Division and class SHALL be read from `Entries.EnDivision` and `Entries.EnClass` in Tournament 1; if an athlete appears only in Tournament 2, division+class SHALL be read from Tournament 2.

#### Scenario: Separate tables per category
- **WHEN** the tournaments contain Recurve Men, Recurve Women, Compound Men, and Compound Women athletes
- **THEN** the PDF SHALL contain four separate ranked tables, one per category

---

### Requirement: PDF output
The system SHALL produce an A4 PDF using TCPDF. The PDF SHALL contain one section per division+class, each section starting with a bold centred title (e.g. "Recurve Mężczyźni"). Each row SHALL contain: Miejsce, Imię Nazwisko, Klub, Nr licencji, and for each of Day 1 and Day 2: Kwalifikacje Miejsce, Kwalifikacje Punkty, Eliminacje Miejsce, Eliminacje Punkty, then the tiebreaker column, then Łącznie punktów. The PDF SHALL be streamed directly to the browser as a download.

#### Scenario: Column layout
- **WHEN** the PDF is generated with two tournaments selected
- **THEN** each row SHALL have columns: Miejsce | Imię Nazwisko | Klub | Nr licencji | Dzień 1 Kwal Miejsce | Dzień 1 Kwal Punkty | Dzień 1 Elim Miejsce | Dzień 1 Elim Punkty | Dzień 2 Kwal Miejsce | Dzień 2 Kwal Punkty | Dzień 2 Elim Miejsce | Dzień 2 Elim Punkty | Najlepsze 2x70m (or 2x50m) | Łącznie punktów

#### Scenario: Blank elimination place
- **WHEN** an athlete did not participate in eliminations in a tournament
- **THEN** the elimination Miejsce cell SHALL be empty and the Punkty cell SHALL show 0

---

### Requirement: QF tie warning on combined ranking page
The combined ranking page SHALL detect and surface unresolved Compound QF place ties for the selected tournaments. When detected, the page SHALL display a warning banner and a data-entry form before allowing PDF generation to proceed. PDF generation SHALL still be possible even when ties are unresolved; unresolved ties are marked in the PDF.

#### Scenario: Warning shown before PDF generation
- **WHEN** the operator selects tournaments and an unresolved QF tie exists in the Compound division
- **THEN** the page SHALL display a warning banner identifying the tied athletes and tournament before the PDF is streamed

#### Scenario: PDF generated with unresolved tie marker
- **WHEN** the operator generates the PDF while an unresolved QF tie remains
- **THEN** the PDF SHALL include the unresolved-tie footnote and generation SHALL NOT be blocked
