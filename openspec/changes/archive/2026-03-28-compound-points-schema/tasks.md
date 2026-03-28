## 1. Update points function

- [x] 1.1 Add `$division = 'R'` parameter to `pl_combined_ranking_points()` in `Fun_CombinedRanking.php`
- [x] 1.2 Add compound qualification lookup table `[20, 19, 18, 17, 11, 10, 9, 8, 1]` and dispatch when `$division === 'C'`
- [x] 1.3 Add compound elimination lookup table `[30, 26, 25, 21, 20, 18, 15, 11, 5]` and dispatch when `$division === 'C'`
- [x] 1.4 Update the docblock to document the `$division` parameter and both schemas

## 2. Update call sites

- [x] 2.1 Pass `$a['division']` to all four `pl_combined_ranking_points()` calls inside `pl_combined_ranking_compute()`
- [x] 2.2 For Compound athletes with no bracket entry, fall back to `qual_rank` as `elim_rank` in `pl_combined_ranking_compute()` (for both Day 1 and Day 2)

## 3. Update PDF renderer

- [x] 3.1 In `PrnCombinedRanking.php`, make the tiebreaker column label division-aware: "Najl. 2x50m" when the section's `divClass` starts with `'C'`, "Najl. 2x70m" otherwise
