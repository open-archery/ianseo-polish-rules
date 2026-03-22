## 1. Verify Obj_RankFactory registration pattern

- [x] 1.1 Read `Common/Rank/Obj_RankFactory.php` and confirm how PL-specific subclasses are discovered/registered for the `DivClass` rank type

## 2. Create Obj_Rank_DivClass_PL rank subclass

- [x] 2.1 Create `Modules/Sets/PL/Rank/Obj_Rank_DivClass_PL.php` extending `Obj_Rank_DivClass`
- [x] 2.2 Override `read()`: call `parent::read()`, then collect all athlete IDs from `rankData['sections'][*]['items']`
- [x] 2.3 Run a single `SELECT EnId, EnDob FROM Entries WHERE EnId IN (...)` query and stitch `birthdate` into each item
- [x] 2.4 Register the subclass with `Obj_RankFactory` per the pattern confirmed in task 1.1

## 3. Update qualification PDF chunk

- [x] 3.1 In `DivClasIndividual.inc.php`, shrink the athlete name cell width by 10mm in both `pl_writeGroupHeaderPrnIndividual` and `pl_writeDataRowPrnIndividual`
- [x] 3.2 Add "Rok ur." header cell (10mm) immediately after the "Nr lic." header cell
- [x] 3.3 Add birth year data cell (10mm) immediately after the `$item['bib']` cell; extract year with `substr($item['birthdate'], 0, 4)` and blank it when year is `'0000'`, `'1900'`, `''`, or `'0'`

## 4. Update finals PDF chunk

- [x] 4.1 In `RankIndividual.inc.php`, shrink the athlete name cell width by 10mm in the header and data row
- [x] 4.2 Add "Rok ur." header cell (10mm) immediately after the "Nr lic." header cell
- [x] 4.3 Add birth year data cell (10mm) immediately after the `$item['bib']` cell with the same blank-rendering logic

## 5. Manual verification

- [x] 5.1 Generate a qualification results PDF for a PL tournament and confirm "Rok ur." column appears with correct values
- [x] 5.2 Generate a finals results PDF and confirm the same
- [ ] 5.3 Confirm athletes with unknown DOB (`EnDob = 0` or year `1900`) show an empty cell, not `0` or `0000`
- [ ] 5.4 Confirm a non-PL tournament PDF is unaffected
