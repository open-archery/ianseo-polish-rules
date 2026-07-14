## 1. Wave tally query

- [ ] 1.1 Add `pl_abc_acd_session_wave_tally(int $tourId, int $sesOrder, string $excludeEvent): array` to `Fun_SetTargetABCACD.php`, joining `Qualifications`+`Entries` like `pl_abc_acd_load_athletes`, filtered to `QuTarget!=0` and `CONCAT(TRIM(EnDivision),TRIM(EnClass)) NOT LIKE excludeEvent`, grouped by `EnCountry`, returning `club_code => ['wave1' => int, 'wave2' => int]` (A/B → wave1, C/D → wave2).
- [ ] 1.2 Unit test: tally correctly splits A/B vs C/D and excludes the current class's own rows.
- [ ] 1.3 Unit test: tally excludes rows from other sessions (`QuSession` filter).
- [ ] 1.4 Unit test: tally excludes unassigned rows (`QuTarget=0`).

## 2. Bias in the assignment algorithm

- [ ] 2.1 Add `array $waveTally = []` as a third parameter to `pl_abc_acd_assign()`.
- [ ] 2.2 Implement `needA(club) = tally[club]['wave2'] - tally[club]['wave1']` (0 when club absent from tally).
- [ ] 2.3 Single-club case: assign column A unless `needA(club0) < 0`, then use column C.
- [ ] 2.4 Two-plus-club case: swap the `club0→A / club1→C` default only when `needA(club1) > needA(club0)` (strict).
- [ ] 2.5 Clubs 2+ overflow case: use `needA(club)` sign to choose remaining-A-first vs remaining-C-first search order; leave `B → D` fallback order unchanged.
- [ ] 2.6 Unit test: default parameter (`[]`) reproduces every existing `pl_abc_acd_assign` test unchanged.
- [ ] 2.7 Unit test: two-club swap triggers when `club1` needs A more than `club0`.
- [ ] 2.8 Unit test: tie (equal or missing tallies) keeps the rank-order default.
- [ ] 2.9 Unit test: single club with wave1-heavy history gets column C.
- [ ] 2.10 Unit test: overflow club (rank 2+) with wave-heavy history searches its needed column first.

## 3. Wire into the UI page

- [ ] 3.1 In `SetTargetABCACD.php`, call `pl_abc_acd_session_wave_tally($tourId, $sesOrder, $event)` before `pl_abc_acd_assign(...)` and pass the result through.
- [ ] 3.2 Manual check: assign two classes in the same session with a shared largest club, confirm the second class's preview shows that club on the opposite column from the first.
- [ ] 3.3 Manual check: same two classes assigned in different sessions show no cross-influence.

## 4. Docs

- [ ] 4.1 Run `tools/test.cmd` (or `tools/test.sh`) and confirm the full suite passes.
- [ ] 4.2 Ready for `/opsx:archive-change` once manual checks in section 3 are confirmed.
