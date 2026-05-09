## 1. Data layer

- [x] 1.1 Create `CupRanking/Fun_CupRanking.php` with `pl_cup_ranking_load($tourId)` — queries `Individuals` + `Entries` + `Countries` + `Events`, filters `IndRankFinal BETWEEN 1 AND 32`, returns athlete rows with `elim_rank`, `name`, `club`, `licence`, `division`, `class`
- [x] 1.2 Implement `pl_cup_ranking_points($place)` — returns points from the Puchar Polski table (1→25 … 8→10, 9–16→5, 17–32→1, else→0)
- [x] 1.3 Implement `pl_cup_ranking_compute($rows, $labels)` — applies points, groups by division+class in preferred order (RM, RW, CM, CW, BM, BW), sorts each section by points DESC then `elim_rank` ASC, assigns sequential `rank`
- [x] 1.4 Implement `pl_cup_ranking_get_div_labels($tourId)` — returns map of divClass key → display label from `Divisions` + `Classes` tables (reuse pattern from CombinedRanking)

## 2. PDF renderer

- [x] 2.1 Create `CupRanking/PrnCupRanking.php` with `pl_cup_ranking_print($sections, $tourName)` — streams A4 PDF via TCPDF
- [x] 2.2 Render one section per division+class with bold centred title (division+class description)
- [x] 2.3 Render table with columns: Lp., Imię Nazwisko, Klub, Nr licencji, Miejsce, Punkty

## 3. Entry page

- [x] 3.1 Create `CupRanking/CupRanking.php` — GET renders a page with a "Generuj PDF" button; POST calls `pl_cup_ranking_load`, `pl_cup_ranking_compute`, `pl_cup_ranking_print` and exits
- [x] 3.2 Include `CheckTourSession(true)`, correct bootstrap path (`dirname × 4 . '/config.php'`), and `Common/Templates/head.php` / `tail.php`

## 4. Menu

- [x] 4.1 Add `'Ranking Pucharu Polski|...'` entry to `menu.php` under `$ret['PRNT'][]`, guarded by `$on && TourLocRule == 'PL'`
