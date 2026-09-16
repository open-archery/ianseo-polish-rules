## Why

`Targets/SetTargetABCACD.php` accepts a single `Event` filter (a `LIKE` pattern). When that pattern matches more than one division/class — e.g. a wildcard, or classes sharing a prefix — every matching athlete is pooled into one club-ranked list and the ABC/ACD algorithm runs once over all of them, so different classes can end up sharing a boss. Native ianseo's own auto target assignment (`Partecipants/SetTarget_auto.php`) solves the equivalent problem with two checkboxes, "Rozdziel dywizje" / "Rozdziel klasy" (`GroupByDiv`/`GroupByClass`), that split entries into per-division/per-class groups before seeding targets. The PL ABC/ACD tool has no such separation today.

## What Changes

- Add two checkboxes to `SetTargetABCACD.php`, mirroring native's naming/intent: "Rozdziel dywizje" and "Rozdziel klasy", both **checked by default**.
- When checked, athletes matching `Event` are split into groups by division and/or class (ordered by `DivViewOrder`/`ClViewOrder`, matching native's group ordering), and each group is assigned its own contiguous, **boss-aligned** sub-range of `TgtFrom`–`TgtTo` — a boss is never split between two groups. Leftover letters on a boundary boss go unused and are reported the same way overflow athletes are today.
- When both are unchecked, behavior is unchanged from today: one pooled group across everything the filter matches.
- `pl_abc_acd_session_wave_tally()`'s bias (§2.5.1.5, same-club cross-class balance) is extended so that within one multi-group run, each group's just-computed assignment feeds the running tally seen by the *next* group in that same run — not just previously-saved history. This modifies the existing "Unsaved preview does not affect the tally" scenario: that guarantee now holds only *across* separate requests, not between groups processed together in one request.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `abc-acd-target-assignment`: "One class at a time" is superseded by optional per-division/per-class grouping; adds boss-aligned group carving; modifies the cross-class wave-balance requirement's tally-visibility scenario for same-run groups.

## Impact

- Modified files: `Targets/SetTargetABCACD.php`, `Targets/Fun_SetTargetABCACD.php`, `Targets/SetTargetABCACDTest.php`.
- No new DB tables/columns; reuses `Divisions.DivViewOrder`/`Classes.ClViewOrder` (existing core columns, not yet read by this module).
- Spec produced by: Advisor agent → `openspec/specs/abc-acd-target-assignment/spec.md` (delta). Design produced by: Developer agent → this change's `design.md`.

## Non-goals

- Changing the erase/save scope (still per matched-`Event` pattern, as today).
- Native's zigzag/field-3D/Oris seeding variants — this tool keeps its own club-column-priority algorithm, just applied per group.
- Cross-session balancing (unchanged: different `QuSession` values never interact).
