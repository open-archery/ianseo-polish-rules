## Why

Organisers running `SetTargetABCACD.php` class-by-class have no at-a-glance view of a session's current state: which bosses are already taken, by which division/class, and which are still free. Today the only feedback is the per-run preview/save table for the class just assigned — there is no persistent view of the whole session, so organisers must infer gaps by memory or by re-running the tool with different ranges.

## What Changes

- Add a "field of play"-style grid to `SetTargetABCACD.php` that appears whenever a session is selected — independent of the `Event`/`TgtFrom`/`TgtTo`/`DoAssign` fields, so it reflects the session's saved state regardless of what the organiser is about to do next.
- The grid's target columns come from the session's real capacity — `Session.SesFirstTarget` / `SesTar4Session` (the same source ianseo core uses in `createAvailableTargetSQL()`) — not the free-text range the organiser happens to type.
- One `<td>` per boss number (e.g. `1 | 2 | 3 | … | 40`), with a second row underneath using `colspan` to merge consecutive bosses that share the same occupying division+class into one colored block. Colors come from a palette keyed by division+class (separate from the existing per-club palette used in the assignment preview).
- Free bosses render as their own labeled/colored block (e.g. "wolne").
- A boss whose letters are split across more than one division/class (partial/mixed occupancy — leftover data, or a boss half-assigned) renders as its own single-boss cell, labeled with the combined classes (e.g. "RU15+RU18") and given a visually distinct (striped) background so it stands out from normal blocks.

## Capabilities

### New Capabilities

- `abc-acd-session-target-view`: read-only session-wide target occupancy grid — which division/class currently occupies each boss in a session, and which bosses are free.

### Modified Capabilities

_(none)_

## Impact

- Modified files: `Modules/Sets/PL/Targets/SetTargetABCACD.php` (new grid section), possibly a new `Modules/Sets/PL/Targets/Fun_SetTargetABCACD.php` helper for computing the boss→label map (or a small dedicated file if the developer agent judges the grouping logic large enough to warrant one), plus corresponding tests.
- No new DB tables/columns; reads existing `Session`/`Qualifications`/`Entries`/`Divisions`/`Classes`.
- Spec produced by: Advisor agent → `openspec/specs/abc-acd-session-target-view/spec.md`. Design produced by: Developer agent → this change's `design.md`.

## Non-goals

- No live/auto-refreshing view — renders from DB state on each page load/postback like the rest of the page, no AJAX polling.
- No per-club detail in the grid — that stays in the existing per-run preview table; this view answers "which class owns this boss", not "which club".
- No PZŁucz regulation clause directly governs this — it is an organiser-ergonomics addition, not a rules requirement.
