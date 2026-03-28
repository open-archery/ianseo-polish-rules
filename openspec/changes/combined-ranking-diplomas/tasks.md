## 1. Diploma Printer

- [x] 1.1 Create `CombinedRanking/PrnCombinedRankingDipl.php` with session guard, require chain (config.php, Fun_CombinedRanking.php, Diplomas/DiplomaSetup.php, Diplomas/PLDiplomaPdf.php)
- [x] 1.2 Read and validate POST inputs: `tour1` (required), `tour2` (optional), `diplDate` (required); die with Polish error if missing
- [x] 1.3 Load diploma config via `pl_diploma_get_config($_SESSION['TourId'])`; show warning if CompetitionName is empty
- [x] 1.4 Compute combined ranking: `load` → `merge` → `get_div_labels` → `compute`
- [x] 1.5 Iterate sections and rows; skip rows outside `PlaceFrom`..`PlaceTo`; die with Polish message if no qualifying rows
- [x] 1.6 Call `PLDiplomaPdf::printDiploma()` for each qualifying row with correct field mapping (diplDate as dates, section label as classText, titleText='')
- [x] 1.7 Stream PDF output as `dyplomy_ranking_laczony.pdf`

## 2. UI Extension

- [x] 2.1 Add a horizontal separator after the existing "Generuj PDF" button in `CombinedRanking.php`
- [x] 2.2 Add a second `<form>` (POST to `PrnCombinedRankingDipl.php`) with duplicated tour1/tour2 dropdowns, a text input for `diplDate` (labelled "Data na dyplomie"), and a "Generuj dyplomy" submit button
