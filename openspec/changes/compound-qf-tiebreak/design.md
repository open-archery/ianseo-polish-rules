## Context

When two Compound athletes have an identical score in a 1/4-final match, ianseo's ranking engine assigns them the same `IndRankFinal` (e.g. both get 5th place). PZŁucz §4.3 requires using the 10/X/9 arrow count from that match to split them. ianseo does not persist per-arrow scores for elimination rounds, so the count must be entered manually by the operator. This change adds the storage, detection, UI, and corrected-points logic.

## Goals / Non-Goals

**Goals:**
- `PLQfTiebreak` DB table: stores 10/X/9 counts per (tournament, athlete), auto-installed
- Tie detection: identify athletes in a combined ranking who share an elimination place in the QF-loser range (places 5–8) within the same tournament and division+class
- Warning UI: banner on `CombinedRanking.php` listing each ambiguous (athlete, tournament) pair
- Data entry: inline form to enter and persist 10/X/9 counts per pair
- Corrected computation: when counts are stored, reassign elimination places for tied athletes and recalculate their points before sorting
- PDF marker: footnote on any section that still contains an unresolved tie

**Non-Goals:**
- Applying this to Recurve or other divisions
- Changing the combined ranking sort (total_pts DESC → best_2x50m DESC remains unchanged)
- Reading arrow scores from ianseo automatically

## Decisions

### D1: Tie detection by shared `IndRankFinal` in QF range

After loading athlete data, group Compound athletes by (tournament, division+class). Within each group, find athletes sharing the same `elim_rank` where that rank falls in 5–8 (QF losers in an 8-athlete bracket). These are the candidates for 10/X/9 resolution.

Alternative considered: detect ties by querying `Finals` match scores directly. Rejected: more complex join, and `IndRankFinal` already reflects the tie.

### D2: Corrected place assignment by 10/X/9 count DESC

When stored counts exist for all athletes in a tied group, sort them by count DESC and assign sequential places starting from the lowest shared rank. Example: two athletes both at rank 5 with counts 8 and 6 → counts athlete gets 5th, 6-count athlete gets 6th.

When counts exist for only some athletes in a tied group, those with counts rank above those without (count = null treated as unresolved, not 0).

### D3: Correction applied in `pl_combined_ranking_compute()`, not in load

The load function (`pl_combined_ranking_load()`) returns raw `IndRankFinal` values unchanged. The correction happens in `pl_combined_ranking_compute()` after merging, so the data layer stays clean and the correction is visible only in the combined ranking context.

### D4: Warning and form on `CombinedRanking.php`, not a separate page

The data-entry form is inline on the existing combined ranking page, shown only when ties are detected. No new menu entry needed. The form POSTs to the same page; the page handles both display and save actions.

### D5: `PLQfTiebreak` keyed on (tournament, EnCode), unique constraint

One row per athlete per tournament. Duplicate submissions update the existing count (INSERT … ON DUPLICATE KEY UPDATE). Operator can correct an entry by resubmitting.

## Files to Create / Modify

```
NEW     DB table PLQfTiebreak (auto-installed in Fun_CombinedRanking.php)
          PlQtId          INT AUTO_INCREMENT PK
          PlQtTournament  INT NOT NULL
          PlQtEnCode      VARCHAR(20) NOT NULL
          PlQtArrows      TINYINT UNSIGNED NOT NULL
          UNIQUE KEY (PlQtTournament, PlQtEnCode)

MODIFY  CombinedRanking/Fun_CombinedRanking.php
          pl_combined_ranking_install_qf_table()   — auto-install PLQfTiebreak
          pl_combined_ranking_load_qf_counts($t1, $t2) — load stored counts keyed by [tourId][enCode]
          pl_combined_ranking_save_qf_count($tourId, $enCode, $arrows) — upsert one row
          pl_combined_ranking_detect_qf_ties($sections, $qfCounts) — return list of unresolved ties
          pl_combined_ranking_apply_qf_counts($sections, $qfCounts) — correct elim places and recompute pts

MODIFY  CombinedRanking/CombinedRanking.php
          — call install on page load
          — handle POST: save QF count, reload
          — detect ties after compute; pass tie list to view
          — render warning banner + per-tie input form when ties present

MODIFY  CombinedRanking/PrnCombinedRanking.php
          — accept $hasUnresolvedTies flag per section
          — add footnote "* Remis nierozstrzygnięty — brak danych 10/X/9" when flag set
```

## Risks / Trade-offs

- **Operator error** — manually entered counts can be wrong. Mitigation: form shows athlete name + tournament name clearly; values are editable on resubmit.
- **Stale counts after re-run** — if the operator re-runs the tournament ranking after entering counts, `IndRankFinal` may change, but stored counts remain. Mitigation: counts are always applied as a post-load override; if places no longer tie, counts are silently ignored.
- **8-athlete assumption** — QF-loser range hardcoded as places 5–8. If a Compound bracket has fewer entries (e.g. 4 athletes, no QF), detection may flag incorrectly. Mitigation: also verify that both athletes have a `Finals` row (confirmed bracket participation) before flagging.
