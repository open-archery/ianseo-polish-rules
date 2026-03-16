# Post-Elimination Ranking — Architecture

## Overview

Two ranking calculator overrides placed in `Modules/Sets/PL/Rank/`,
picked up automatically by `Obj_RankFactory` when the tournament locale
is `PL`. No menu entries, no new DB tables, no configuration UI.

---

## Reference Implementation

The Norway (`NO`) set already implements unique sub-ranking for all
elimination phases (`Modules/Sets/NO/Rank/Obj_Rank_FinalInd_calc.php`
and `Obj_Rank_FinalTeam_calc.php`). The PL implementation reuses the
Norway approach with two targeted modifications:

| Aspect                      | Norway (NO)                        | Poland (PL)                                    |
| --------------------------- | ---------------------------------- | ---------------------------------------------- |
| Unique ranks for phases ≥ 4 | ✓ all phases                       | ✓ all phases (same)                            |
| Secondary tiebreaker        | Cumulative score (`CumScore DESC`) | Qualification rank (`IndRank ASC`)             |
| No-bronze-match detection   | ✗ not handled                      | ✓ detect 0-0 tie → both semifinal losers = 3rd |

---

## ianseo Hook Point

`Obj_RankFactory::create($family, $opts)` searches for rank calculators
in this order:

```
1. Modules/Sets/{Locale}/Rank/Obj_Rank_{family}_{type}_{subrule}_calc.php
2. Modules/Sets/{Locale}/Rank/Obj_Rank_{family}_{type}_calc.php
3. Modules/Sets/{Locale}/Rank/Obj_Rank_{family}_calc.php          ← our files
4. Common/Rank/Obj_Rank_{family}_{type}_{subrule}_calc.php
5. Common/Rank/Obj_Rank_{family}_{type}_calc.php
6. Common/Rank/Obj_Rank_{family}_calc.php                         ← core fallback
```

The base (non-`_calc`) class is resolved first via the same sequence.
Since we do NOT provide a custom base class, the factory loads
`Common/Rank/Obj_Rank_FinalInd.php` (or `FinalTeam`), then our `_calc`
file. The core `_calc` file is **never loaded** — no class name conflict.

Each PL `_calc` class extends the core base class directly
(`Obj_Rank_FinalInd` or `Obj_Rank_FinalTeam`) and provides a complete
implementation of all methods. This is the established pattern used by
Norway and other sets.

---

## Files to Create

| #   | File                                               | Purpose                                |
| --- | -------------------------------------------------- | -------------------------------------- |
| 1   | `Modules/Sets/PL/Rank/Obj_Rank_FinalInd_calc.php`  | Individual final ranking with PL rules |
| 2   | `Modules/Sets/PL/Rank/Obj_Rank_FinalTeam_calc.php` | Team final ranking with PL rules       |

No `menu.php` changes. No new DB tables. No setup script changes.

---

## Class Design

### `Obj_Rank_FinalInd_calc extends Obj_Rank_FinalInd`

Methods (all identical to core `Common/Rank/Obj_Rank_FinalInd_calc.php`
**except** `calcFromPhase()`):

| Method            | Source       | Notes                                       |
| ----------------- | ------------ | ------------------------------------------- |
| `writeRow()`      | Core (copy)  | Writes `IndRankFinal` to `Individuals`      |
| `calcFromAbs()`   | Core (copy)  | Ranks non-qualifiers from qualification     |
| `calcFromElim1()` | Core (copy)  | Copies `ElRank` to `IndRankFinal` (phase 0) |
| `calcFromElim2()` | Core (copy)  | Copies `ElRank` to `IndRankFinal` (phase 1) |
| `calcFromPhase()` | **Modified** | PL-specific ranking logic (see below)       |
| `calculate()`     | Core (copy)  | Orchestrates per-event/per-phase calls      |

### `Obj_Rank_FinalTeam_calc extends Obj_Rank_FinalTeam`

| Method            | Source       | Notes                                        |
| ----------------- | ------------ | -------------------------------------------- |
| `writeRow()`      | Core (copy)  | Writes `TeRankFinal` to `Teams`              |
| `calcFromAbs()`   | Core (copy)  | Ranks non-qualifiers from team qualification |
| `calcFromPhase()` | **Modified** | PL-specific ranking logic (see below)        |
| `calculate()`     | Core (copy)  | Orchestrates per-event/per-phase calls       |

> Note: `calcFromElim1()`/`calcFromElim2()` exist only in individual
> ranking. The core `FinalTeam_calc` does not use them and neither does PL.

---

## Modification 1 — No-Bronze-Match Detection

### Location

`calcFromPhase()`, within the `$phase == 1` (bronze) branch.

### Current core behaviour

The core queries losers via `f2.FinWinLose = 1` (the opponent won).
If the bronze match was 0-0 (never shot), neither athlete has
`FinWinLose = 1`, so the query returns 0 rows. Both athletes keep
`IndRankFinal = 0` from the reset at the top of `calcFromPhase()`.
No rank is assigned — **incorrect**.

### PL behaviour

When the losers query returns 0 rows for `$phase == 1`:

1. Run a secondary query to check if both bronze-match athletes have
   `FinScore = 0 AND FinSetScore = 0` (indicates match was not shot).
2. If yes: assign both athletes `IndRankFinal = EvWinnerFinalRank + 2`
   (i.e. shared 3rd place when `EvWinnerFinalRank = 1`).
3. If no: no action (match may still be in progress — same as default).

**Detection query (individual):**

