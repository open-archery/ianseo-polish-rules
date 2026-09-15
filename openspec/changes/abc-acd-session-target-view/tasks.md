## 1. Core data query

- [ ] 1.1 Implement a helper that joins core's `createAvailableTargetSQL($sesOrder)` output to `Qualifications`/`Entries`/`Divisions`/`Classes` to get, per occupied slot, its `CONCAT(EnDivision,EnClass)` label; unit test (via `FakeDb`) that stubbed rows produce the expected per-slot label map.
- [ ] 1.2 Unit test: a session with zero assigned rows returns every boss labeled free.

## 2. Boss-level aggregation

- [ ] 2.1 Implement per-boss aggregation from per-slot labels: zero distinct labels → free, one distinct label → that label, more than one → mixed with a combined label string; unit test each branch.
- [ ] 2.2 Unit test: a boss with only some letters filled by a single class (others still empty) is labeled by that class, not mixed.

## 3. Range/colspan encoding

- [ ] 3.1 Implement a run-length encoding pass that merges consecutive bosses sharing an identical label into one colspan group; unit test against a multi-class, multi-free-range session.
- [ ] 3.2 Unit test: mixed-boss cells never merge with a neighboring cell, even when a neighbor computes an identical combined label string.

## 4. Rendering

- [ ] 4.1 Add the grid section to `SetTargetABCACD.php`, rendered whenever a session is selected, using the session's real boss/letter capacity rather than the typed `TgtFrom`/`TgtTo`.
- [ ] 4.2 Implement a label color palette (a fixed hex array distinct from the existing per-club `$palette`), assigning colors to labels in first-seen order within the render.
- [ ] 4.3 Style the free block with one fixed neutral color and mixed cells with a striped/hatched CSS background, both distinct from the rotating label palette.
- [ ] 4.4 Manual check: a session with several saved classes and free trailing bosses renders a correctly colored, correctly labeled, correctly colspan'd grid.
- [ ] 4.5 Manual check: the grid appears immediately on session selection, before `Event`/`TgtFrom`/`TgtTo` are filled in or the form is submitted.

## 5. Docs

- [ ] 5.1 Run `tools/test.cmd` (or `tools/test.sh`) and confirm the full suite passes.
- [ ] 5.2 Ready for `/opsx:archive-change` once manual checks in section 4 are confirmed.
