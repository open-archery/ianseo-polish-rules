## Why

PZŁucz regulation §2.5.1.5 requires that when archers shoot in two groups ("kolejki"), same-club athletes should be spread across both groups rather than concentrated in one, if possible. The ABC/ACD tool (`Targets/Fun_SetTargetABCACD.php`) already produces two shooting waves per boss (wave1 = letters A/B, wave2 = letters C/D), but always gives the largest club in a class column A (wave1) and the second-largest column C (wave2), with no memory of other classes already assigned in the same session. A club that is the largest in several classes shot in the same `QuSession` (e.g. men's and women's Recurve run separately but at the same time) ends up wave1 in every one of those classes, so all its athletes across categories shoot simultaneously — the cross-class analogue of the violation §2.5.1.5 warns against.

## What Changes

- `pl_abc_acd_assign()` gains a per-club wave bias: before choosing which column a club's block starts in, check that club's existing wave1 (A/B) vs wave2 (C/D) tally from other classes already assigned in the *same* `QuSession`, and prefer the column that reduces the imbalance.
- New read-only query (`pl_abc_acd_session_wave_tally()`) reads already-saved `QuTarget`/`QuLetter` for the same tournament+session, excluding the class currently being assigned, grouped by club (`EnCountry`).
- Behavior is unchanged when there is no prior history for a club in that session (first class assigned in a session, or a club with no matching entries elsewhere in it) — falls back to today's largest-club-gets-A default.
- Behavior is unchanged for a single class run in isolation: a club can still occupy one column entirely within that run (§2.5.1.5 does not require splitting within one simultaneous group).
- Different sessions never influence each other — no cross-session interaction, since athletes in different sessions never shoot at the same time.

## Capabilities

### New Capabilities

_(none)_

### Modified Capabilities

- `abc-acd-target-assignment`: club-column assignment now considers cross-class wave history within the same session (see §2.5.1.5) instead of always defaulting largest club → A, second-largest → C.

## Non-goals

- Splitting a single club's block across A/C within one class run — confirmed acceptable for a club to occupy one column entirely in isolation.
- Balancing across different `QuSession` values — sessions run at different times, so simultaneous-shooting concerns (§2.5.1.5) don't apply across them.
- Perfect balance guarantees — this is a best-effort bias ("jeśli to możliwe" per §2.5.1.5), not a hard constraint; small clubs, odd counts, or heavily skewed class sizes may still end up imbalanced.
- Changing the erase/save scope, which stays per-class as today.

## Impact

- **Modified files:** `Modules/Sets/PL/Targets/Fun_SetTargetABCACD.php` (new query function, updated column-priority logic in `pl_abc_acd_assign`), `Modules/Sets/PL/Targets/SetTargetABCACDTest.php` (new coverage).
- **No new DB tables/columns** — reads existing `Qualifications.QuTarget`/`QuLetter` joined to `Entries.EnCountry`.
- **Spec produced by:** Advisor agent → `openspec/specs/abc-acd-target-assignment/spec.md` (delta).
- **Design produced by:** Developer agent → `openspec/changes/abc-acd-session-wave-balance/design.md`.
