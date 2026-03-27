## Why

PZŁucz national team selection requires a cross-tournament aggregate ranking: athletes compete in two separate ianseo 70m tournaments on consecutive days, and their qualification and elimination places from both events are converted to points and summed to produce a final ordered list. No such cross-tournament ranking exists in ianseo or the PL module today.

## What Changes

- New dedicated page accessible from the Printouts menu within a PL tournament
- Two tournament selects (current tournament pre-selected, second optional/empty)
- Combined ranking computed per division+class (e.g. RM, RW, CM, CW) by merging results from both tournaments, matching athletes by licence number (`EnCode`)
- Points formula: qualification place → 15..1 pts (places 1–15), elimination final rank → 30..2 pts (places 1–15), zero beyond 15th
- Tiebreaker: best qualification score (QuScore) across both tournaments ("Najlepsze 2x70m")
- Output: A4 PDF (TCPDF), one section per division+class, columns matching the PZŁucz result sheet format

## Capabilities

### New Capabilities

- `combined-ranking`: Cross-tournament aggregate ranking page — collects qual rank, qual score, and final elimination rank from up to two PL tournaments; applies PZŁucz points formula; outputs a multi-section PDF with one table per division+class

### Modified Capabilities

_(none — no existing spec-level requirements change)_

## Non-goals

- Validation that selected tournaments are 70m format
- Saving or persisting the combined ranking to the database
- Team events (individual only)
- Print-to-screen HTML view (PDF output only)

## Impact

- New directory: `Modules/Sets/PL/CombinedRanking/` (3 PHP files)
- `menu.php`: one new `PRNT` entry guarded by `$on && TourLocRule == 'PL'`
- No DB schema changes; reads only from existing ianseo tables (`Entries`, `Qualifications`, `Individuals`, `Finals`, `Tournament`)
- No changes to ianseo core
