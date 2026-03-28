## Why

PZŁucz compound bow national team selection uses a different points schema than recurve: qualification and elimination places map to points via a fixed lookup table (9 places), not the arithmetic formula currently used for all divisions. The existing `pl_combined_ranking_points()` function is division-unaware and applies the recurve formula to compound athletes, producing incorrect totals.

## What Changes

- `pl_combined_ranking_points($place, $type)` gains a `$division` parameter; compound division (`'C'`) uses lookup tables, all other divisions continue using the existing formula
- `pl_combined_ranking_compute()` passes `$a['division']` at the four `pl_combined_ranking_points()` call sites
- The tiebreaker column label is division-aware: "Najl. 2x50m" for Compound, "Najl. 2x70m" for Recurve
- The combined-ranking spec is updated to document the compound-specific points schema and column label

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `combined-ranking`: Points formula requirements change — compound athletes use a division-specific lookup table instead of the shared arithmetic formula

## Non-goals

- Configurable or per-tournament schema overrides
- Barebow division handling (out of scope)
- Any changes outside `Fun_CombinedRanking.php`, `PrnCombinedRanking.php`, and their spec

## Impact

- `CombinedRanking/Fun_CombinedRanking.php`: function signature change + new lookup tables
- `CombinedRanking/PrnCombinedRanking.php`: division-aware tiebreaker column label per section
- `openspec/changes/combined-ranking/specs/combined-ranking/spec.md`: delta requirements added
