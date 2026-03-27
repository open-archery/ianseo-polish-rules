## 1. Data Layer (Fun_CombinedRanking.php)

- [x] 1.1 Create `CombinedRanking/Fun_CombinedRanking.php` with `pl_combined_ranking_get_tournaments()` — queries all tournaments ordered by date desc, returns array of `{ToId, ToName, ToWhenFrom}`
- [x] 1.2 Implement `pl_combined_ranking_load($tourId)` — runs the main athlete query (Entries LEFT JOIN Qualifications LEFT JOIN Individuals), uses a pre-fetched bracket participant set from `Finals` to set `in_bracket` flag, returns array keyed by `EnCode`
- [x] 1.3 Implement `pl_combined_ranking_merge($data1, $data2)` — FULL OUTER JOIN logic in PHP: union of all `EnCode` keys from both arrays, preferring Tournament 1 athlete info (name/club/div/class) when present in both
- [x] 1.4 Implement `pl_combined_ranking_points($place, $type)` — returns `max(0, 16 - $place)` for `'qual'` and `max(0, (16 - $place) * 2)` for `'elim'`; returns 0 for null place
- [x] 1.5 Implement `pl_combined_ranking_compute($merged)` — groups athletes by div+class, applies points formula to each, calculates `best_2x70m = max(d1_qual_score, d2_qual_score)`, sorts each group by `total_pts DESC` then `best_2x70m DESC`, assigns final `rank`
- [x] 1.6 Define div+class sort order: RM, RW, CM, CW, BM, BW, then alphabetical for any others

## 2. UI Page (CombinedRanking.php)

- [x] 2.1 Create `CombinedRanking/CombinedRanking.php` with ianseo bootstrap (`config.php`), session guard (`CheckTourSession(true)`)
- [x] 2.2 Render `<form method="POST">` with two `<select>` elements for Tournament 1 and Tournament 2; Tournament 1 pre-selected with `$_SESSION['TourId']`; Tournament 2 has an empty/blank first option
- [x] 2.3 Populate both selects from `pl_combined_ranking_get_tournaments()` — show tournament name and date
- [x] 2.4 On POST: validate that at least one tournament is selected; show Polish error message if both are empty
- [x] 2.5 On valid POST: call data layer and PDF printer, stream PDF to browser (no HTML page rendered)
- [x] 2.6 Wrap page in `head.php` / `tail.php` (form only shown when no POST or when validation error)

## 3. PDF Printer (PrnCombinedRanking.php)

- [x] 3.1 Create `CombinedRanking/PrnCombinedRanking.php`; include `Common/tcpdf/tcpdf.php` and `Common/pdf/IanseoPdf.php`; define `PLCombinedRankingPdf extends IanseoPdf`
- [x] 3.2 Set landscape A4 orientation in constructor; define column width constants (see design.md layout)
- [x] 3.3 Implement `renderSection($section, $t1Name, $t2Name)` — renders title row, two-row header (Dzień 1 / Dzień 2 spanning cells, then individual column labels), and data rows
- [x] 3.4 Render two-row header: Row 1 uses wide cells spanning four sub-columns for "Dzień 1" and "Dzień 2"; Row 2 renders individual column labels (Kwal Miejsce, Kwal Punkty, Elim Miejsce, Elim Punkty × 2)
- [x] 3.5 Render data rows: blank cell for null elim place, `0` for null elim points; right-align numeric columns; bold rank column; alternate row shading optional
- [x] 3.6 Add page break between sections (`AddPage()` before each section except the first)
- [x] 3.7 Implement `pl_combined_ranking_print($sections, $t1Name, $t2Name)` — instantiates the PDF class, calls `renderSection()` per section, outputs with `Output('ranking_laczony.pdf', 'D')` (force download)

## 4. Menu Registration

- [x] 4.1 Add to `menu.php` under `$ret['PRNT']`: `'Ranking łączony|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/CombinedRanking/CombinedRanking.php'`

## 5. Verification

- [ ] 5.1 With one tournament selected and athletes across RM/RW: verify PDF has two sections, Day 2 columns blank, Najlepsze 2x70m = Day 1 score
- [ ] 5.2 With two tournaments and an athlete in both: verify single row, correct point totals, correct best_2x70m
- [ ] 5.3 With an athlete only in Tournament 2: verify they appear with Day 1 blank/0
- [ ] 5.4 With an athlete who did not enter eliminations: verify elim Miejsce is blank, Punkty = 0
- [ ] 5.5 With tied total points: verify higher best_2x70m athlete ranks higher
- [ ] 5.6 Verify PDF renders without errors when both selects point to the same tournament (edge case)
