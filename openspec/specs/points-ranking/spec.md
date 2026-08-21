## ADDED Requirements

### Requirement: Preset selection per tournament
The system SHALL allow the operator to select one competition preset for the current tournament from a list of read-only predefined presets. The selection SHALL be persisted per tournament in `PLPointsTournamentConfig`. Exactly one preset may be active at a time per tournament.

#### Scenario: Operator selects a preset
- **WHEN** the operator opens the points ranking configuration page and chooses a preset from the dropdown
- **THEN** the selection is saved and subsequent calculation, PDF and diplomas use that preset's rules

#### Scenario: No preset selected
- **WHEN** no preset has been selected for the current tournament
- **THEN** the ranking page shows only the configuration form and suppresses the calculation, PDF and diploma controls

---

### Requirement: Preset scope filter
Each preset SHALL declare the set of divisions and classes it scores. Athletes and teams whose event falls outside that scope SHALL be excluded from every classification, report and rollup. A preset with an empty scope declaration SHALL score every division and class present in the tournament.

#### Scenario: Out-of-scope category excluded
- **WHEN** the LZS preset (scope: division `R`, classes `U24M,U24W,U21M,U21W,U18M,U18W`) is active and the tournament also contains `RM` (Seniorzy) entries
- **THEN** no `RM` athlete appears in any report and no `RM` result contributes to club or voivodeship totals

#### Scenario: Unrestricted preset
- **WHEN** the active preset declares no scope restriction
- **THEN** every division and class present in the tournament is scored

---

### Requirement: Category dimension
All points assignment, cutoff evaluation, ranking and output sectioning SHALL be scoped to a **category**. For individual results the category is `Entries.EnDivision` concatenated with `Entries.EnClass`; for team and mixed results it is `Teams.TeEvent`. A place of 1 in one category and a place of 1 in another category each receive the full bracket value independently.

Sections SHALL be ordered by division following the PZŁucz display order `R, C, B`, then by the tournament's `Classes.ClViewOrder`. Unknown divisions SHALL follow alphabetically. Section labels SHALL be built from `Divisions.DivDescription` and `Classes.ClDescription`.

#### Scenario: Same place in two categories
- **WHEN** athlete A places 1st in `RU21M` and athlete B places 1st in `RU21W`, and the bracket awards 25 points for place 1
- **THEN** both athletes receive 25 points

#### Scenario: Section order
- **WHEN** the tournament contains `RU21M`, `CU21M` and `BU21M` results
- **THEN** the `R` section is rendered first, then `C`, then `B`

#### Scenario: Section labels
- **WHEN** a section covers division `R` and class `U21M`
- **THEN** the section title uses the tournament's own descriptions, e.g. "Łuk klasyczny Junior"

---

### Requirement: Rank source per classification
Each classification SHALL declare its rank source as `QUAL` or `ELIM`. `QUAL` reads `Individuals.IndRank` for individuals and `Teams.TeRank` for teams. `ELIM` reads `Individuals.IndRankFinal` and `Teams.TeRankFinal`. A subject whose place in the declared source is `0` SHALL be treated as having no result and SHALL receive 0 points.

#### Scenario: Qualification-only competition
- **WHEN** the LZS preset declares both its classifications as `QUAL` and the tournament has no elimination round
- **THEN** places are read from `IndRank` and `TeRank` and every scored athlete receives points

#### Scenario: Elimination source
- **WHEN** a Puchar Polski classification declares source `ELIM`
- **THEN** places are read from `IndRankFinal`

#### Scenario: No result in the declared source
- **WHEN** an athlete has `IndRank = 5` but the classification source is `ELIM` and `IndRankFinal = 0`
- **THEN** the athlete receives 0 points and is omitted from that classification's table

---

### Requirement: Individual points assignment
The system SHALL assign points to each athlete based on their place in the classification's rank source, using the classification's rank→points bracket table. Brackets are inclusive ranges: an athlete at place P receives the points of the bracket where `rank_from ≤ P ≤ rank_to`. Athletes with a special rank (DSQ, DNS, DNF — place value ≥ 29999) SHALL receive 0 points. Athletes outside all brackets SHALL receive 0 points and SHALL be omitted from that classification's table.

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
- **THEN** the athlete receives 0 points regardless of place