```sql
SELECT f.FinAthlete AS AthId
FROM Finals AS f
    INNER JOIN Grids ON f.FinMatchNo = GrMatchNo AND GrPhase = {$realphase}
    INNER JOIN Events ON f.FinEvent = EvCode
        AND f.FinTournament = EvTournament AND EvTeamEvent = 0
WHERE f.FinTournament = {$tournament}
    AND f.FinEvent = '{$event}'
    AND f.FinScore = 0 AND f.FinSetScore = 0
```

If this returns exactly 2 rows, both athletes get
`EvWinnerFinalRank + 2`.

**Team equivalent:** same pattern using `TeamFinals`/`TfScore`/`TfSetScore`,
assigning both teams `EvWinnerFinalRank + 2`.

---

## Modification 2 — Unique Sub-Ranking with Qualification Rank Tiebreaker

### Location

`calcFromPhase()`, within the `else` branch (phases ≥ 4: QF
and below).

### Current core behaviour

- Phase 4 (QF): unique positions ranked by `Score DESC, CumScore DESC`
- Phases > 4 (1/8, 1/16, …): **all losers share the same rank**

### PL behaviour

All phases ≥ 4 get unique positions, ranked by:

1. **Match score** (`Score`) descending — higher is better
2. **Qualification rank** (`IndRank` / `TeRank`) ascending — lower is better
3. Shared only if **both** are identical (practically impossible)

### Query changes (individual)

The core losers query already JOINs `Individuals AS i` and selects
`i.IndRank as AthRank`. Only the ORDER BY changes:

```sql
-- Core:
ORDER BY IF(EvMatchMode=0, f.FinScore, f.FinSetScore) DESC, f.FinScore DESC

-- PL:
ORDER BY IF(EvMatchMode=0, f.FinScore, f.FinSetScore) DESC, i.IndRank ASC
```

### Query changes (team)

The core team losers query does NOT join `Teams`. Add:

```sql
LEFT JOIN Teams AS te
    ON te.TeTournament = tf.TfTournament
    AND te.TeCoId = tf.TfTeam
    AND te.TeSubTeam = tf.TfSubTeam
    AND te.TeEvent = tf.TfEvent
    AND te.TeFinEvent = 1
```

Select `te.TeRank AS TeamQualRank` and change ORDER BY:

```sql
ORDER BY IF(EvMatchMode=0, tf.TfScore, tf.TfSetScore) DESC, te.TeRank ASC
```

### Ranking loop changes

For **all** phases ≥ 4 (not just phase 4), apply sequential positioning:

```php
// Starting position: same as core for QF, use helper functions for others
if ($realphase == 4) {
    $MaxRank = ($myRow->EvElimType == 3 or $myRow->EvElimType == 4) ? 4 : 8;
    $pos = max(4, $MaxRank - safe_num_rows($rs));
} elseif ($realphase > 4) {
    $pos = numMatchesByPhase($phase) + SavedInPhase($phase);
}

$rank = $pos + 1;
$scoreOld = 0;
$qualRankOld = -1;

while ($myRow) {
    ++$pos;
    // Unique rank unless BOTH match score AND qual rank are identical
    if (!($myRow->Score == $scoreOld && $myRow->QualRank == $qualRankOld)) {
        $rank = $pos;
    }
    $scoreOld  = $myRow->Score;
    $qualRankOld = $myRow->QualRank;

    $this->writeRow(..., $rank + $myRow->EvWinnerFinalRank - 1);
    $myRow = safe_fetch($rs);
}
```

### `EvMatchMode` reference

| `EvMatchMode` | Scoring system | `Score` column used | Divisions |
| ------------- | -------------- | ------------------- | --------- |
| `0`           | Cumulative     | `FinScore` (total)  | Compound  |
| `≠ 0`         | Set system     | `FinSetScore` (0–6) | R, B      |

The `IF(EvMatchMode=0, FinScore, FinSetScore)` pattern already in the
core handles both systems. No additional match-mode logic needed.

---

## Preserved Core Features

The following core `calcFromPhase()` features are carried over unchanged
to ensure compatibility with all bracket configurations:

- `EvWinnerFinalRank` offset (events where winner rank ≠ 1)
- `EvCodeParent` chain resolution (sub-events)
- `SubCodes` handling (combined events)
- `EvElimType` pool-phase handling (types 3 and 4)
- `IrmType` irregularity handling (DNS, DNF, DSQ)
- `namePhase()` / phase conversion for non-standard bracket sizes
  (1/24, 1/48, etc.)

---

## Verification Plan

Per requirements verification checklist:

| #   | Test case                                   | Expected result                               |
| --- | ------------------------------------------- | --------------------------------------------- |
| 1   | Bronze match played normally                | 1st, 2nd, 3rd, 4th assigned as usual          |
| 2   | Bronze match 0-0 (not shot)                 | 1st, 2nd; both semifinal losers = 3rd; no 4th |
| 3   | QF losers (4 athletes, different scores)    | Unique 5, 6, 7, 8 by match score              |
| 4   | 1/8 losers (8 athletes)                     | Unique 9–16 by match score then qual rank     |
| 5   | Set-system match (R, B): sub-rank criterion | Set-point total used as primary tiebreaker    |
| 6   | Cumulative match (C): sub-rank criterion    | Cumulative score used as primary tiebreaker   |
| 7   | Equal match scores → use qualification rank | Lower qual rank gets better (lower) place     |
| 8   | Team bracket                                | Same unique sub-ranking using TeRank          |
| 9   | Mixed-team bracket                          | Same (mixed teams use team ranking tables)    |
| 10  | 104-archer bracket (1/48 + 1/24)            | Positions 33–56 and 57–104 assigned uniquely  |
