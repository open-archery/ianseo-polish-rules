## ADDED Requirements

### Requirement: Double Round (`Setup_37_PL.php`)

The Double Round (Podwójna runda, §2.3.1.10.7) SHALL be configured by `Setup_37_PL.php` on ianseo TourType 37 (`Type_2x70mRound`), registered in `sets.php` with the single sub-rule `Poland-Full`.

Each archer shoots **144 arrows** — every session of the Single-Distance Round structure shot twice, for every class and division, with no change to which distances are used:

| Class group | Single-Distance Round (2 sessions) | Double Round (4 sessions) |
| --- | --- | --- |
| R Senior/U24/U21 (M, W) | 70m, 70m | 70m, 70m, 70m, 70m |
| R U18 / Master (50+) | 60m, 60m | 60m, 60m, 60m, 60m |
| R Młodzik (U15) | 40m, 20m | 40m, 40m, 20m, 20m |
| C (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |
| B (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |

`tourDetNumDist` SHALL be `4`; `tourDetMaxDistScore` SHALL remain `360` (a per-session cap, unaffected by session count). Divisions, classes, target faces, event codes, elimination and finals configuration, and team scoring SHALL be identical to the Single-Distance Round.

#### Scenario: Creating a Double Round tournament

- **WHEN** an organiser creates a TourType 37 tournament under the Poland (PZŁucz) rule set
- **THEN** R Senior/U24/U21 archers shoot 70m in 4 sessions (144 arrows total)
- **THEN** R U18/Master archers shoot 60m in 4 sessions
- **THEN** R Młodzik (U15) archers shoot 40m, 40m, 20m, 20m in that order
- **THEN** Compound and Barebow archers shoot 50m in 4 sessions
- **THEN** `tourDetNumDist` is `4` and `tourDetMaxDistScore` is `360`

#### Scenario: Elimination structure matches the Single-Distance Round

- **WHEN** an organiser creates a TourType 37 tournament
- **THEN** the first-phase cut counts (48 individual / 12 team), set and cumulative match formats, and finals structure are identical to a TourType 3 tournament

### Requirement: Youth Round (`Setup_16_PL.php`)

The Youth Round SHALL be configured by `Setup_16_PL.php` on ianseo TourType 16, registered in `sets.php` with the single sub-rule `Poland-Full`. It is a youth-only competition: the script SHALL create **only** the U15 and U12 classes, in the divisions those classes are eligible for (U15: R and C; U12: R only), and SHALL NOT create senior, U24, U21, U18 or Master classes.

U15 archers shoot the 40m/20m Round (§2.3.1.10.5): 36 arrows at 40m and 36 arrows at 20m. Target faces by division:

| Division | 40m | 20m |
| --- | --- | --- |
| R (Recurve) | 122cm | 80cm |
| C (Compound) | 80cm | 60cm |

U12 archers shoot the Children's Round (§2.3.1.10.11): **18 arrows at each of four distances**, shot longest to shortest. The two longer distances SHALL use a 122cm face and the two shorter distances an 80cm face. The four distance values are a configuration parameter of this setup script, not a regulation constant — the regulation leaves them to the organiser, and the defaults are recorded in the design.

Both classes shoot **3-arrow ends** (§2.4.1.1).

#### Scenario: Creating a Youth Round tournament

- **WHEN** an organiser creates a TourType 16 tournament under the Poland (PZŁucz) rule set
- **THEN** only U15M, U15W, U12M and U12W classes exist, in their eligible divisions
- **THEN** no senior, U24, U21, U18 or Master class is created

#### Scenario: U15 distances and faces

- **WHEN** a Recurve U15 archer is entered in a TourType 16 tournament
- **THEN** they shoot 36 arrows at 40m on a 122cm face and 36 arrows at 20m on an 80cm face
- **WHEN** a Compound U15 archer is entered
- **THEN** they shoot 36 arrows at 40m on an 80cm face and 36 arrows at 20m on a 60cm face

#### Scenario: U12 children's round

- **WHEN** a U12 archer is entered in a TourType 16 tournament
- **THEN** they shoot 18 arrows at each of four distances, ordered longest to shortest
- **THEN** the two longer distances use a 122cm face and the two shorter distances an 80cm face

#### Scenario: Youth ends are three arrows

- **WHEN** any archer shoots in a TourType 16 tournament
- **THEN** ends are 3 arrows, for both U15 and U12

## MODIFIED Requirements

### Requirement: Single-Distance Round (`Setup_3_PL.php`)

The Single-Distance Round is an outdoor competition configured by `Setup_3_PL.php` on TourType 3. The script SHALL support exactly one sub-rule, `Poland-Full`: each archer shoots **72 arrows** — 2 sessions of 36 arrows at the distance(s) appropriate for their category (or 40m+20m for Młodzicy U15).

The `Poland-4x70m` sub-rule SHALL be removed. The Double Round it provided is configured by `Setup_37_PL.php` on TourType 37 instead, so `sets.php` SHALL register no sub-rule other than `Poland-Full` for TourType 3, and `Setup_3_PL.php` SHALL NOT branch on `$subRuleName`.

`tourDetNumDist` SHALL be `2` and `tourDetMaxDistScore` SHALL be `360`. Target faces, elimination and finals configuration, event codes and team scoring are unchanged by this change.

Tournaments already created under `Poland-4x70m` keep the `ToTypeSubRule` value stored on the tournament record. The module no longer registers that name, so re-running setup on such a tournament SHALL be treated as unsupported; the competition must be recreated as TourType 37 if its setup has to be rebuilt.

#### Scenario: Creating a Single-Distance Round tournament

- **WHEN** an organiser creates a TourType 3 tournament under the Poland (PZŁucz) rule set
- **THEN** every class shoots its 2-session distance structure and `tourDetNumDist` is `2`

#### Scenario: Double Round is no longer offered on TourType 3

- **WHEN** an organiser creates a TourType 3 tournament
- **THEN** `Poland-Full` is the only selectable sub-rule
- **THEN** `Poland-4x70m` does not appear

#### Scenario: An existing Double Round tournament is opened

- **WHEN** a tournament created earlier with `ToTypeSubRule = 'Poland-4x70m'` is opened
- **THEN** its stored scores, distances and events are untouched by this change
- **THEN** re-running tournament setup on it is unsupported