---

### Requirement: Team points assignment
The system SHALL assign points to each team from the team's place using the classification's bracket table, then attribute those points twice:

- **to the club**, at full value;
- **to each counting team member**, split evenly (`team_points ÷ counting_member_count`).

The roster SHALL be read from `TeamComponent` when the classification source is `QUAL`, and from `TeamFinComponent` when the source is `ELIM`. Team size SHALL be read from `Events.EvMaxTeamPerson` and SHALL NOT be hardcoded. The club of a team SHALL be resolved as `IF(EnCountry2 = 0, EnCountry, EnCountry2)`, matching ianseo's own team builder.

When a preset declares `one_team_per_club`, only `Teams.TeSubTeam = 0` SHALL be scored; further sub-teams of the same club in the same category SHALL be ignored.

#### Scenario: Qualification team roster
- **WHEN** the LZS preset scores its team classification with source `QUAL`
- **THEN** the roster is read from `TeamComponent`, not `TeamFinComponent`

#### Scenario: Team of 3 — split and club credit
- **WHEN** a 3-member team places 1st and the bracket awards 9 points
- **THEN** each member is credited 3 points and the club is credited 9 points

#### Scenario: Second team of the same club ignored
- **WHEN** `one_team_per_club` is set and a club has rows with `TeSubTeam = 0` and `TeSubTeam = 1` in the same category
- **THEN** only the `TeSubTeam = 0` team is scored

#### Scenario: 3-of-4 rule
- **WHEN** a team has 4 members and the preset has `three_of_four` enabled
- **THEN** the member with the worst qualification place is credited 0 team points, the other 3 each receive `team_points ÷ 3`, and the club is still credited the full team points

#### Scenario: Empty roster
- **WHEN** a team has a place and bracket points but no rows in the source roster table
- **THEN** the club is still credited the full team points, no member split occurs, and the HTML view shows a warning naming the team

---

### Requirement: Mixed team points assignment
The system SHALL treat a mixed team as a 2-member team: the pair's bracket points are credited to the club at full value and split evenly between the two members (`÷ 2`). Mixed team events are identified by `Events.EvMixedTeam = 1`.

#### Scenario: Mixed pair ranked 1st
- **WHEN** a mixed pair places 1st and the bracket awards 25 points
- **THEN** each of the 2 members is shown with 12,5 points and the club is credited 25 points

#### Scenario: Mixed classification not merged into the athlete total
- **WHEN** the active preset renders the mixed classification as a `SEPARATE` report
- **THEN** the mixed points do not appear in and do not contribute to any `COMBINED` athlete total

---

### Requirement: Cutoff rule
When a classification has cutoff enabled, the system SHALL set the points of the last-placed subject **within each category independently** to 0 when the number of starters in that category (subjects with a valid place, place value < 29999) is less than the maximum `rank_to` in that classification's bracket table.

#### Scenario: Cutoff applies within one category
- **WHEN** 12 athletes compete in `RU21M`, the bracket table extends to place 15, and cutoff is enabled
- **THEN** the athlete placed 12th in `RU21M` receives 0 points; athletes in other categories are unaffected

#### Scenario: Cutoff does not apply — sufficient starters
- **WHEN** 16 athletes compete in a category and the bracket table extends to place 15
- **THEN** all athletes in that category receive their bracket points

#### Scenario: Cutoff disabled
- **WHEN** the classification has cutoff disabled
- **THEN** all subjects with a valid place receive their bracket points; the last subject is not zeroed

---

### Requirement: Report composition
Each preset SHALL declare an ordered list of reports. The HTML view and the PDF SHALL render exactly those reports, in that order. Four report kinds SHALL be supported:

| Kind | Renders |
|---|---|
| `SEPARATE(c)` | one table for classification `c`, sectioned per category |
| `COMBINED(c…, cap N)` | one athlete table, one column per listed classification plus `Suma`, sectioned per category |
| `CLUB` | one table of clubs ranked by summed points |
| `VOIVODESHIP` | one table of voivodeships ranked by summed club points |

A report that yields no rows SHALL be omitted from the output entirely.

