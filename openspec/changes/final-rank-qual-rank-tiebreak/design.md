# Design — qualification rank as the third tiebreaker

## Open gaps (decide before implementing)

| # | Gap | Effect if left open |
| - | --- | --- |
| 1 | §2.6.6.2 says "Wynik w kwalifikacjach" — score, not rank. Rank smuggles golds/X in as a fourth criterion. | The whole change may be wrong; needs a PZŁucz ruling. |
| 2 | What `IndRank` holds for walkovers, DNS and DSQ on the dev install — asserted as 0, not verified. | The unranked-sorts-last rule may key off the wrong value. |
| 3 | Whether finished tournaments get recalculated. | Placements could change retroactively on old competitions. |

## Current state

Both calc classes select the qualification total and sort on it:

- `Obj_Rank_FinalInd_calc.php`: `COALESCE(qq.QuScore, 0) AS QualScore`, then `usort` on `avgMatch` desc, `avgTie` desc, `qualScore` desc.
- `Obj_Rank_FinalTeam_calc.php`: `te.TeScore AS QualScore`, same comparator shape.

Shared places are assigned by comparing the same three values against the previous row.

## Change

Swap the selected column and flip the comparison direction for that one criterion:

- Individual: select `i.IndRank` (the query already joins `Individuals AS i` for `AthRank`, so this may need no new join — confirm during implementation).
- Team: select `TeRank`; ianseo's own PL set adds `INNER JOIN Teams ON TeCoId = tf.TfTeam AND TeSubTeam = tf.TfSubTeam` for exactly this, which is the join to copy.
- Comparator: `$a['qualRank'] <=> $b['qualRank']` (ascending) in place of `$b['qualScore'] <=> $a['qualScore']`.
- Unranked guard: map rank `0` (and DNS/DSQ statuses) to `PHP_INT_MAX` before comparing, so they sort last. This is the part with no equivalent in ianseo's PL set, which subtracts the raw rank from a packed float and would silently put rank 0 first.
- Shared-place detection compares the same three fields, so it follows automatically.

## Why not copy ianseo's packed-float approach

Their implementation is one expression:

```php
$lstMatches[$MatchNo] = $avg[0]*1000000 + ($avg[1]/100)*1000 - $myRow->AthRank;
```

It encodes three criteria in one float. That is compact but collides for large ranks and cannot express "unranked sorts last". The explicit comparator already in our two files stays; only the third field changes.

## Files

- **Modified:** `Rank/Obj_Rank_FinalInd_calc.php`, `Rank/Obj_Rank_FinalTeam_calc.php`
- **Tests:** existing tiebreak cases in the rank test files, plus new cases for equal score / different rank, and for unranked competitors
- **Menu:** none — no user-facing surface
- **DB:** none
