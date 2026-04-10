# Post-Elimination Ranking Update — Architecture

## Context

The existing PL ranking overrides in `Rank/Obj_Rank_FinalInd_calc.php` and
`Rank/Obj_Rank_FinalTeam_calc.php` implement the old §2.6.5 tiebreaking
logic: sort same-round losers by raw match score (set-point total for R/B,
cumulative total for C), then by qualification rank. They also carry dead
pool-phase code (`EvElimType` 3/4 branch) inherited from the Norway set.

ianseo rev 114 updated the core `calcFromPhase()` to compute and store
average arrow values (`FinAverageMatch`, `FinAverageTie`) for every match.
This aligns with the updated WA rules — and with the new PZŁucz §2.6.6.2.
Since the PL overrides replace the core method entirely, they currently
neither compute these averages nor write them to the database.

**ianseo hook point:** `Obj_RankFactory::create()` resolves rank calculator
classes by searching locale-specific paths before the core fallback. Files
in `Modules/Sets/PL/Rank/` named `Obj_Rank_Final{Ind,Team}_calc.php` are
automatically loaded in place of the core calculators when the tournament
locale is `PL`. No registration step is needed.

## Goals / Non-Goals

**Goals:**
- Replace the old tiebreaker sort (raw match score → qual rank) with the
  §2.6.6.2 criteria (avg match arrow value → avg shoot-off arrow value →
  qual score) in both individual and team overrides
- Write `FinAverageMatch`/`FinAverageTie` (and team equivalents) to the
  Finals tables for all phases, matching ianseo rev 114 core behaviour
- Remove dead `EvElimType` 3/4 pool-phase block from both overrides

**Non-Goals:**
- Unique sequential rank placement — already implemented, unchanged
- No-bronze-match detection — already implemented, unchanged
- Field/3D elimination brackets — out of scope
- No new DB tables, menu entries, or config UI

## Decisions

### Decision 1 — PHP-side sorting instead of SQL ORDER BY

**Choice:** Switch the phases-≥-4 processing loop from SQL `ORDER BY` to
PHP-side sorting using a composite key array.

**Why:** The average arrow value cannot be computed as a simple SQL
expression because the arrow count is derived from `FinArrowstring`
(variable-length string) or `FinSetPoints` (set separator counting via
`preg_replace`). Both require PHP string functions. The ianseo rev 114 core
already uses the same PHP pattern for this reason.

**Alternative considered:** Computing the average in SQL with nested
`LENGTH(TRIM(...))` / `REGEXP_REPLACE(...)` expressions. MySQL 5.7 lacks
`REGEXP_REPLACE`, and the fallback chain would be fragile. PHP is cleaner.

### Decision 2 — Reuse the exact core average-computation formula

**Choice:** Copy the average formula verbatim from the rev 114 core:

```php
// avgMatch: score divided by arrows shot
$arrows = strlen(trim($Arrowstring))
    ?: (strlen(preg_replace("/\d/", "", $SetPoints))
        ? (strlen(preg_replace("/\d/", "", $SetPoints)) + 1) * $DiEndArrows
        : ($DiArrows ?: 1));
$avgMatch = round($Score / $arrows, 3);

// avgTie: shoot-off total divided by shoot-off arrows (0 if none)
$avgTie = round(valutaArrowString($Tiebreak) / (strlen(trim($Tiebreak)) ?: 1), 3);
```

**Why:** This formula is the WA-defined standard and is already used by the
rest of ianseo. Using a different formula would produce results inconsistent
with the rest of the system and make future ianseo upgrades harder to track.

**Arrow count fallback chain:**
1. `FinArrowstring` populated → `strlen(trim(...))` counts individual arrow values
2. `FinSetPoints` has set separators (e.g. `"6-6-2"`) → count separators + 1, multiply by `DiEndArrows`
3. Configured `DiArrows` (arrows per full match) → use directly
4. Hard fallback: `1` (prevents division by zero)

### Decision 3 — Composite sort key via `usort()` for three-level ordering

**Choice:** Store per-match data as a structured array and sort with
`usort()` using an explicit three-level comparator: `avgMatch DESC` →
`avgTie DESC` → `qualScore DESC`.

**Why:** The core uses `arsort()` on a scalar key
(`avg[0]*1000 + avg[1]/100`). A scalar key works for two criteria but
encoding three criteria (with `qualScore` values up to ~3600 for teams)
into a reliable float is fragile. `usort()` with a closure is explicit and
correct.

**Alternative considered:** `arsort()` with
`avg[0]*100000000 + avg[1]*100000 + qualScore`. This works for known score
ranges but is brittle if score maximums change. `usort()` is safer and
equally fast for the small arrays involved (≤ 104 rows).

