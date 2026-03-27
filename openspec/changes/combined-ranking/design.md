## Context

PZŁucz uses two separate ianseo tournaments (run on consecutive days) to select national team athletes. After both events, a combined aggregate ranking must be produced that converts qualification and elimination places from each tournament into points and sums them.

ianseo has no built-in cross-tournament ranking. This feature is entirely additive: a new module page that reads from existing ianseo tables across two tournament IDs, computes the aggregate, and outputs a TCPDF PDF.

## Goals / Non-Goals

**Goals:**
- New `CombinedRanking/` submodule under `Modules/Sets/PL/`
- UI page: two tournament selects (current pre-selected) + generate button
- Data layer: aggregate qual rank, qual score, and final elim rank from up to two tournaments, matched by `EnCode`
- Points formula per spec, sorted by total then by best qual score
- TCPDF PDF with one section per division+class, landscape-friendly wide table

**Non-Goals:**
- Team events
- Persisting rankings to DB
- HTML screen view
- Format validation of selected tournaments

## Decisions

### D1: Athlete matching by `EnCode`

`Entries.EnCode` stores the licence number, which is guaranteed to equal the bib number for PL tournaments. This is the stable cross-tournament identity. Alternative (name-based matching) rejected: names can have spelling differences.

### D2: Detecting elimination bracket participation via `Finals` table

`Individuals.IndRankFinal` is set for all athletes — including those who never entered the bracket (via `calcFromAbs`). To distinguish bracket participants, we check for the existence of a row in `Finals` where `FinTournament = $tourId AND (FinId1 = $entryId OR FinId2 = $entryId)`. No `Finals` row → athlete is qual-only → elim place blank, 0 pts.

Alternative considered: use a score threshold on `IndRankFinal`. Rejected: fragile — qual-only athletes can have high ranks in small fields.

### D3: Page lives within tournament context (option B)

The page is registered in `menu.php` under `PRNT`, guarded by `$on && TourLocRule == 'PL'`. It uses `CheckTourSession(true)`. The current `$_SESSION['TourId']` is pre-selected as Tournament 1. Both selects query the `Tournament` table directly (not filtered by type). This avoids building a standalone page outside ianseo's session model.

### D4: PDF uses TCPDF `Cell()` directly (not `OrisPDF`/`ResultPDF` base classes)

`OrisPDF`/`ResultPDF` are tightly coupled to ianseo's internal rank data structures. The combined ranking data structure is custom. We extend `IanseoPdf` (from `Common/pdf/IanseoPdf.php`) for ianseo header/footer defaults, then render cells directly, following the pattern of `PLDiplomaPdf`.

### D5: Landscape A4 orientation

The table has 14 columns. Landscape A4 (297 × 210 mm, effective ~277 mm content width) fits comfortably. Alternative (portrait) requires very small fonts or wrapping — rejected.

### D6: Division+class order

Tables are rendered in the order they are discovered in the data, sorted by division then sex: Recurve Men (RM), Recurve Women (RW), Compound Men (CM), Compound Women (CW), Barebow Men (BM), Barebow Women (BW). Unknown combinations fall at the end.

## Files to Create / Modify

```
NEW  Modules/Sets/PL/CombinedRanking/CombinedRanking.php
     UI page — tournament selects, generates PDF on POST
     Bootstrap: dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'
     Guard: CheckTourSession(true)

NEW  Modules/Sets/PL/CombinedRanking/Fun_CombinedRanking.php
     pl_combined_ranking_get_tournaments() → array of {ToId, ToName, ToWhenFrom}
     pl_combined_ranking_load($tourId) → array keyed by EnCode:
       { name, club, licence, division, class,
         qual_rank, qual_score,
         elim_rank (null if no Finals row), elim_in_bracket }
     pl_combined_ranking_merge($data1, $data2) → merged array keyed by EnCode
     pl_combined_ranking_points($place, $type) → int  ('qual'|'elim')
     pl_combined_ranking_compute($merged) → array of sections (one per div+class),
       each section: { divClass, label, rows[] }
       each row: { rank, name, club, licence,
                   d1_qual_place, d1_qual_pts, d1_elim_place, d1_elim_pts,
                   d2_qual_place, d2_qual_pts, d2_elim_place, d2_elim_pts,
                   best_2x70m, total_pts }

NEW  Modules/Sets/PL/CombinedRanking/PrnCombinedRanking.php
     Receives $sections[] (from Fun_CombinedRanking.php)
     Extends IanseoPdf; landscape A4
     One section per div+class: bold centred title, double-row header, data rows
     Streams PDF to browser (Content-Disposition: attachment)

MODIFY  Modules/Sets/PL/menu.php
     Add under PRNT: 'Ranking łączony|..../CombinedRanking/CombinedRanking.php'
```

