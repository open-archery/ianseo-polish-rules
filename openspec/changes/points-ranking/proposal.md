## Why

Several PZŁucz competitions require a **points-based club ranking** derived from individual, team, and mixed-team results: positions are converted to points, aggregated per athlete (with an optional cap on how many events count), then rolled up into club and voivodeship standings. Today this calculation is done manually after every competition. This change automates it within the PL module.

## What Changes

- New `PointsRanking/` module with per-tournament preset selection, calculation engine, HTML preview, and PDF output.
- New `PLPointsPreset`, `PLPointsEventConfig`, `PLPointsTable` DB tables storing 7 read-only competition presets with their rank→points brackets.
- New `PLVoivodeshipMap` DB table and operator UI for mapping clubs to voivodeships.
- PDF report: individual ranking → club ranking (optional) → voivodeship ranking (optional), all in one document.
- Menu entry registered in `menu.php` under the PL ruleset.

## Capabilities

### New Capabilities

- `points-ranking`: Full points-ranking feature — preset management, per-tournament config, calculation engine (athlete → club → voivodeship), and TCPDF PDF output.

### Modified Capabilities

*(none)*

## Non-goals

- Multi-tournament season aggregation (e.g. Puchar Polski Seniorów final-round qualification sum across 4 rounds) — out of scope.
- Admin UI for editing preset point values — presets are read-only constants seeded at install time.
- Any modification to ianseo core files.

## Impact

- **New files:** `PointsRanking/` directory (~6 PHP files), new PL-prefixed DB tables (auto-installed).
- **Modified files:** `menu.php` (one new menu entry).
- **ianseo tables read (never written):** `Individuals`, `Teams`, `TeamFinComponent`, `Entries`, `Companies`.
- **Regulation reference:** PZŁucz competition regulations — individual competition annexes for Młodzieżowe MP, MP Juniorów, PP Juniorów Młodszych, PP Juniorów, PP Seniorów, Międzywojewódzkie MM Młodzików, Ogólnopolska Olimpiada Młodzieży.
- **Spec produced by:** Advisor agent → `openspec/specs/points-ranking/spec.md`
- **Design produced by:** Developer agent → `openspec/changes/points-ranking/design.md`