### Decision 4 — Qualification score field: `IndScore` / `TeScore`

**Choice:** Use `i.IndScore AS QualScore` (joined from `Individuals`) for
individual events, and `te.TeScore AS QualScore` (joined from `Teams`) for
team events.

**Why:** `IndScore` is the aggregated qualification total already written
to `Individuals` by the qualification ranking step. It is directly
accessible via the existing `Individuals AS i` JOIN already in the query.
`TeScore` is the team equivalent in `Teams`. No additional JOIN to the
`Qualifications` table is needed.

**Note:** The core uses `IF((EvLockResults OR EvQualBestOfDistances),
IndScore, QuScore)` in some ranking contexts. For this ranking step,
`IndScore` is always the correct field — by the time elimination runs,
qualification scores are locked in `Individuals`.

### Decision 5 — Write `FinAverageMatch`/`FinAverageTie` for all phases

**Choice:** Write the computed averages to `Finals` / `TeamFinals` for all
phases (0/1/2 and ≥4), not just the phases ≥4 sort loop.

**Why:** The ianseo core writes these values for every phase. Since our
override replaces the core method entirely, if we only write them in the
≥4 branch, gold/bronze/semifinal participants will have null averages.
ianseo's results display pages read these columns for display; null values
would cause missing or incorrect UI output after a rev 114 upgrade.

### Decision 6 — Remove pool-phase override block

**Choice:** Remove the `EvElimType == 3 || EvElimType == 4` block entirely.

**Why:** PL events set `EvElimType` implicitly to `0` (the global default
in `Modules/Sets/lib.php`). The condition can never be true. The block was
inherited from Norway's implementation without adaptation. Removing it
eliminates dead code and simplifies the new PHP sort loop.

## Files to Modify

| File | Change |
| ---- | ------ |
| `Rank/Obj_Rank_FinalInd_calc.php` | Update `calcFromPhase()` as described |
| `Rank/Obj_Rank_FinalTeam_calc.php` | Update `calcFromPhase()` as described |

No files to create. No files to delete. No other files affected.

## `calcFromPhase()` Changes — Individual

### SELECT query additions

Add to the individual losers query:

```sql
-- Arrow count helpers (already in core; add to PL query)
IF((EvMatchArrowsNo & GrBitPhase)=0, EvFinArrows, EvElimArrows) AS DiEndArrows,
IF((EvMatchArrowsNo & GrBitPhase)=0, EvFinArrows*EvFinEnds, EvElimArrows*EvElimEnds) AS DiArrows,

-- Arrow content for count derivation
f.FinArrowstring AS Arrowstring,
f.FinSetPoints AS SetPoints,
f.FinTiebreak AS Tiebreak,

-- Qualification score (3rd tiebreaker)
i.IndScore AS QualScore,

-- Match numbers for writing averages back
f.FinMatchNo AS RealMatchNo,
f2.FinMatchNo AS OppRealMatchNo
```

Remove from the ORDER BY (sorting moves to PHP):
```sql
-- Remove:
ORDER BY IF(EvMatchMode=0, f.FinScore, f.FinSetScore) DESC, i.IndRank ASC
-- Replace with:
ORDER BY least(f.FinMatchNo, f2.FinMatchNo)  -- stable ordering only
```

### Phase 0/1 additions

After the `$toWrite` loop, compute and write averages (same as core):

```php
$avgMatch = round($myRow->Score / (strlen(trim($myRow->Arrowstring))
    ?: (strlen(preg_replace("/\d/","", $myRow->SetPoints))
        ? (strlen(preg_replace("/\d/","", $myRow->SetPoints))+1) * $myRow->DiEndArrows
        : ($myRow->DiArrows ?: 1))), 3);
$avgTie = round(valutaArrowString($myRow->Tiebreak)
    / (strlen(trim($myRow->Tiebreak)) ?: 1), 3);

safe_w_sql("UPDATE Finals SET FinAverageMatch='{$avgMatch}', FinAverageTie='{$avgTie}'
    WHERE FinTournament='{$this->tournament}' AND FinEvent='{$EventToUse}'
    AND FinMatchNo='{$myRow->RealMatchNo}'");
// same for opponent using OppArrowstring, OppSetPoints, OppTiebreak, OppRealMatchNo
```

### Phase 2 / SubCodes additions

Same average writes inside the `while ($myRow)` loop, no rank changes.

### Phases ≥ 4 — replace the else branch