## Key Queries

### Load athletes for one tournament

```sql
SELECT
    e.EnId,
    e.EnCode        AS licence,
    e.EnLastName    AS last_name,
    e.EnFirstName   AS first_name,
    e.EnClub        AS club,
    e.EnDivision    AS division,
    e.EnClass       AS class,
    q.QuRank        AS qual_rank,
    q.QuScore       AS qual_score,
    i.IndRankFinal  AS elim_rank_raw,
    (SELECT COUNT(*) FROM Finals
     WHERE FinTournament = e.EnTournament
       AND (FinId1 = e.EnId OR FinId2 = e.EnId)
     LIMIT 1)       AS in_bracket
FROM Entries e
LEFT JOIN Qualifications q
    ON q.QuId = e.EnId AND q.QuTournament = e.EnTournament
LEFT JOIN Individuals i
    ON i.IndId = e.EnId AND i.IndTournament = e.EnTournament
WHERE e.EnTournament = {$tourId}
  AND e.EnDivision IN ('R','C','B')
ORDER BY e.EnDivision, e.EnClass, e.EnLastName, e.EnFirstName
```

`elim_rank` = `elim_rank_raw` only when `in_bracket = 1`; otherwise NULL.

### Load all tournaments for select

```sql
SELECT ToId, ToName, ToWhenFrom
FROM Tournament
ORDER BY ToWhenFrom DESC, ToName ASC
```

## PDF Layout (Landscape A4, 277 mm content width)

```
Column widths (mm):
 Miejsce        :  10
 Imię Nazwisko  :  45
 Klub           :  35
 Nr licencji    :  20
 D1 Kwal Msc   :  12
 D1 Kwal Pkt   :  12
 D1 Elim Msc   :  12
 D1 Elim Pkt   :  12
 D2 Kwal Msc   :  12
 D2 Kwal Pkt   :  12
 D2 Elim Msc   :  12
 D2 Elim Pkt   :  12
 Najl. 2x70m   :  20
 Łącznie pkt   :  19
                 ──────
                  245 mm  (fits in 277 mm content area)

Header: two rows
  Row 1: [blank] [blank] [blank] [blank] | Dzień 1 (colspan 4) | Dzień 2 (colspan 4) | [blank] | [blank]
  Row 2: Miejsce | Imię Nazwisko | Klub | Nr lic. | Kwal Msc | Kwal Pkt | Elim Msc | Elim Pkt | Kwal Msc | Kwal Pkt | Elim Msc | Elim Pkt | Najl. 2x70m | Łącznie pkt
```

## Risks / Trade-offs

- **`Finals` detection query** — using a correlated subquery per athlete is readable but may be slow for very large tournaments. Mitigation: cache bracket participants in a keyed array before the main query (two queries instead of a correlated subquery).
- **EnCode uniqueness** — if `EnCode` is empty or duplicated within a tournament, matching breaks. Mitigation: filter `WHERE EnCode != ''` and document the assumption.
- **Single-tournament mode** — when Tournament 2 is empty, Day 2 columns are all blank/0 and "Najlepsze 2x70m" = Day 1 `QuScore`. This is correct per spec.
- **TCPDF colspan** — TCPDF does not natively support `colspan`. The two-row merged header requires careful width arithmetic. Mitigation: render "Dzień 1" as a single wide cell spanning 4 column widths (48 mm).

## Open Questions

- **Q1**: Should "Najlepsze 2x70m" display the raw integer score (e.g. `672`) or a formatted string (e.g. `672 pkt`)? → Assume raw integer for now.
- **Q2**: When both tournaments are empty (both selects cleared), should the page show an error or silently render an empty PDF? → Show a validation error message on the UI page, do not generate PDF.
