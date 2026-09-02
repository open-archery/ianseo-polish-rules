## Why

Puchar Polski is shot as 4 rounds hosted by 4 different organisers, each scored with the existing `pp` points preset. The overall cup winner is the athlete with the highest sum across all 4 rounds, but the earlier rounds' ianseo databases are not available to the final round's host — today there is no way to produce the combined classification or its diplomas.

## What Changes

- New cup layer above the points-ranking module: rounds are stored as flat point snapshots in `PL` tables, keyed by cup year, round number, classification (`ind` / `mix`) and category.
- CSV import of earlier rounds (place, points, qualification score per athlete/pair) and CSV export of the current round in the same format, so a later host can consume it directly.
- One-click snapshot of the current tournament: runs the existing `pp` calculation and stores it as this tournament's round. The round number is remembered per tournament; re-snapshotting overwrites it. The page warns when the stored snapshot no longer matches the live calculation.
- Combined cup classification (HTML + PDF), sectioned per category, showing per-round points and the total. Renders with any subset of rounds present, so it also serves as a standings table after round 2 or 3.
- Tie-breaks exactly as the annexes state them, per cup series: juniors and juniors młodsi use best place then best qualification score, barebow uses the qualification score alone, and seniors, compound and mixed have no stated step — those ties stay shared rather than being decided by an invented rule. Where the regulation prescribes a baraż, the operator can record its outcome after the final round so the tied rows get distinct ranks.
- Cup diplomas via the existing `PLDiplomaPdf` renderer, using a cup-specific competition name and the tournament's existing diploma configuration for everything else.
- The whole feature is available only while the `pp` (Puchar Polski) preset is active.

## Non-goals

- Automatic qualification to the Final Round (round winners qualify regardless of standing — not derivable from points).
- Any preset other than `pp`; other presets have no multi-round equivalent.
- Cup-wide club or voivodeship rollups; a cup cut-off line after place 8.
- Deciding a baraż automatically, and filling the regulation’s gaps for seniors, compound and mixed — the system records a judge’s shoot-off result but never invents a tie-break the annex does not state.

## Capabilities

### New Capabilities
- `cup-ranking`: multi-round Puchar Polski aggregation — round snapshots, CSV import/export, combined classification, tie-breaks, cup diplomas.

### Modified Capabilities

- `points-ranking`: the round tables list only subjects whose place matched a bracket. A no-result, a DSQ/DNS/DNF or a place beyond the last bracket is omitted (as the requirement already said), while a cutoff-zeroed row stays listed with 0 — and every row remains in the calculation, which the cup layer reads for its tie-breaks.

## Impact

New `PointsRanking/Cup*.php` files, two new `PL` tables, one `menu.php` entry, and a reused `PLDiplomaPdf` / `pl_diploma_get_config()`. Spec by the Advisor role, design and implementation by the Developer role.
