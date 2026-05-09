### Requirement: Points assignment from elimination place
The system SHALL assign Puchar Polski auxiliary points to each athlete based on their final elimination place (`IndRankFinal`) using the following table:

| Place | Points |
|-------|--------|
| 1 | 25 |
| 2 | 21 |
| 3 | 18 |
| 4 | 15 |
| 5 | 13 |
| 6 | 12 |
| 7 | 11 |
| 8 | 10 |
| 9–16 | 5 |
| 17–32 | 1 |
| 33+ | 0 (omitted) |

Athletes with no bracket entry (`IndRankFinal = 0`) SHALL receive 0 points and SHALL be omitted from the report.

#### Scenario: Top-8 place receives unique points
- **WHEN** an athlete finishes in elimination place 1
- **THEN** they SHALL receive 25 points
- **WHEN** an athlete finishes in elimination place 8
- **THEN** they SHALL receive 10 points

#### Scenario: Places 9–16 receive equal points
- **WHEN** an athlete finishes in elimination place 9
- **THEN** they SHALL receive 5 points
- **WHEN** an athlete finishes in elimination place 16
- **THEN** they SHALL receive 5 points

#### Scenario: Places 17–32 receive equal points
- **WHEN** an athlete finishes in elimination place 17
- **THEN** they SHALL receive 1 point
- **WHEN** an athlete finishes in elimination place 32
- **THEN** they SHALL receive 1 point

#### Scenario: Place 33 and beyond — omitted
- **WHEN** an athlete finishes in elimination place 33 or higher
- **THEN** they SHALL NOT appear in the report

#### Scenario: No bracket entry — omitted
- **WHEN** an athlete has no `Finals` row (did not enter the elimination bracket)
- **THEN** they SHALL NOT appear in the report

---

### Requirement: Ordering within same-points groups
Within a group of athletes sharing the same point value (places 9–16 and 17–32), athletes SHALL be ordered by their actual elimination place ascending.

#### Scenario: Same-points group ordered by elimination place
- **WHEN** athletes finishing in places 9 and 12 both receive 5 points
- **THEN** the athlete in place 9 SHALL appear before the athlete in place 12

---

### Requirement: Per division+class sections
The report SHALL group athletes into separate sections, one per division+class combination present in the data (e.g. RM, RW, CM, CW). Sections SHALL be ordered by the preferred PZŁucz display order: RM, RW, CM, CW, BM, BW; unknown combinations SHALL follow alphabetically.

#### Scenario: Separate section per category
- **WHEN** the tournament contains Recurve Men and Compound Women athletes with points
- **THEN** the PDF SHALL contain two separate sections, one for each category

#### Scenario: Preferred section order
- **WHEN** the tournament contains both RM and RW athletes
- **THEN** the RM section SHALL appear before the RW section

---

### Requirement: PDF output
The system SHALL produce an A4 PDF using TCPDF, streamed directly to the browser as a download. Each section SHALL begin with a bold centred title showing the division+class description. Each row SHALL contain: **Lp.**, **Imię Nazwisko**, **Klub**, **Nr licencji**, **Miejsce** (elimination place), **Punkty**.

#### Scenario: PDF generation
- **WHEN** the user clicks "Generuj PDF" on the cup ranking page
- **THEN** the browser SHALL receive a PDF file download

#### Scenario: PDF columns
- **WHEN** the PDF is generated
- **THEN** each athlete row SHALL display: rank position, full name, club, licence number, elimination place, and points

---

### Requirement: Menu entry
The cup ranking page SHALL be accessible from the **Printouts** menu when a PL tournament is open.

#### Scenario: Menu item visible with PL tournament open
- **WHEN** a tournament with `TourLocRule = 'PL'` is open
- **THEN** "Ranking Pucharu Polski" SHALL appear in the Printouts menu

#### Scenario: Menu item absent without PL tournament
- **WHEN** no PL tournament is open
- **THEN** "Ranking Pucharu Polski" SHALL NOT appear in the Printouts menu
