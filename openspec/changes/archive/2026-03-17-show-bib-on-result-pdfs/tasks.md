## 1. Create chunk override directory

- [x] 1.1 Create directory `Modules/Sets/PL/pdf/chunks/`

## 2. Finals ranking PDF override

- [x] 2.1 Copy `Common/pdf/chunks/RankIndividual.inc.php` to `Modules/Sets/PL/pdf/chunks/RankIndividual.inc.php`
- [x] 2.2 Add a 16mm "Nr lic." column header after the rank column in the header row, reducing the athlete column width by 16mm (`40` → `24`, dynamic part unchanged)
- [x] 2.3 Add `$item['bib']` cell (16mm) in the data row at the same position

## 3. Qualification ranking PDF override

- [x] 3.1 Copy `Common/pdf/chunks/DivClasIndividual.inc.php` to `Modules/Sets/PL/pdf/chunks/DivClasIndividual.inc.php`
- [x] 3.2 Add a 16mm "Nr lic." column header in `writeGroupHeaderPrnIndividual()` after the session/target column, reducing the athlete column width by 16mm (`37` → `21`)
- [x] 3.3 Add `$item['bib']` cell (16mm) in `writeDataRowPrnIndividual()` at the same position
