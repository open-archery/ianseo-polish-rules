## Why

Several PZŁucz competitions require **points-based rankings** derived from individual, team and mixed-team places: places are converted to points, then either published as standalone classifications (Puchar Polski) or aggregated per athlete and rolled up into club and voivodeship standings (Mistrzostwa, OOM, LZS). Today this is done manually after every competition. This change automates it within the PL module.

Two upcoming competitions drove the current shape of the design:

- **Puchar Polski w łukach bloczkowych i barebow** — the individual and mixed classifications are **independent**: an athlete's mixed points never merge into their individual total.
- **Mistrzostwa Krajowego Zrzeszenia LZS** — individual and team places are **summed** into a club total, then into a voivodeship total; the teams are ianseo's automatic 3-person qualification teams, and the competition is qualification-only.

A single "preset produces one merged athlete total" model cannot express both. The preset therefore declares an ordered list of **reports** over a set of **classifications**.

## What Changes

- New `PointsRanking/` module: per-tournament preset selection, calculation engine, HTML preview, PDF output, club/voivodeship diplomas.
- Presets are PHP constants — no preset tables, no seeding, no migration when point values change.
- Preset declares: a division/class **scope**, a list of **classifications** (each with rank source and bracket table), and an ordered list of **reports** (`SEPARATE`, `COMBINED`, `CLUB`, `VOIVODESHIP`).
- Two new DB tables: `PLPointsTournamentConfig` (selected preset) and `PLVoivodeshipMap` (club code → voivodeship), auto-installed on first use.
- Club and voivodeship diplomas reuse the existing `Diplomas/PLDiplomaPdf` renderer and its per-tournament configuration.
- Shared section-ordering and division-label helpers extracted from `CupRanking/` so there is one copy, not three.
- Menu entries registered in `menu.php` under the PL ruleset.

## Capabilities

### New Capabilities

- `points-ranking`: preset definition and selection, calculation engine (athlete → club → voivodeship), report composition, TCPDF output, ranking diplomas.

### Modified Capabilities

*(none)*

## Non-goals

- **Multi-tournament season aggregation** (Puchar Polski klasyfikacja generalna across rounds). It will be a separate feature with its own spec, layered on top of this one. This change only guarantees that output rows carry `Entries.EnCode` so that later layer has a stable join key.
- Merging or reworking `CombinedRanking/` (`feat/merged-ranking`) — kept as history, out of scope.
- Admin UI for editing preset point values — presets are read-only PHP constants.
- Any modification to ianseo core files.

## Impact

- **New files:** `PointsRanking/` (~7 PHP files), two PL-prefixed DB tables (auto-installed).
- **Modified files:** `menu.php` (two new entries). `CupRanking/Fun_CupRanking.php` if that branch lands first — its label/order helpers move to the shared file.
- **ianseo tables read (never written):** `Individuals`, `Teams`, `TeamComponent`, `TeamFinComponent`, `Entries`, `Countries`, `Events`, `Divisions`, `Classes`.
- **Reused PL code:** `Diplomas/PLDiplomaPdf.php`, `Diplomas/Fun_Diploma.php` (`pl_diploma_get_config`, `pl_diploma_ensure_tables`), `CupRanking/` conventions.
- **Regulation reference:** PZŁucz competition annexes for Młodzieżowe MP, MP Juniorów, Puchar Polski (Uchwała Zarządu nr 24/11/2025), Międzywojewódzkie MM Młodzików, OOM, Mistrzostwa KZ LZS.