```php
// 1. Build $matchData array
$matchData = [];
while ($myRow) {
    $arrows = strlen(trim($myRow->Arrowstring))
        ?: (strlen(preg_replace("/\d/", "", $myRow->SetPoints))
            ? (strlen(preg_replace("/\d/", "", $myRow->SetPoints))+1) * $myRow->DiEndArrows
            : ($myRow->DiArrows ?: 1));
    $avgMatch = round($myRow->Score / $arrows, 3);
    $avgTie   = round(valutaArrowString($myRow->Tiebreak)
                    / (strlen(trim($myRow->Tiebreak)) ?: 1), 3);

    // Write averages to Finals (same as core)
    safe_w_sql("UPDATE Finals SET FinAverageMatch=..., FinAverageTie=...
        WHERE ... AND FinMatchNo='{$myRow->RealMatchNo}'");
    // same for opponent

    $matchData[] = [
        'id'        => $myRow->AthId,
        'avgMatch'  => $avgMatch,
        'avgTie'    => $avgTie,
        'qualScore' => (int) $myRow->QualScore,
        'winnerRank'=> $myRow->EvWinnerFinalRank,
        'matchNo'   => $myRow->MatchNo,
    ];
    $myRow = safe_fetch($rs);
}

// 2. Sort: avgMatch DESC, avgTie DESC, qualScore DESC
usort($matchData, function($a, $b) {
    if ($a['avgMatch'] != $b['avgMatch']) return $b['avgMatch'] <=> $a['avgMatch'];
    if ($a['avgTie']   != $b['avgTie'])   return $b['avgTie']   <=> $a['avgTie'];
    return $b['qualScore'] <=> $a['qualScore'];
});

// 3. Assign unique sequential ranks
$rank     = $pos + 1;
$prev     = null;

foreach ($matchData as $m) {
    ++$pos;
    if ($prev === null
        || $m['avgMatch'] != $prev['avgMatch']
        || $m['avgTie']   != $prev['avgTie']
        || $m['qualScore']!= $prev['qualScore'])
    {
        $rank = $pos;
    }
    $this->writeRow($m['id'], $event, $rank + $m['winnerRank'] - 1);
    $prev = $m;
}
```

`$pos` starting value is unchanged: `4` for `$realphase == 4` (QF),
`numMatchesByPhase($phase) + SavedInPhase($phase)` for deeper phases.

## `calcFromPhase()` Changes — Team

Identical pattern using:

| Individual field | Team equivalent |
| ---------------- | --------------- |
| `Finals` | `TeamFinals` |
| `f.FinScore` | `tf.TfScore` |
| `f.FinArrowstring` | `tf.TfArrowstring` |
| `f.FinSetPoints` | `tf.TfSetPoints` |
| `f.FinTiebreak` | `tf.TfTiebreak` |
| `FinAverageMatch` | `TfAverageMatch` |
| `FinAverageTie` | `TfAverageTie` |
| `i.IndScore AS QualScore` | `te.TeScore AS QualScore` |
| `$myRow->AthId` | `$myRow->TeamId` + `$myRow->SubTeam` |

The existing `Teams AS te` JOIN already in the team query provides
`te.TeScore`.

## Risks / Trade-offs

**`FinArrowstring` not populated** → The fallback chain covers this: if
`FinArrowstring` is empty, the formula falls back to set-point separator
counting, then to `DiArrows`. For PL outdoor (set system) and indoor
(cumulative) formats, `DiArrows` is always set via `EvElimArrows *
EvElimEnds`. Worst case: all athletes in a round have `avgMatch = 0` and
are sub-ranked purely by qual score — correct behaviour, just based on fewer
data points.

**Float comparison in `usort`** → `avgMatch` and `avgTie` are both
`round(..., 3)`, so comparison is on values with at most 3 decimal places.
Direct `!=` comparison of PHP floats at 3dp precision is safe for values
in the 0–10 range.

**`IndScore` = 0 for DNS/withdrawn** → Athletes with `IndIrmTypeFinal >= 15`
are skipped by `writeRow()` (unchanged behaviour), so zero scores for
absent athletes do not pollute the ranking.

## Migration Plan

Drop-in replacement: the two PHP files are loaded dynamically by
`Obj_RankFactory`. Deploying updated files takes effect immediately for any
tournament whose ranking is recalculated. No DB migration needed — `Finals`
and `TeamFinals` already have `FinAverageMatch`/`FinAverageTie` /
`TfAverageMatch`/`TfAverageTie` columns (added by ianseo rev 114).

**Rollback:** Restore the previous version of the two files from git.

## Open Questions

_(none — all decisions resolved during exploration)_
