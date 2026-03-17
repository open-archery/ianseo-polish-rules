## 1. Create chunk override directory

- [ ] 1.1 Create directory `Modules/Sets/PL/pdf/chunks/`

## 2. Finals ranking PDF override

- [ ] 2.1 Copy `Common/pdf/chunks/RankIndividual.inc.php` to `Modules/Sets/PL/pdf/chunks/RankIndividual.inc.php`
- [ ] 2.2 Add a 16mm "Nr lic." column header after the rank column in the header row, reducing the athlete column width by 16mm (`40` → `24`, dynamic part unchanged)
- [ ] 2.3 Add `$item['bib']` cell (16mm) in the data row at the same position

## 3. Qualification ranking PDF override

- [ ] 3.1 Copy `Common/pdf/chunks/DivClasIndividual.inc.php` to `Modules/Sets/PL/pdf/chunks/DivClasIndividual.inc.php`
- [ ] 3.2 Add a 16mm "Nr lic." column header in `writeGroupHeaderPrnIndividual()` after the session/target column, reducing the athlete column width by 16mm (`37` → `21`)
- [ ] 3.3 Add `$item['bib']` cell (16mm) in `writeDataRowPrnIndividual()` at the same position
