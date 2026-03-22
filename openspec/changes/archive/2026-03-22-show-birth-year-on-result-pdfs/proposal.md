## Why

Polish competition officials need athlete birth year visible on printed results to verify age-class eligibility at a glance. The licence number column added previously shows identity; birth year completes the picture without requiring a separate roster printout.

## What Changes

- Add a **"Rok ur."** (year of birth) column to the **qualification results PDF** (`DivClasIndividual` chunk)
- Add a **"Rok ur."** column to the **finals ranking PDF** (`RankIndividual` chunk)
- Column width: 10mm; athlete name column shrunk by 10mm in both layouts to accommodate it
- Placement: immediately after the "Nr lic." column
- Display: 4-digit year extracted from `EnDob`; blank if `EnDob` is `0` (unknown) or year is `1900` (placeholder)
- Create a PL-specific rank override for `Obj_Rank_DivClass` to expose `EnDob` in the item array (it is absent from the core class; finals class already includes it)

## Capabilities

### New Capabilities

- `birth-year-on-result-pdfs`: Display athlete year of birth on qualification and finals result PDFs

### Modified Capabilities

<!-- none — existing bib-on-result-pdfs spec is not changing requirements, only a new column is added -->

## Impact

- **New file:** `Modules/Sets/PL/Rank/Obj_Rank_DivClass_PL.php` — PL rank subclass enriching items with birthdate
- **Modified:** `Modules/Sets/PL/pdf/chunks/DivClasIndividual.inc.php` — new column + layout adjustment
- **Modified:** `Modules/Sets/PL/pdf/chunks/RankIndividual.inc.php` — new column + layout adjustment
- No DB schema changes; `EnDob` already exists in `Entries`
- No menu or install script changes

## Non-goals

- Showing full date of birth (only year)
- Modifying team results PDFs
- Adding birth year to diploma PDFs