#### Scenario: Two independent classifications
- **WHEN** the Puchar Polski preset declares `SEPARATE(ind)` and `SEPARATE(mix)`
- **THEN** the output contains an individual table and a separate mixed table, and no athlete's individual and mixed points are ever summed

#### Scenario: Combined then rollups
- **WHEN** the LZS preset declares `COMBINED(ind, tea, cap 0)`, `SEPARATE(tea)`, `CLUB`, `VOIVODESHIP`
- **THEN** the output contains, in order: the athlete table with `Indywidualnie / Zespołowo / Suma` columns, the team ranking table, the club table, the voivodeship table

#### Scenario: Empty report omitted
- **WHEN** a preset declares `SEPARATE(mix)` and the tournament has no mixed team event
- **THEN** the mixed report is omitted and no empty section is rendered

---

### Requirement: Combined athlete total and max-events cap
A `COMBINED` report SHALL sum, per athlete and per category, that athlete's points from each listed classification. When the report declares a cap `N > 0` and the athlete earned points in more than `N` of the listed classifications, only the `N` highest values SHALL be summed. A cap of `0` means unlimited.

#### Scenario: Cap drops the lowest value
- **WHEN** an athlete earns 11 (team), 7 (individual) and 9,5 (mixed) and the cap is 2
- **THEN** the individual 7 is dropped and the total is 20,5

#### Scenario: Unlimited cap
- **WHEN** the LZS preset declares cap 0 and an athlete earns 9 individually and 3 from the team
- **THEN** the total is 12

#### Scenario: Fewer results than the cap
- **WHEN** an athlete earns points in only one classification and the cap is 2
- **THEN** the total equals that single value; no penalty applies

---

### Requirement: Ranking order and ties
Within each table and each section, subjects SHALL be sorted by points descending, then by place ascending. Subjects with equal points SHALL share the same displayed rank.

#### Scenario: Ordering within a shared bracket
- **WHEN** athletes placed 9th and 12th both receive 5 points
- **THEN** the athlete placed 9th is listed first

#### Scenario: Tied subjects share a rank
- **WHEN** two athletes each total 20 points
- **THEN** both are shown at the same rank

---

### Requirement: Club ranking
A `CLUB` report SHALL rank clubs by the sum of all points credited to that club — athlete points from every classification listed by the preset, plus team and mixed points credited at full value. Clubs are identified within the tournament by `Countries.CoId` and across tournaments by `Countries.CoCode`. The table SHALL contain: Miejsce, Klub, Województwo (when a `VOIVODESHIP` report is also declared), Suma.

#### Scenario: Club aggregation
- **WHEN** athletes of "Łucznik Kraków" total 9, 7 and 5 individually and the club's team places 1st for 9 points
- **THEN** the club total is 30

#### Scenario: No club report declared
- **WHEN** the active preset declares no `CLUB` report
- **THEN** no club table is computed or rendered

---

### Requirement: Voivodeship ranking
A `VOIVODESHIP` report SHALL rank voivodeships by the sum of club totals, using the `PLVoivodeshipMap` mapping. Clubs with no mapping SHALL be shown in the club table with "Nieprzypisane" and SHALL be excluded from voivodeship totals.

#### Scenario: Voivodeship aggregation
- **WHEN** three clubs mapped to "małopolskie" have totals 60, 45 and 30
- **THEN** "małopolskie" totals 135

#### Scenario: Unmapped club
- **WHEN** a club has no row in `PLVoivodeshipMap`
- **THEN** it appears in the club table labelled "Nieprzypisane" and contributes to no voivodeship

---

### Requirement: Voivodeship mapping management
The system SHALL provide an operator page listing every club present in the current tournament with a dropdown of the 16 Polish voivodeships. Mappings SHALL be stored in `PLVoivodeshipMap` keyed by `Countries.CoCode`, so a mapping entered once applies to every tournament in which that club code appears. Existing mappings SHALL be pre-selected.

#### Scenario: Mapping persists across tournaments
- **WHEN** the operator maps club code `KRA01` to "małopolskie" in tournament A
- **THEN** the mapping is applied automatically in tournament B where the same club code appears, without re-entry

#### Scenario: Pre-existing mapping shown
- **WHEN** the operator opens the mapping page and a club already has a mapping
- **THEN** the stored voivodeship is pre-selected

