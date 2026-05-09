## Why

Polish Cup (Puchar Polski) rounds use the IORWA elimination bracket to assign auxiliary points to athletes (§8 of the Polish Cup regulations). No tool currently exists in the PL module to generate this per-round point report from a single tournament's elimination results.

## What Changes

- New dedicated page accessible from the Printouts menu within a PL tournament
- Points assigned from final elimination place using the PZŁucz Puchar Polski table: 1→25, 2→21, 3→18, 4→15, 5→13, 6→12, 7→11, 8→10, 9–16→5, 17–32→1; places 33+ receive 0 points and are omitted
- Athletes who did not enter the elimination bracket (0 points) are omitted from the report
- Within the same-points group (e.g. places 9–16 all earning 5 pts), athletes are ordered by their actual elimination place
- Results are split per division+class (RM, RW, CM, CW, …), one section per category
- Output: A4 PDF via TCPDF, streamed as a download; no persistent DB storage
- Uses the current session tournament (`$_SESSION['TourId']`) — no tournament selector needed

## Capabilities

### New Capabilities

- `cup-ranking`: Single-tournament Polish Cup auxiliary points report — reads `IndRankFinal` from the elimination bracket, applies the Puchar Polski points table, groups by division+class, and outputs a multi-section PDF with columns: Lp., Imię Nazwisko, Klub, Nr licencji, Miejsce w eliminacjach, Punkty

### Modified Capabilities

_(none)_

## Non-goals

- Cross-tournament series accumulation (future work)
- Team events (individual only)
- Compound bow special handling
- Qualification points
- On-screen HTML view (PDF only)
- Saving results to the database

## Impact

- New directory: `Modules/Sets/PL/CupRanking/` (3 PHP files)
- `menu.php`: one new `PRNT` entry guarded by `$on && TourLocRule == 'PL'`
- No DB schema changes; reads only from existing ianseo tables (`Individuals`, `Entries`, `Divisions`, `Classes`)
- No ianseo core files touched
