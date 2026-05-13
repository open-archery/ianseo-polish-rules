## 1. Refactor Data Row Height

- [ ] 1.1 Change the page-break guard from `SamePage(4)` to `SamePage(8)` in the data row path
- [ ] 1.2 Change the `NeedTitle` repeat-header page-break guard from `SamePage(4)` to `SamePage(8)`
- [ ] 1.3 Update all non-finals Cell() height arguments in the data row from `4` to `8` (rank, athlete, bib, birth year, countryCode, countryName, qualScore, and elim columns)

## 2. Refactor Finals Cell Rendering

- [ ] 2.1 Before the finals loop, save cursor position as `$TmpX / $TmpY`
- [ ] 2.2 Add top sub-row pass: for each phase, render the 7px score sub-cell and 5px tiebreak-label sub-cell at `$TmpY`
- [ ] 2.3 Add bottom sub-row pass: call `$pdf->setXY($TmpX, $TmpY + 4)`, then for each phase render the 7px avgMatch sub-cell and 5px avgTie sub-cell
- [ ] 2.4 After both passes, restore X position to end of finals block with `$pdf->setXY($pdf->GetX(), $TmpY)`

## 3. Score / Set-Points Display Logic

- [ ] 3.1 Implement set-system detection: `$isSetSystem = !empty($v['setPoints'])`
- [ ] 3.2 Top-left sub-cell: display `$v['setScore']` when `$isSetSystem`, otherwise `$v['score']`; use bold font size 8; border `'TLB'`
- [ ] 3.3 Top-right sub-cell (5px): display `'T.' . str_replace('|', '', $v['tiebreak'])` when `$cntPhases == count($item['finals'])` and `floatval($v['avgTie']) > 0`, otherwise empty; border `'TRB'`

## 4. Average Display Logic

- [ ] 4.1 Bottom-left sub-cell (7px): display `avgMatch` formatted to 3 dp when non-zero, otherwise empty; use normal font size 7; border `'LB'`
- [ ] 4.2 Bottom-right sub-cell (5px): display `avgTie` formatted to 3 dp when `$cntPhases == count($item['finals'])` and `floatval($v['avgTie']) > 0`, otherwise empty; border `'RB'`

## 5. Bye Cell

- [ ] 5.1 Update the bye branch to render a single 12×8 cell (height 8 instead of 4) containing the `-Wolne-` label

## 6. Verify

- [ ] 6.1 Generate an elimination results PDF for a recurve event and confirm: set points visible top-left, arrow average bottom-left, all rows properly aligned
- [ ] 6.2 Confirm tiebreak annotation (T.X top-right, avgTie bottom-right) appears only on the last phase cell for archers who had a shoot-off
- [ ] 6.3 Confirm compound event shows cumulative score (not set points) in top-left
- [ ] 6.4 Confirm bye archers show `-Wolne-` in a full-height cell
