## 1. Grouping logic

- [x] 1.1 Extend `pl_abc_acd_load_athletes()` with `$groupByDiv`/`$groupByClass` boolean parameters, joining `DivViewOrder`/`ClViewOrder`, returning an ordered list of groups (each with its own club map) instead of one flat club map; verify a unit test confirms both-`false` returns a result equivalent to today's single flat club map.
- [x] 1.2 Unit test: "separate classes" only groups by `EnClass`, pooling different divisions that share a class value.
- [x] 1.3 Unit test: "separate divisions" only groups by `EnDivision`, pooling different classes that share a division value.
- [x] 1.4 Unit test: both flags group by the division+class combination.
- [x] 1.5 Unit test: groups are emitted ordered by `(DivViewOrder, ClViewOrder)` ascending, not by query/appearance order.

## 2. Boss-aligned carving

- [x] 2.1 Implement `pl_abc_acd_carve_group_ranges(int $tgtFrom, int $tgtTo, array $groupSizes): array` returning ordered `[from, to]` boss sub-ranges (3 usable slots/boss); unit test an exact-fit case.
- [x] 2.2 Unit test: a group whose size isn't a multiple of 3 leaves its boundary boss's leftover letters unused and the next group starts at the next fresh boss.
- [x] 2.3 Unit test: combined group sizes exceeding `$tgtTo - $tgtFrom + 1` bosses truncate the last group(s) rather than throwing, leaving the excess for the caller to report as unassigned.

## 3. Wave tally threading

- [x] 3.1 Implement `pl_abc_acd_merge_tally(array $tally, array $assignments): array`, counting `QuLetter`-equivalent `A`/`B` as wave1 and `C`/`D` as wave2 per club from an assignment map, additive into the passed-in tally; unit test the merge arithmetic.
- [x] 3.2 Unit test: merging a group's assignments into an empty base tally produces the same per-club counts as reading that group's saved rows back through `pl_abc_acd_session_wave_tally()`.

## 4. Wire into the UI page

- [x] 4.1 Add "Rozdziel dywizje" / "Rozdziel klasy" checkboxes to `SetTargetABCACD.php`, defaulting to checked when the request has no prior value for them (first load).
- [x] 4.2 Replace the single-group call in `SetTargetABCACD.php` with a loop over `pl_abc_acd_load_athletes()`'s groups: carve ranges via `pl_abc_acd_carve_group_ranges()`, call `pl_abc_acd_assign()` per group with the running tally, fold each group's result into the running tally via `pl_abc_acd_merge_tally()` before the next group.
- [x] 4.3 Merge every group's `$assignments` into one flat map before the existing preview-rendering/`pl_abc_acd_save()` code, unchanged downstream of that point.
- [x] 4.4 Manual check: two classes matched by one `Event` pattern, both checkboxes checked, land on disjoint boss ranges and the preview table shows both groups correctly labeled. Verified against tournament 130 (4RPPJJM), session 1, `Event=RU18%` (U18M=46 athletes, U18W=50): U18M landed on bosses 1-16, U18W on 17-33, zero mixed bosses.
- [x] 4.5 Manual check: unchecking both checkboxes reproduces the exact pre-change pooled output for the same inputs (regression check on a test tournament). Same request with both checkboxes unchecked: all 96 athletes pooled into bosses 1-32 with both classes interleaved on many shared bosses, confirming the pooled path is unchanged. Both checks ran in preview mode only (no `DoAssign`); verified via direct DB query that the tournament's real `QuTarget` assignments were untouched before/after.

## 5. Docs

- [x] 5.1 Run `tools/test.cmd` (or `tools/test.sh`) and confirm the full suite passes.
- [x] 5.2 Ready for `/opsx:archive-change` once manual checks in section 4 are confirmed.