---

### Requirement: Row identity
Every output row for an athlete SHALL carry that athlete's licence number (`Entries.EnCode`) alongside their name and club, and the licence number SHALL be rendered in both the HTML view and the PDF.

#### Scenario: Licence rendered
- **WHEN** any athlete-level table is rendered
- **THEN** the row includes the athlete's licence number

---

### Requirement: PDF report generation
The system SHALL generate a single A4 TCPDF document containing the preset's declared reports in declared order, streamed to the browser. Each page header SHALL carry the tournament name, tournament date and preset name. Each section SHALL begin with a bold centred category title. Fractional points SHALL be displayed to at most 2 decimal places, with a trailing `,00` suppressed for whole values.

#### Scenario: Reports rendered in declared order
- **WHEN** the preset declares `COMBINED`, `SEPARATE(tea)`, `CLUB`, `VOIVODESHIP`
- **THEN** the PDF contains those four reports in that order

#### Scenario: Fractional points display
- **WHEN** a mixed team member's share is 12.5 points
- **THEN** the PDF renders `12,5`

#### Scenario: Whole points display
- **WHEN** an athlete's total is 25
- **THEN** the PDF renders `25`, not `25,00`

---

### Requirement: Ranking diplomas
The system SHALL generate diplomas for `CLUB` and `VOIVODESHIP` reports, reusing the Diplomas module's `PLDiplomaPdf` renderer and its per-tournament configuration (`pl_diploma_get_config()`: competition name, dates, location, body text, head judge, organizer, place range). The ranking page SHALL expose a diploma button per eligible report, shown only when that report is part of the active preset.

Club diplomas SHALL print the club name in the recipient slot with an empty club line; voivodeship diplomas SHALL print the voivodeship name. The category line SHALL read "Klasyfikacja klubowa" and "Klasyfikacja województw" respectively. Only rows whose rank falls inside the configured place range (`PlaceFrom`..`PlaceTo`) SHALL be printed.

#### Scenario: Club diplomas printed for the configured places
- **WHEN** the diploma configuration has `PlaceFrom = 1`, `PlaceTo = 3` and the club ranking has 12 clubs
- **THEN** three diplomas are produced, for the clubs ranked 1st, 2nd and 3rd

#### Scenario: Voivodeship diploma content
- **WHEN** "małopolskie" is ranked 1st in the voivodeship report
- **THEN** its diploma shows "małopolskie" as the recipient, no club line, and the category line "Klasyfikacja województw"

#### Scenario: Diploma button hidden when the report is absent
- **WHEN** the active preset declares no `VOIVODESHIP` report
- **THEN** the voivodeship diploma button is not rendered

#### Scenario: No rows in range
- **WHEN** no ranking row falls inside the configured place range
- **THEN** the system reports that no diplomas can be generated instead of emitting an empty PDF

---

### Requirement: Preset definitions (read-only)
Presets SHALL be defined as PHP constant arrays in `Presets.php` and read directly at calculation time. They SHALL NOT be seeded into or read from database tables, and SHALL NOT be editable through the UI. A change to a preset's point values SHALL take effect for every installation on the next code update, with no migration step.

