## Why

`pl_standard_class_candidates()` (`lib.php`) still carries two leftovers from before Masters became its own opt-in preset. Senior M/W is capped at `ageTo=49`, so any tournament without the Masters preset selected has zero `Classes` rows covering age 50+ — `pl_resolve_age_class()` (`Import/Fun_BibImport.php`) fails to auto-assign a class for any archer 50 or older. Separately, the `ClValidClass` "compete up" chains let U12/U15/U18 archers reach all the way up to Senior M/W, when per PZŁucz rules (§1.2.1: Seniorzy/Seniorki are open-age with no upper bound) and current domain practice only U21 and U24 archers may compete up into Senior.

## What Changes

- Raise Senior M/W `ClAgeTo` from 49 to unbounded (100, matching the convention already used by Masters' own 70+/80+ bands), so age alone never excludes an archer from Senior when no other class fits.
- Narrow `ClValidClass` upward-eligibility chains in `pl_standard_class_candidates()`:
  - U12M/W: self-only (`U12M` / `U12W`) — was chained up through U15/U18/U21/Senior.
  - U15M/W: self-only (`U15M` / `U15W`) — was chained up through U18/U21/Senior.
  - U18M/W: up to U21 only (`U18M,U21M` / `U18W,U21W`) — was chained further up into Senior.
  - U21M/W and U24M/W: unchanged (`U21M,M` / `U24M,M`, etc.) — these remain the only classes eligible to compete up into Senior.
  - Masters bands and PU12: unchanged (already self-only "parallel track" chains).

## Non-goals

- No changes to the preset table, sub-rule registration, or any UI (`sets.php`, `menu.php`, Setup screens).
- No retrofit of existing tournaments' already-created `Classes` rows — this only affects tournaments created after the fix.
- No change to Masters/PU12 chain shape — both already match the intended pattern.
- Does not touch TourType 16 (fixed U12-only roster, already self-only, out of scope per `pl_standard_class_candidates()`'s existing early-return).

## Capabilities

### Modified Capabilities

- `tournament-setup`: Senior M/W age range and the U12/U15/U18/U21/U24 `ClValidClass` upward-eligibility chains change.

## Impact

- `Modules/Sets/PL/lib.php`: `pl_standard_class_candidates()` — the single source of truth `CreateStandardClasses()` and `InsertStandardEvents()` both consume, plus every `Setup_*_PL.php`'s per-class loops.
- `Modules/Sets/PL/Import/Fun_BibImport.php`: no code change, but `pl_resolve_age_class()`'s auto-assignment behavior changes (50+ archers now resolve to Senior M/W when no Masters classes exist in the tournament).
- Advisor role should confirm the U18→U21 partial chain against `regulamin-lucznictwa.md` if broader validation is wanted; the specific chain shapes here were confirmed directly by the domain owner in conversation.
