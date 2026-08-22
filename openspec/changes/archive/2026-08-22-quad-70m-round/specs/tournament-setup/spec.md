## MODIFIED Requirements

### Requirement: Single-Distance Round (`Setup_3_PL.php`)

The Single-Distance Round is an outdoor competition configured by `Setup_3_PL.php`. The script SHALL support two sub-rules, selected via `$SubRule`:

- **`Poland-Full`** (existing, default): each archer shoots **72 arrows** — 2 sessions of 36 arrows at the distance(s) appropriate for their category (or 40m+20m for Młodzicy U15).
- **`Poland-4x70m`** (Podwójna runda / Double Round, §2.11.1.1, §2.11.1.2): each archer shoots **144 arrows** — every session in the `Poland-Full` structure is shot twice, for every class and division, with no change to which distances are used:

| Class group | `Poland-Full` (2 sessions) | `Poland-4x70m` (4 sessions) |
| --- | --- | --- |
| R Senior/U24/U21 (M, W) | 70m, 70m | 70m, 70m, 70m, 70m |
| R U18 / Master (50+) | 60m, 60m | 60m, 60m, 60m, 60m |
| R Młodzik (U15) | 40m, 20m | 40m, 40m, 20m, 20m |
| C (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |
| B (all categories) | 50m, 50m | 50m, 50m, 50m, 50m |

Under `Poland-4x70m`, `tourDetNumDist` SHALL be `4` (instead of `2`); `tourDetMaxDistScore` SHALL remain `360` (a per-session cap, unaffected by session count). Target faces, elimination/finals configuration (`EvFinalFirstPhase`, set/cumulative match structure, first-phase cut counts), event codes, and team scoring remain identical between both sub-rules.

`Poland-4x70m` SHALL be registered only under TourType 3 in `sets.php` — it SHALL NOT appear as an option for TourType 1 (1440 Round) or TourType 6 (Indoor).

#### Scenario: Creating a tournament with the standard sub-rule

- **WHEN** an organiser creates a TourType 3 tournament with sub-rule `Poland-Full`
- **THEN** every class shoots its existing 2-session distance structure, unchanged from current behaviour

#### Scenario: Creating a tournament with the Double Round sub-rule

- **WHEN** an organiser creates a TourType 3 tournament with sub-rule `Poland-4x70m`
- **THEN** R Senior/U24/U21 archers shoot 70m in 4 sessions (144 arrows total)
- **THEN** R U18/Master archers shoot 60m in 4 sessions
- **THEN** R Młodzik (U15) archers shoot 40m, 40m, 20m, 20m (in that order)
- **THEN** Compound and Barebow archers (all categories) shoot 50m in 4 sessions
- **THEN** `tourDetNumDist` is `4` and `tourDetMaxDistScore` is `360`

#### Scenario: Elimination structure is unaffected by sub-rule choice

- **WHEN** an organiser creates a TourType 3 tournament under either sub-rule
- **THEN** the elimination first-phase cut counts (48 individual / 12 team), set/cumulative match formats, and finals structure are identical between `Poland-Full` and `Poland-4x70m`

#### Scenario: Double Round is unavailable outside TourType 3

- **WHEN** an organiser creates a tournament of TourType 1 (1440 Round) or TourType 6 (Indoor)
- **THEN** `Poland-4x70m` does not appear as a selectable sub-rule
