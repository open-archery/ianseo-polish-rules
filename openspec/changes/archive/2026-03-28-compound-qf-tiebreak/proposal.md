## Why

PZŁucz §4.3 requires that when two Compound athletes share the same score in a 1/4-final match, their elimination place (which determines combined ranking points) is resolved by the count of 10/X/9 arrows from that match. ianseo does not store per-arrow scores for elimination rounds, so this tiebreaker cannot be applied automatically. Without it, athletes can receive identical elimination points when they should be split across adjacent places (e.g. 5th vs 6th), producing an incorrect combined ranking.

## What Changes

- New `PLQfTiebreak` database table stores 10/X/9 arrow counts per athlete per tournament (persistent, auto-installed)
- Combined ranking page detects QF-loser place ties within each selected tournament and shows a warning with affected athletes
- Inline form on the combined ranking page allows entering 10/X/9 counts for flagged athletes; data is saved to `PLQfTiebreak`
- Combined ranking computation uses stored counts to assign correct elimination places (and thus correct points) for Compound QF ties
- PDF marks any still-unresolved ties with a footnote

## Capabilities

### New Capabilities

- `compound-qf-tiebreak`: Persistent storage and UI for 10/X/9 arrow counts used to resolve Compound QF place ties; detection of ambiguous elimination places in the combined ranking; corrected points assignment when data is present

### Modified Capabilities

- `combined-ranking`: Combined ranking page gains tie-detection warning and data-entry form; PDF gains unresolved-tie marker

## Non-goals

- Automatic reading of 10/X/9 counts from ianseo (data is not stored there)
- Applying this tiebreaker to Recurve or other divisions
- Changing the combined ranking sort order — it remains total_pts DESC, best_2x50m DESC; the 10/X/9 data corrects upstream elimination places, not the final sort

## Impact

- New DB table: `PLQfTiebreak` (auto-installed)
- `CombinedRanking/Fun_CombinedRanking.php`: new load/save functions for QF tiebreak data; tie detection logic; corrected elimination place assignment
- `CombinedRanking/CombinedRanking.php`: warning banner + inline data-entry form
- `CombinedRanking/PrnCombinedRanking.php`: unresolved-tie footnote in PDF
