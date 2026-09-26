## MODIFIED Requirements

### Requirement: Rank source per classification
Each classification SHALL declare its rank source as `QUAL` or `ELIM`. `QUAL` reads `Individuals.IndRank` for individuals and `Teams.TeRank` for teams. `ELIM` reads `Individuals.IndRankFinal` and `Teams.TeRankFinal` as absolute values (`ABS()` — ianseo core writes negative final ranks in some flows). For an event with no elimination phase (`Events.EvFinalFirstPhase = 0`) an `ELIM` classification SHALL fall back to the qualification rank, matching the Diplomas module. A subject whose place in the declared source is `0` SHALL be treated as having no result and SHALL receive 0 points.

#### Scenario: Qualification-only competition
- **WHEN** the LZS preset declares its `ind` classification as `QUAL` and the tournament has no elimination round
- **THEN** places are read from `IndRank` and every scored athlete receives points

#### Scenario: Elimination source
- **WHEN** a Puchar Polski classification declares source `ELIM`
- **THEN** places are read from `IndRankFinal`

#### Scenario: No result in the declared source
- **WHEN** an athlete has `IndRank = 5` but the classification source is `ELIM` and `IndRankFinal = 0`
- **THEN** the athlete receives 0 points and is omitted from that classification's table

#### Scenario: Category without an elimination round
- **WHEN** an `ELIM` preset is active and a category's event has `EvFinalFirstPhase = 0` (no elimination round was shot)
- **THEN** that category is scored from qualification ranks instead of silently disappearing from the reports and club totals

---

### Requirement: Team points assignment
The system SHALL assign points to each team from the team's place using the classification's bracket table and credit them to the **counting team members** as even shares (`team_points ÷ counting_member_count`). The team's full value reaches the club through those shares (see Club ranking): with a complete roster and no cap drop, the shares sum back to the full table value; a share dropped by the combined cap reduces the club total by exactly that share.

The roster SHALL be read from `TeamComponent` when the classification source is `QUAL`, and from `TeamFinComponent` when the source is `ELIM`. Team size SHALL be read from `Events.EvMaxTeamPerson` and SHALL NOT be hardcoded. The club of a team SHALL be resolved as `IF(EnCountry2 = 0, EnCountry, EnCountry2)`, matching ianseo's own team builder. In the 3-of-4 rule, a tie for the worst qualification place SHALL be broken deterministically (the member with the higher entry id is dropped).

When a preset declares `one_team_per_club`, only `Teams.TeSubTeam = 0` SHALL be scored; further sub-teams of the same club in the same category SHALL be ignored.

#### Scenario: Qualification team roster
- **WHEN** the Międzywojewódzkie Mistrzostwa Młodzików preset scores its team classification with source `QUAL`
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
- **WHEN** the LZS preset declares `SEPARATE(ind)`, `CLUB`, `VOIVODESHIP`
- **THEN** the output contains, in order: the individual classification table, the club table, the voivodeship table

#### Scenario: Empty report omitted
- **WHEN** a preset declares `SEPARATE(mix)` and the tournament has no mixed team event
- **THEN** the mixed report is omitted and no empty section is rendered

---

### Requirement: Combined athlete total and max-events cap
A `COMBINED` report SHALL sum, per athlete and per category (the athlete's own `EnDivision . EnClass`), that athlete's points from each listed classification. The values compared and summed are the athlete-level values: individual points at full value, team and mixed points as the athlete's share. When the report declares a cap `N > 0` and the athlete earned points in more than `N` of the listed classifications, only the `N` highest values SHALL be summed. A cap of `0` means unlimited.

A value dropped by the cap SHALL reach no report — neither the athlete's `Suma` nor any club or voivodeship total. Athletes whose post-cap total is 0 SHALL be omitted from the table.

#### Scenario: Cap drops the lowest value
- **WHEN** an athlete earns 11 (team), 7 (individual) and 9,5 (mixed) and the cap is 2
- **THEN** the individual 7 is dropped and the total is 20,5

#### Scenario: Unlimited cap
- **WHEN** a `COMBINED` report declares cap `0` and an athlete earns 9 points in one listed classification and 3 in another
- **THEN** the total is 12

#### Scenario: Fewer results than the cap
- **WHEN** an athlete earns points in only one classification and the cap is 2
- **THEN** the total equals that single value; no penalty applies

---

### Requirement: Preset definitions (read-only)
Presets SHALL be defined as PHP constant arrays in `Presets.php` and read directly at calculation time. They SHALL NOT be seeded into or read from database tables, and SHALL NOT be editable through the UI. A change to a preset's point values SHALL take effect for every installation on the next code update, with no migration step.

| # | Preset | Scope | Reports | Cutoff | Source |
|---|---|---|---|---|---|
| 1 | Młodzieżowe Mistrzostwa Polski | all | `COMBINED(ind,tea,mix, cap 3)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 2 | Mistrzostwa Polski Juniorów | all | `COMBINED(ind,tea,mix, cap 3)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 3 | Puchar Polski — runda | all | `SEPARATE(ind)`, `SEPARATE(mix)` | NO | ELIM |
| 4 | Międzywojewódzkie Mistrzostwa Młodzików | all | `COMBINED(ind,tea,mix, cap 2)`, `CLUB`, `VOIVODESHIP` | YES | QUAL |
| 5 | Ogólnopolska Olimpiada Młodzieży | all | `COMBINED(ind,tea,mix, cap 2)`, `CLUB`, `VOIVODESHIP` | YES | ELIM |
| 6 | Mistrzostwa Krajowego Zrzeszenia LZS | `R` × `U24M,U24W,U21M,U21W,U18M,U18W` | `SEPARATE(ind)`, `CLUB`, `VOIVODESHIP` | NO | QUAL |

Presets 1, 2 and 5 enable `three_of_four` (their annexes carry the 4th-athlete rule). Preset 4 does **not**: MM Młodzików teams and mixed teams are **declared** 3-person club rosters ("trzech zgłoszonych zawodników"), entered by the operator as ianseo team entries — any number of sub-teams per club, all scoring, club teams only. Preset 4 declares `min_participation` (3 clubs, 2 voivodeships). LZS competitions field no team event at all: preset 6 scores individuals only, and its club/voivodeship totals are the sum of individual points.

**Brackets — preset 3 (Puchar Polski), covers juniorzy młodsi, juniorzy and seniorzy in every division:**

| | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9-16 | 17-32 |
|---|---|---|---|---|---|---|---|---|---|---|
| ind | 25 | 21 | 18 | 15 | 13 | 12 | 11 | 10 | 5 | 1 |
| mix | 25 | 21 | 18 | 15 | 13 | 12 | 11 | 10 | 5 | — |

The mixed table intentionally stops at 9-16: at most 16 pairs take part in a Puchar Polski mixed bracket.

**Brackets — preset 6 (LZS)**, the `ind` classification's bracket table:

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
