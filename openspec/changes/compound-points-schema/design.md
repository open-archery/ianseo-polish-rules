## Context

`pl_combined_ranking_points($place, $type)` in `Fun_CombinedRanking.php` applies a single arithmetic formula to all divisions. PZŁucz rules require compound athletes to use a fixed lookup table with 9 places and non-linear point values. The function is called at four sites inside `pl_combined_ranking_compute()`.

## Goals / Non-Goals

**Goals:**
- Add `$division` parameter to `pl_combined_ranking_points()`
- Compound division (`'C'`) dispatches to lookup tables; all other divisions use the existing formula unchanged
- Pass `$a['division']` at all four call sites in `pl_combined_ranking_compute()`

**Non-Goals:**
- Barebow division handling
- Per-tournament or configurable schema overrides
- Any other file changes beyond `Fun_CombinedRanking.php` and `PrnCombinedRanking.php`

## Decisions

### D1: Division parameter on existing function (Option A)

Add `$division` as the third parameter with a default of `'R'` for safety. Inside the function, branch on `$division === 'C'` to use lookup tables; all other values fall through to the existing formula. Rejected alternative (two separate functions): adds public API surface for no benefit — the division is always available at call sites.

### D2: Lookup tables as PHP arrays inside the function

The compound point values have no arithmetic pattern, so they are encoded as two `const`-style arrays inside the function body:

```
Qualification lookup (index = place - 1):
  [20, 19, 18, 17, 11, 10, 9, 8, 1]

Elimination lookup (index = place - 1):
  [30, 26, 25, 21, 20, 18, 15, 11, 5]
```

Places 10+ return 0. Null place returns 0.

## Files to Create / Modify

```
MODIFY  Modules/Sets/PL/CombinedRanking/Fun_CombinedRanking.php
  - pl_combined_ranking_points(): add $division parameter; add compound lookup tables
  - pl_combined_ranking_compute(): pass $a['division'] at all 4 call sites

MODIFY  Modules/Sets/PL/CombinedRanking/PrnCombinedRanking.php
  - Per-section column label: "Najl. 2x50m" when divClass starts with 'C', "Najl. 2x70m" otherwise
```

## Risks / Trade-offs

- **Signature change** — `pl_combined_ranking_points()` is an internal function, not part of any external API. No other callers exist outside this file. Risk: none.
- **Lookup table maintenance** — if PZŁucz updates the compound schema, the arrays must be edited manually. Acceptable: schema changes are infrequent and always come with a regulation document.