| # | Preset | Scope | Reports | Cutoff | Source |
|---|---|---|---|---|---|
| 1 | Młodzieżowe Mistrzostwa Polski | all | `COMBINED(ind,tea,mix, cap 3)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 2 | Mistrzostwa Polski Juniorów | all | `COMBINED(ind,tea,mix, cap 2)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 3 | Puchar Polski — runda | all | `SEPARATE(ind)`, `SEPARATE(mix)` | NO | ELIM |
| 4 | Międzywojewódzkie Mistrzostwa Młodzików | all | `COMBINED(ind,tea,mix, cap 2)`, `CLUB`, `VOIVODESHIP` | YES | QUAL |
| 5 | Ogólnopolska Olimpiada Młodzieży | all | `COMBINED(ind,tea,mix, cap 2)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 6 | Mistrzostwa Krajowego Zrzeszenia LZS | `R` × `U24M,U24W,U21M,U21W,U18M,U18W` | `COMBINED(ind,tea, cap 0)`, `SEPARATE(tea)`, `CLUB`, `VOIVODESHIP` | NO | QUAL |

Presets 1, 2, 4 and 5 enable `three_of_four`. Preset 6 enables `one_team_per_club`.

**Brackets — preset 3 (Puchar Polski), covers juniorzy młodsi, juniorzy and seniorzy in every division:**

| | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9-16 | 17-32 |
|---|---|---|---|---|---|---|---|---|---|---|
| ind | 25 | 21 | 18 | 15 | 13 | 12 | 11 | 10 | 5 | 1 |
| mix | 25 | 21 | 18 | 15 | 13 | 12 | 11 | 10 | 5 | — |

The mixed table intentionally stops at 9-16: at most 16 pairs take part in a Puchar Polski mixed bracket.

**Brackets — preset 6 (LZS)**, one table shared by `ind` and `tea`:

| 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 |
|---|---|---|---|---|---|---|---|
| 9 | 7 | 6 | 5 | 4 | 3 | 2 | 1 |

**Brackets — preset 1 (Młodzieżowe Mistrzostwa Polski):**

| | 1 | 2 | 3-4 | 5 | 6 | 7 | 7-8 | 8 | 9-10 | 11-12 | 13-16 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| ind | 25 | 21 | 17 | 10 | 5 | — | 4 | — | 3 | 2 | 1 |
| tea | 25 | 21 | 17 | 11 | 5 | 4 | — | 1 | — | — | — |
| mix | 25 | 21 | 17 | 10 | 5 | 4 | — | 1 | — | — | — |

**Brackets — preset 2 (Mistrzostwa Polski Juniorów):**

| | 1 | 2 | 3-4 | 5 | 6 | 6-7 | 7 | 8 | 8-10 | 9-10 | 9-12 | 11-12 | 13-15 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| ind | 15 | 12 | 10 | 8 | 7 | — | 6 | 5 | — | — | 4 | — | 2 |
| tea | 33 | 27 | 22 | 10 | — | 8 | — | 4 | — | 3 | — | — | — |
| mix | 24 | 19 | 16 | 8 | — | 7 | — | — | 3 | — | — | 1 | — |

The preset 2 mixed table is printed in the annex as `6-7` followed by `7-10`, which overlap at place 7. The corrected reading is `8-10 → 3`.

**Brackets — preset 5 (Ogólnopolska Olimpiada Młodzieży):**

| | 1 | 2 | 3-4 | 5 | 5-6 | 6-7 | 6-8 | 7-10 | 8 | 9 | 9-10 | 10-13 | 11-16 | 17-24 | 25-32 | 33-64 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| ind | 12 | 10 | 8 | — | 6 | — | — | 5 | — | — | — | — | 4 | 3 | 2 | 1 |
| tea | 27 | 22 | 18 | 10 | — | 8 | — | — | 7 | — | 6 | — | — | — | — | — |
| mix | 19 | 16 | 12 | 8 | — | — | 6 | — | — | 4 | — | 2 | — | — | — | — |

**Brackets — preset 4 (Międzywojewódzkie MM Młodzików):**

| | 1 | 2 | 3-4 | 5-6 | 5-8 | 5-9 | 10-20 |
|---|---|---|---|---|---|---|---|
| ind | 5 | 4 | 3 | — | — | 2 | 1 |
| tea | 5 | 4 | 3 | 2 | — | — | — |
| mix | 5 | 4 | 3 | — | 2 | — | — |

Each classification's bracket table ends at a different place because the fields differ in size; places beyond the last bracket score 0, as everywhere else. The annex note "Każdy zawodnik może uzyskać punkty tylko dwukrotnie" is the `COMBINED` cap of 2.

#### Scenario: Preset values change with the code
- **WHEN** a preset's point value is edited in `Presets.php` and the module is updated
- **THEN** the new value is used on the next calculation with no database migration or re-seed

---

### Requirement: Bracket table integrity
Bracket tables SHALL NOT contain overlapping ranges, and every bracket SHALL satisfy `rank_from ≤ rank_to`. The rule SHALL be enforced by an automated test over every preset shipped in `Presets.php`.

#### Scenario: Overlapping brackets rejected
- **WHEN** a preset declares brackets `7-10` and `9-16` for the same classification
- **THEN** the integrity test fails and names the preset and classification
