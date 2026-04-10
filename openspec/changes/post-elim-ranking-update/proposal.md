## Why

PZŁucz regulations §2.6.5–§2.6.6 were updated to unify with WA rules. The
tiebreaking criteria for placing same-round losers after elimination now use
**average arrow value** (score ÷ arrows shot) instead of raw match score, add
a new **shoot-off average** criterion, and replace qualification rank with
**qualification score** as the third tiebreaker. The existing implementation
uses the old criteria and must be updated to match.

## What Changes

- **Update individual ranking override** (`Rank/Obj_Rank_FinalInd_calc.php`):
  replace old tiebreaker logic (raw match score → qual rank) with the new
  three-level criteria (avg match arrow value → avg shoot-off arrow value →
  qual score); add `FinAverageMatch`/`FinAverageTie` writes to `Finals` for
  all phases, matching ianseo rev 114 core behaviour
- **Update team ranking override** (`Rank/Obj_Rank_FinalTeam_calc.php`):
  same tiebreaker update using `TeamFinals` fields and `TeScore` for qual
  score; add `TfAverageMatch`/`TfAverageTie` writes to `TeamFinals`
- **Remove dead pool-phase code** from both overrides: the `EvElimType 3/4`
  branch carried over from Norway never triggers in PL events
- **Update spec** (`openspec/specs/post-elim-ranking/spec.md`): already done
  prior to this change

## Capabilities

### New Capabilities

_(none — this is a rule update, not a new capability)_

### Modified Capabilities

- `post-elim-ranking`: Tiebreaking criteria for same-round losers changed
  from (match score → qual rank) to (avg match arrow value → avg shoot-off
  arrow value → qual score) per updated §2.6.6.2

## Non-goals

- No changes to unique sequential rank placement logic (still required, still
  custom — ianseo native does not do this)
- No changes to no-bronze-match detection (unchanged behaviour)
- No changes to position ranges (§2.6.6.1 unchanged)
- No changes to Field/3D elimination (out of scope)
- No new DB tables, menu entries, or setup script changes

## Impact

- `Rank/Obj_Rank_FinalInd_calc.php` — `calcFromPhase()` modified
- `Rank/Obj_Rank_FinalTeam_calc.php` — `calcFromPhase()` modified
- Spec already updated; design.md will be rewritten (Developer role)
- No ianseo core files touched
