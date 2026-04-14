## ADDED Requirements

### Requirement: Preset selection per tournament
The system SHALL allow the operator to select one competition preset for the current tournament from a list of read-only predefined presets. The selection SHALL be persisted per tournament. Exactly one preset may be active at a time per tournament.

#### Scenario: Operator selects a preset
- **WHEN** the operator opens the points ranking configuration page and chooses a preset from the dropdown
- **THEN** the selection is saved and subsequent calculation and PDF use that preset's rules

#### Scenario: No preset selected
- **WHEN** no preset has been selected for the current tournament
- **THEN** the ranking page shows only the configuration form and suppresses the calculation and PDF controls

---

### Requirement: Individual points assignment
The system SHALL assign points to each athlete based on their rank (qualification or final, per preset config) using the preset's individual rank→points bracket table. Brackets are inclusive ranges: an athlete at rank R receives the points value of the bracket where rank_from ≤ R ≤ rank_to. Athletes with a special rank (DSQ, DNS, DNF — IndRankFinal ≥ 29999) SHALL receive 0 points. Athletes outside all brackets SHALL receive 0 points.

#### Scenario: Athlete rank falls within a bracket
- **WHEN** an athlete's rank is 5 and the bracket 5-6 awards 6 points
- **THEN** the athlete receives 6 points for the individual event

#### Scenario: Athlete rank outside all brackets
- **WHEN** an athlete's rank is 70 and no bracket covers rank 70
- **THEN** the athlete receives 0 points

#### Scenario: DSQ athlete
- **WHEN** an athlete has IndRankFinal ≥ 29999 (DSQ/DNS/DNF)
- **THEN** the athlete receives 0 points regardless of rank

---

### Requirement: Cutoff rule for individual event
When the preset has cutoff enabled, the system SHALL set the points of the last-ranked athlete to 0 if the total number of starters (athletes with a valid rank < 29999) is less than the maximum rank_to value in the individual bracket table.

#### Scenario: Cutoff applies — last athlete zeroed
- **WHEN** 12 athletes compete and the individual bracket table extends to rank 15
- **THEN** the athlete at rank 12 receives 0 points (all others receive their bracket points normally)

#### Scenario: Cutoff does not apply — sufficient starters
- **WHEN** 16 or more athletes compete and the bracket table extends to rank 15
- **THEN** all athletes receive their bracket points normally

#### Scenario: Preset has cutoff disabled
- **WHEN** the preset's cutoff_enabled flag is false (e.g. PP Juniorów Młodszych, PP Juniorów, PP Seniorów)
- **THEN** all athletes with a valid rank receive their bracket points; the last athlete is not zeroed

---

### Requirement: Team points assignment with roster splitting
The system SHALL assign points to each athlete who is a member of a team by splitting the team's event points evenly among counting team members. Team event points are determined by the team's rank using the preset's team bracket table, subject to the same cutoff rule as the individual event. The roster is read from ianseo's `TeamFinComponent` table.

#### Scenario: Team with 3 members
- **WHEN** a team ranked 1st has 3 members and the preset awards 33 points for rank 1
- **THEN** each of the 3 members receives 33 ÷ 3 = 11 points for the team event

#### Scenario: Team with 4 members — 3-of-4 rule applies
- **WHEN** a team ranked 1st has 4 members, the preset has three_of_four enabled, and one member has the worst individual qualification rank
- **THEN** the member with the highest IndRank (worst qualifier) receives 0 team points; the other 3 each receive 33 ÷ 3 = 11 points

#### Scenario: Team event disabled in preset
- **WHEN** the preset marks the team event as disabled (e.g. PP Juniorów Młodszych)
- **THEN** all athletes receive 0 team points regardless of team rank

#### Scenario: Preset uses qualification source for team
- **WHEN** the preset's team event phase_source is QUAL
- **THEN** team rank is read from TeRank (qualification rank), not TeRankFinal

---

### Requirement: Mixed team points assignment
The system SHALL assign points to each athlete who is a member of a mixed team by splitting the mixed team's event points equally between the 2 members (1 male + 1 female). Team roster is read from `TeamFinComponent`. The same cutoff rule applies.

