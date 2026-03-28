## 1. Update points function

- [ ] 1.1 Add `$division = 'R'` parameter to `pl_combined_ranking_points()` in `Fun_CombinedRanking.php`
- [ ] 1.2 Add compound qualification lookup table `[20, 19, 18, 17, 11, 10, 9, 8, 1]` and dispatch when `$division === 'C'`
- [ ] 1.3 Add compound elimination lookup table `[30, 26, 25, 21, 20, 18, 15, 11, 5]` and dispatch when `$division === 'C'`
- [ ] 1.4 Update the docblock to document the `$division` parameter and both schemas

## 2. Update call sites

- [ ] 2.1 Pass `$a['division']` to all four `pl_combined_ranking_points()` calls inside `pl_combined_ranking_compute()`

## 3. Update PDF renderer

- [ ] 3.1 In `PrnCombinedRanking.php`, make the tiebreaker column label division-aware: "Najl. 2x50m" when the section's `divClass` starts with `'C'`, "Najl. 2x70m" otherwise
