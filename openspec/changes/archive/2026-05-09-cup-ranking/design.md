## Context

The `CombinedRanking/` module (on `feat/merged-ranking`) is the reference implementation: a PDF printout page that loads elimination data, applies a points formula, groups by division+class, and renders via TCPDF. This new module follows the same structure but strips out everything related to a second tournament, qualification data, and compound-specific handling.

`IndRankFinal` is already computed by the existing PL ranking override (`Rank/Obj_Rank_FinalInd_calc.php`) and stored on `Individuals`. No re-computation is needed — the cup ranking is a pure read of that value.

## Goals / Non-Goals

**Goals:**
- One-click PDF from the current session tournament
- Points assigned per the Puchar Polski table (places 1–32 only)
- Athletes with 0 points (place 33+ or no bracket entry) omitted
- Separate section per division+class, ordered by preferred display order (RM, RW, CM, CW, BM, BW)
- Within the same-points group, ordered by `IndRankFinal` ASC (actual elimination place)

**Non-Goals:**
- Tournament selector / cross-tournament accumulation
- Qualification points
- Compound bow special handling
- Team events
- On-screen HTML view
- Storing results to the database

## Decisions

### No tournament selector — use session directly

**Decision:** Read `$_SESSION['TourId']` without exposing a select UI.

**Why:** The regulation context ("ranking for this round") implies the current open tournament is always the right scope. A selector adds complexity for no user benefit here.

**Alternative considered:** Pre-fill a select (as in CombinedRanking). Rejected — unnecessary given single-tournament scope.

---

### Source field: `IndRankFinal` on `Individuals`

**Decision:** Pull final place directly from `Individuals.IndRankFinal`.

**Why:** Already computed and stored by the PL ranking override. No need to re-derive from `Finals` match records. Athletes without a `Finals` entry (did not enter the bracket) have `IndRankFinal = 0` and are filtered out naturally.

**Alternative considered:** Query `Finals` directly and rank in PHP. Rejected — duplicates logic already in the ranking override; `IndRankFinal` is authoritative.

---

### Points as PHP lookup array, not a formula

**Decision:** Express the points table as a PHP array keyed by place:

```php
$PL_CUP_POINTS = [
    1 => 25, 2 => 21, 3 => 18, 4 => 15,
    5 => 13, 6 => 12, 7 => 11, 8 => 10,
];
// Places 9–16 → 5, 17–32 → 1, else → 0
```

**Why:** The table is irregular (25, 21, 18, 15 are not a linear progression). A conditional range check for the 9–16 and 17–32 bands is cleaner than squeezing this into a single formula.

---

### Sort order within same-points group: `IndRankFinal` ASC

**Decision:** Athletes with the same points (e.g. all 5-pointers at places 9–16) are ordered by their actual elimination place ascending.

**Why:** The regulation table implies place 9 is better than place 10; using `IndRankFinal` directly preserves the bracket result order without needing a separate tiebreaker column.

---

### Simple GET → page with button, POST → stream PDF

**Decision:** `CupRanking.php` shows a simple page with one "Generuj PDF" button. POST streams the PDF immediately (no intermediate state, no re-render with tie warnings).

**Why:** No multi-step form state exists (no tournament selection, no tie resolution). The page is stateless.

## Risks / Trade-offs

- **`IndRankFinal = 0` for unranked athletes** — athletes who participated in eliminations but whose ranking has not yet been computed will appear to have no bracket entry and be omitted. This is correct behaviour (ranking must be run first).
- **Division+class labels from `Divisions` / `Classes` tables** — if a tournament's divisions/classes are not configured with descriptions, the label falls back to the raw key (e.g. "RM"). Same behaviour as CombinedRanking.

## Files to Create / Modify

| File | Action | Purpose |
|------|--------|---------|
| `CupRanking/CupRanking.php` | Create | Entry page and POST handler |
| `CupRanking/Fun_CupRanking.php` | Create | Data layer: load, compute points, group |
| `CupRanking/PrnCupRanking.php` | Create | PDF renderer (TCPDF) |
| `menu.php` | Modify | Add `PRNT` menu entry |

### menu.php addition

```php
$ret['PRNT'][] = 'Ranking Pucharu Polski|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/CupRanking/CupRanking.php';
```

Added after the existing Dyplomy entry, before the divider (or after it — visual order to confirm with user).

### Query (Fun_CupRanking.php)

Single query joining `Individuals`, `Entries`, `Countries`, with a filter on `IndRankFinal BETWEEN 1 AND 32`:

```sql
SELECT
    e.EnCode        AS licence,
    e.EnName        AS last_name,
    e.EnFirstName   AS first_name,
    co.CoName       AS club,
    e.EnDivision    AS division,
    e.EnClass       AS class,
    i.IndRankFinal  AS elim_rank
FROM Individuals i
INNER JOIN Entries e
    ON e.EnId = i.IndId AND e.EnTournament = i.IndTournament
LEFT JOIN Countries co
    ON co.CoId = e.EnCountry AND co.CoTournament = e.EnTournament
INNER JOIN Events ev
    ON ev.EvCode = i.IndEvent AND ev.EvTournament = i.IndTournament
    AND ev.EvTeamEvent = 0
WHERE i.IndTournament = {$tourId}
  AND i.IndRankFinal BETWEEN 1 AND 32
ORDER BY e.EnDivision, e.EnClass, i.IndRankFinal ASC
```

### PDF layout (PrnCupRanking.php)

One section per division+class. Per section:
- Bold centred title (e.g. "Recurve Mężczyźni")
- Table with columns: **Lp.** | **Imię Nazwisko** | **Klub** | **Nr licencji** | **Miejsce** | **Punkty**
- Rows sorted: points DESC, `IndRankFinal` ASC within same-points group
- Athletes with 0 points omitted (already filtered by query)
