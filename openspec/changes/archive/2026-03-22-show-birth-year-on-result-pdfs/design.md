## Context

The PL module overrides two ianseo PDF chunk files to inject a "Nr lic." column into results PDFs. The same override mechanism is used here to add a "Rok ur." (year of birth) column immediately after "Nr lic." in both the qualification and finals layouts.

`EnDob` (stored as `YYYY-MM-DD` or `0`) is already present in the `Entries` table. The core `Obj_Rank_FinalInd` class already exposes it as `$item['birthdate']`. However, `Obj_Rank_DivClass` (used for qualification PDFs) does not include `EnDob` in its SELECT or item array, requiring a PL-specific subclass.

## Goals / Non-Goals

**Goals:**
- Add a 10mm "Rok ur." column immediately after "Nr lic." in qualification and finals result PDFs
- Show 4-digit birth year; blank for `EnDob = 0` or year `1900`
- Keep ianseo core untouched

**Non-Goals:**
- Full date of birth display
- Team result PDFs
- Diploma PDFs

## Decisions

### 1. Rank subclass for qualification data enrichment

`Obj_Rank_DivClass` does not expose `EnDob`. Rather than modifying the core class, a PL subclass (`Obj_Rank_DivClass_PL`) will call `parent::read()` then run one supplementary query:

```sql
SELECT EnId, EnDob FROM Entries WHERE EnId IN (...)
```

...and stitch `birthdate` into each `$item`. This is a single query regardless of result size — no N+1 issue.

**Alternative considered:** Querying per-item inside the PDF chunk. Rejected: mixes data access with rendering, and chunks have no reliable DB handle access pattern.

**How the subclass is loaded:** The factory `Obj_RankFactory` checks for a PL-specific class by name convention before falling back to the core class. The existing `Obj_Rank_FinalInd_calc.php` is registered this way. `Obj_Rank_DivClass_PL.php` follows the same pattern.

### 2. Column placement: after "Nr lic."

Birth year groups with other personal identifiers (licence, year) rather than with scoring data. Placing it immediately after "Nr lic." in both layouts is consistent and requires no re-ordering of other columns.

### 3. Width: 10mm

A 4-digit year at font size 7 renders comfortably in 10mm. The licence column uses 16mm (licence codes are variable-length strings); birth year is always exactly 4 characters.

### 4. Space reclaimed from athlete name column

The athlete name column is shrunk by 10mm in both layouts. This is the only flexible text column with headroom. Country/club columns are left unchanged as they can already be tight for long club names.

### 5. Blank rendering for missing/placeholder DOB

`EnDob = 0` means unknown. Year `1900` is used as a placeholder for "unknown" entries in some import paths. Both cases render as an empty string via:

```php
$year = substr($item['birthdate'], 0, 4);
$display = ($year === '0000' || $year === '1900' || $year === '' || $year === '0') ? '' : $year;
```

## Risks / Trade-offs

- **Athlete name truncation** — shrinking the name column by 10mm may cause long names to truncate in the PDF cell. Risk is low: ianseo already truncates at the current width; the reduction is modest (10mm out of 37–60mm depending on layout). → Accepted trade-off.
- **`Obj_RankFactory` registration** — the factory registration pattern must be verified against the actual factory code before implementing. If the pattern differs, the subclass wiring approach needs adjustment.

## Files

| Action | Path |
|--------|------|
| **New** | `Modules/Sets/PL/Rank/Obj_Rank_DivClass_PL.php` |
| **Modify** | `Modules/Sets/PL/pdf/chunks/DivClasIndividual.inc.php` |
| **Modify** | `Modules/Sets/PL/pdf/chunks/RankIndividual.inc.php` |

## Open Questions

- Confirm `Obj_RankFactory` registration pattern for `DivClass` type before implementing the subclass.