#### Scenario: Mixed team ranked 2nd
- **WHEN** a mixed team is ranked 2nd and the preset awards 19 points for rank 2
- **THEN** each of the 2 members receives 19 ÷ 2 = 9.5 points for the mixed event

#### Scenario: Mixed event disabled in preset
- **WHEN** the preset marks the mixed event as disabled
- **THEN** all athletes receive 0 mixed event points

---

### Requirement: Max-events cap per athlete
The system SHALL cap the number of events from which an athlete accumulates points according to the preset's max_events value. If an athlete earned points in more events than the cap, the lowest point value(s) are dropped and only the highest-valued events count toward the total. If max_events is 0 (unlimited), all earned points are summed.

#### Scenario: Athlete earns points in 3 events, cap is 2
- **WHEN** an athlete earns 11 (team), 7 (individual), 9.5 (mixed) and max_events = 2
- **THEN** the 7-point individual result is dropped and the athlete's total is 11 + 9.5 = 20.5

#### Scenario: Cap equals number of events earned
- **WHEN** an athlete earns points in exactly 2 events and max_events = 2
- **THEN** both event scores are included in the total; no dropping occurs

#### Scenario: Athlete earns points in only 1 event, cap is 2
- **WHEN** an athlete earns points in only the individual event and max_events = 2
- **THEN** the total equals the individual points; no penalty applies

---

### Requirement: Individual athlete ranking
The system SHALL produce a ranked list of athletes sorted by total points (descending). Athletes with equal total points share the same rank. The list SHALL include: rank, athlete name, club name, points per event type, total points.

#### Scenario: Ranking order
- **WHEN** athlete A has 25 total points and athlete B has 20 total points
- **THEN** athlete A is ranked 1st and athlete B is ranked 2nd

#### Scenario: Tied athletes
- **WHEN** two athletes each have 20 total points
- **THEN** both are assigned the same rank (tie is not broken further)

---

### Requirement: Club ranking
When the preset has club_rank_enabled, the system SHALL compute a club ranking by summing all athletes' total points per club. Clubs are identified by their ianseo company record (CoId / CoName). The list SHALL include: rank, club name, voivodeship (if voiv_rank_enabled), total club points.

#### Scenario: Club points aggregation
- **WHEN** athletes from "Łucznik Kraków" have totals of 25, 20, and 15
- **THEN** "Łucznik Kraków" has 60 club points

#### Scenario: Preset has club ranking disabled
- **WHEN** the preset is PP Juniorów (club_rank_enabled = false)
- **THEN** no club ranking is computed or shown

---

### Requirement: Voivodeship ranking
When the preset has voiv_rank_enabled, the system SHALL compute a voivodeship ranking by summing club totals per voivodeship using the PLVoivodeshipMap mapping table. Clubs not mapped to any voivodeship SHALL be listed as "Nieprzypisane" (unassigned) and included in the individual ranking but excluded from voivodeship totals.

#### Scenario: Voivodeship aggregation
- **WHEN** three clubs from "Małopolska" have club totals of 60, 45, and 30
- **THEN** "Małopolska" has 135 voivodeship points

#### Scenario: Club not mapped
- **WHEN** a club has no entry in PLVoivodeshipMap
- **THEN** the club appears in the club ranking with "Nieprzypisane" and is excluded from voivodeship totals

---

### Requirement: Voivodeship mapping management
The system SHALL provide an operator UI page to assign each club in the current tournament to a voivodeship. Mappings SHALL be stored in the PLVoivodeshipMap table and persisted globally (not per tournament). The operator SHALL be able to update any mapping at any time.

#### Scenario: Operator maps a club
- **WHEN** the operator selects "Małopolska" for club "Łucznik Kraków" and saves
- **THEN** the mapping is stored and applied in all subsequent voivodeship ranking calculations for any tournament

#### Scenario: Pre-existing mapping shown
- **WHEN** the operator opens the mapping page and a club already has a mapping
- **THEN** the existing voivodeship is pre-selected in the dropdown

---

### Requirement: PDF report generation
The system SHALL generate a single TCPDF document containing the individual ranking table, followed by the club ranking table (if enabled), followed by the voivodeship ranking table (if enabled). The document SHALL include a header with tournament name, date, and preset name on each page. Individual ranking columns: Miejsce, Zawodnik, Klub, points per event type (labelled), Suma. Club ranking columns: Miejsce, Klub, Województwo (if voiv enabled), Suma. Voivodeship ranking columns: Miejsce, Województwo, Suma.

