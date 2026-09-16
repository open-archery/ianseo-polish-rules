## Context

See `proposal.md` - Why/What Changes for motivation. Current shape of `Targets/Fun_SetTargetABCACD.php`:

- `pl_abc_acd_load_athletes($tourId, $sesOrder, $event)` runs one query filtered by the `Event` `LIKE` pattern, groups rows by `EnCountry` only, and returns `club_code => [athlete, ...]` sorted largest-club-first.
- `pl_abc_acd_build_slots($from, $to)` turns a boss range into the ABC/ACD letter-slot list. Every boss (odd or even) yields exactly 3 usable letters (`SesAth4Target=4` is enforced elsewhere, so one letter is always the empty one) — this fixed "3 slots/boss" invariant is what makes boss-aligned group sizing computable.
- `pl_abc_acd_assign($clubs, $slots, $waveTally = [])` runs the club-column algorithm over one flat club map and one flat slot list.
- `pl_abc_acd_session_wave_tally($tourId, $sesOrder, $excludeEvent)` reads only committed (`QuTarget!=0`) rows, excluding rows matching `$excludeEvent` exactly.
- `SetTargetABCACD.php` wires these together for exactly one `(clubs, slots)` pair per request.

## Goals / Non-Goals

**Goals:**
- Split the athletes matched by `Event` into groups by division/class (per the two new checkboxes) before running the existing per-group algorithm, unchanged, on each group.
- Carve `TgtFrom`–`TgtTo` into boss-aligned per-group sub-ranges, ordered by `DivViewOrder`/`ClViewOrder`.
- Thread the wave-balance tally across groups processed in the same request, in addition to the existing saved-history source.
- Preserve exact current behavior when both checkboxes are unchecked (single pooled group).

**Non-Goals:**
- Reproducing native's zigzag/Field3D/Oris seeding modes, wheelchair/double-space handling, or its generic "any archer count per boss" logic — this tool keeps the ABC/ACD-specific, always-4-archers-per-boss, club-column algorithm.
- Changing what `Event` itself matches (still one `LIKE` pattern) — grouping happens after the SQL filter, in PHP.
- A UI for reordering groups manually — order is always `DivViewOrder`/`ClViewOrder`, matching native.

## Decisions

### Grouping happens by adding a group key in `pl_abc_acd_load_athletes`, not by calling it once per class
`pl_abc_acd_load_athletes` gains two boolean parameters (`$groupByDiv`, `$groupByClass`). Its query additionally selects `EnDivision`, `EnClass`, and joins `Divisions`/`Classes` for `DivViewOrder`/`ClViewOrder` (already joined for `DivAthlete=1`/`ClAthlete=1`; the view-order columns just need to be selected, no new join). Grouping in PHP becomes two-level: build a group key from whichever of `EnDivision`/`EnClass` are active (or a constant key when neither is), then within each group bucket rows by `EnCountry` exactly as today. Groups are emitted ordered by `(DivViewOrder, ClViewOrder)` ascending, taken from the first row seen in each group (all rows in a group share the same division/class by construction).

**Alternative considered:** call the existing single-group function once per distinct division/class value found by a separate lookup query, looping in `SetTargetABCACD.php`. Rejected — doubles the query count for no benefit, and makes ordering by view-order awkward (would need a second query anyway to get view orders before deciding loop order).

### Boss-aligned carving is a new pure function operating on boss counts, not slots
`pl_abc_acd_carve_group_ranges(int $tgtFrom, int $tgtTo, array $groupSizes): array` takes the overall boss range and an ordered list of group sizes (athlete counts), and returns an ordered list of `[from, to]` boss sub-ranges, using `ceil($size / 3)` bosses per group (3 = the fixed usable-letters-per-boss invariant), walking forward from `$tgtFrom` and never overlapping. A group that would extend past `$tgtTo` gets a truncated range (however many whole bosses remain, possibly none) rather than a sentinel value — an empty range is represented as `[$cursor, $cursor - 1]` (`$to < $from`), so partial overflow is still reported via the existing unassigned-athlete path, matching how a single oversized group overflows today.

`SetTargetABCACD.php` calls this once with all group sizes, then calls today's `pl_abc_acd_build_slots()` per group on its assigned sub-range — no change to `pl_abc_acd_build_slots` or `pl_abc_acd_assign` itself.

**Alternative considered:** size groups by counting only the *columns* they'll actually use (e.g. a 2-club group only needs 2 columns' worth of bosses, so could pack tighter than `ceil(n/3)`). Rejected as unnecessary complexity — `pl_abc_acd_assign`'s own column-fitting logic already handles sub-boss packing within a group's carved range; over-provisioning by a boss or two at a group boundary is the acceptable cost of the boss-alignment guarantee, not a bug to optimize away.

### Wave tally threading via an in-memory fold, not a second DB read
`SetTargetABCACD.php`'s loop becomes: compute `$runningTally = pl_abc_acd_session_wave_tally($tourId, $sesOrder, $event)` once, `$event` still the whole filter pattern. `pl_abc_acd_session_wave_tally` now excludes via `NOT LIKE $event` (not exact `!=`) so every class matching the pattern — not just a literal string equal to it — is excluded from its own saved history; a wildcard `$event` like `RU18%` correctly excludes both `RU18M` and `RU18W` rows saved by an earlier request, instead of excluding nothing (see spec's cross-class wave balance requirement). Then for each group in order: call `pl_abc_acd_assign($groupClubs, $groupSlots, $runningTally)`, and fold the resulting `$assignments` into `$runningTally` via a new small pure function `pl_abc_acd_merge_tally(array $tally, array $assignments): array` (counts `QuLetter` `A`/`B` vs `C`/`D` per club from the assignment map, same shape as the DB-read tally, so both sources merge with plain array addition). The merged result feeds the next group.

**Alternative considered:** re-run `pl_abc_acd_session_wave_tally` against a temp table or session-stashed preview state after each group. Rejected — no new DB writes needed; an in-memory fold is simpler, cheaper, and keeps "unsaved preview never leaks to a *different* request" trivially true (the running tally only lives for the duration of one request).

### Erase/save stay unchanged
`pl_abc_acd_erase`/`pl_abc_acd_save` still take one `$event` pattern and one flat `$assignments` map. `SetTargetABCACD.php` merges every group's `$assignments` into one flat map (keys are already globally-unique slot strings across groups, since groups never share a boss) before calling `pl_abc_acd_save` once — identical call shape to today, just built from more than one group's output.

## Risks / Trade-offs

- **Boss over-provisioning at group boundaries** (see carving decision) → small, bounded waste (at most 2 unused letters per group boundary); accepted for the correctness guarantee of never mixing two groups on one boss.
- **Query cost**: one extra pair of joined columns (view order) and a bit more PHP-side bucketing; no new queries. Negligible.
- **Interacts with the already-shipped `abc-acd-session-wave-balance` feature's exact function signatures** — `pl_abc_acd_session_wave_tally` and `pl_abc_acd_assign`'s `$waveTally` parameter are reused as-is; only a new merge helper and the call site's loop change. Existing unit tests for those two functions should keep passing unmodified.

## Migration Plan

No data migration. Purely additive UI + PHP logic; existing saved `Qualifications`/`Entries` rows are unaffected until an organiser re-runs the tool. Rollback is a plain revert (no schema change).
