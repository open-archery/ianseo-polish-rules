## Why

PZŁucz standard team competition requires operators to manually declare rosters (3 or 4 athletes per team) before qualification, but ianseo's built-in team machinery only supports automatic grouping by country code. A 4-athlete team must also be ranked by the sum of its **best 3** qualification scores — a rule not present in the ianseo core.

## What Changes

- New manual team composition UI (`Teams/` directory) allowing operators to create, edit, and delete qualification team rosters for any division/class event.
- New PL-owned backup table (`PLTeamDeclaration`) as a persistent source of truth, protecting manually declared rosters from accidental overwrite by ianseo's core `MakeTeams` mechanism.
- "Restore rosters" action that re-syncs `PLTeamDeclaration` → `Teams` + `TeamComponent` after any accidental core overwrite.
- New `Rank/Obj_Rank_DivClassTeam_calc.php` PL override that recalculates team qualification scores using best-3-of-4 logic and re-ranks automatically after each roster save.
- `EvMaxTeamPerson` updated to `4` in Setup_1_PL, Setup_3_PL, and Setup_6_PL so that finals substitution supports 4-person rosters.
- New menu entry "Składy drużyn" under the QUAL category.

## Capabilities

### New Capabilities

- `manual-teams`: Manual operator entry of standard team rosters (3 or 4 athletes per club per event), with multi-team-per-club support, pre-qualification roster entry, automatic best-3-of-4 score recalculation, and a backup/restore mechanism against core team regeneration.

### Modified Capabilities

- `tournament-setup`: `EvMaxTeamPerson` must be set to `4` for team events in Setup_1_PL, Setup_3_PL, and Setup_6_PL.

## Non-goals

- Mixed team composition (separate feature).
- Modifying ianseo core files (`Qualification/MakeTeams.php`, `Final/Team/` pages).
- Preventing the core "Make Teams" button from running — mitigation is backup/restore only.
- Field archery and 3D team formats.
- Finals per-end substitution UI (already handled by ianseo core `ChangeComponents.php`).

## Impact

- **New files:** `PL/Teams/ManualTeams.php`, `PL/Teams/ManualTeams-data.php`, `PL/Rank/Obj_Rank_DivClassTeam_calc.php`
- **Modified files:** `PL/menu.php`, `PL/Setup_1_PL.php`, `PL/Setup_3_PL.php`, `PL/Setup_6_PL.php`
- **New DB table:** `PLTeamDeclaration` (auto-installed on first use)
- **ianseo tables written:** `Teams`, `TeamComponent` (standard tables, no schema change)
- **Regulation reference:** §2.3.1.2.3, §2.3.1.2.5, §2.3.2.2.3, §2.3.2.2.5 (team size and substitution); organizer rule for best-3-of-4 scoring