#### Scenario: Full PDF with all three sections
- **WHEN** the preset has club_rank_enabled and voiv_rank_enabled both true
- **THEN** the PDF contains individual, club, and voivodeship sections in that order

#### Scenario: Individual-only PDF
- **WHEN** the preset has club_rank_enabled = false (e.g. PP Juniorów)
- **THEN** the PDF contains only the individual ranking section

#### Scenario: Fractional points display
- **WHEN** an athlete's team event share is 11.333... points
- **THEN** the PDF displays the value rounded to 2 decimal places (11.33)

---

### Requirement: Preset definitions (read-only)
The system SHALL ship with 7 predefined read-only presets seeded into DB tables on first access. The preset data is defined as PHP constants and cannot be modified through the UI.

| # | Name | Max events | Cutoff | Club | Voiv. | 3-of-4 | Team source |
|---|---|---|---|---|---|---|---|
| 1 | Młodzieżowe Mistrzostwa Polski | 3 | YES | YES | YES | YES | FINAL |
| 2 | Mistrzostwa Polski Juniorów | 2 | YES | YES | YES | YES | FINAL |
| 3 | PP Juniorów Młodszych | 1 | NO | NO | NO | N/A | FINAL |
| 4 | PP Juniorów | 1 | NO | NO | NO | N/A | FINAL |
| 5 | PP Seniorów | 1 | NO | NO | NO | N/A | FINAL |
| 6 | Między. M. Młodzików | 2 | YES | YES | YES | YES (3 only) | QUAL |
| 7 | Ogólnopolska Olimpiada Młodzieży | 2 | YES | YES | YES | YES | FINAL |

**Points brackets — Individual:**

| Preset | 1 | 2 | 3-4 | 5 | 5-6 | 6 | 7 | 7-10 | 8 | 9-12 | 9-16 | 10-20 | 11-16 | 13-15 | 17-24 | 17-32 | 25-32 | 33-64 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| #1 MP Młodzieżowe | 15 | 12 | 10 | 8 | — | 7 | 6 | — | 5 | 4 | — | — | — | 2 | — | — | — | — |
| #2/#7 MP Jun / OOM | 12 | 10 | 8 | — | 6 | — | — | 5 | — | — | 4 | — | — | — | 3 | — | 2 | 1 |
| #3/#4/#5 PP* | 25 | 21 | — | — | — | — | — | — | — | — | 5 | — | — | — | — | 1 | — | — |
| #6 Między. Młodzicy | 5 | 4 | 3 | — | — | — | — | — | — | — | — | 1 | — | — | — | — | — | — |

*PP* individual: 3→18, 4→15, 5→13, 6→12, 7→11, 8→10, 9-16→5, 17-32→1

**Points brackets — Team:**

| Preset | 1 | 2 | 3-4 | 5 | 5-6 | 6-7 | 8 | 9-10 |
|---|---|---|---|---|---|---|---|---|
| #1 MP Młodzieżowe | 33 | 27 | 22 | 10 | — | 8 | 4 | 3 |
| #2/#7 MP Jun / OOM | 27 | 22 | 18 | 10 | — | 8 | 7 | 6 |
| #6 Między. Młodzicy | 5 | 4 | 3 | — | 2 | — | — | — |

**Points brackets — Mixed:**

| Preset | 1 | 2 | 3-4 | 5 | 6-7 | 6-8 | 8-10 | 9 | 10-13 | 11-12 | 5-8 |
|---|---|---|---|---|---|---|---|---|---|---|---|
| #1 MP Młodzieżowe | 24 | 19 | 16 | 8 | 7 | — | 3 | — | — | 1 | — |
| #2/#7 MP Jun / OOM | 19 | 16 | 12 | 8 | — | 6 | — | 4 | 2 | — | — |
| #6 Między. Młodzicy | 5 | 4 | 3 | — | — | — | — | — | — | — | 2 |

#### Scenario: Preset seeded on first access
- **WHEN** the points ranking page is accessed for the first time on a fresh ianseo installation
- **THEN** the PLPointsPreset, PLPointsEventConfig, and PLPointsTable tables are created and populated automatically before any UI is rendered
