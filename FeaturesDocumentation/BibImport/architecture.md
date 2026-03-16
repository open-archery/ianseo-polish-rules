# BibImport — Architecture

**Feature:** Bib List Batch Import
**Author:** Developer agent
**Status:** Design complete

---

## Overview

BibImport lets a tournament operator paste a list of PZŁucz licence numbers
(one per line) into a textarea, select a single bow-type division, and import
all recognised athletes as `Entries` records in the current tournament.

This feature does **not** create tournament setup structures (Divisions, Classes,
Events). It only creates `Entries` and (when needed) `Countries` rows. No new
DB tables are required.

---

## Integration Approach

### Tables read

| Table          | Purpose                                                      |
|----------------|--------------------------------------------------------------|
| `LookUpEntries`| Athlete registry populated by Sportzona sync (IOC = `POL`)  |
| `Divisions`    | Populate the division dropdown for the operator              |
| `Classes`      | Age class resolution per athlete                             |
| `Countries`    | Resolve or create a club record for each athlete             |
| `Entries`      | Duplicate check                                              |

### Tables written

| Table       | Purpose                                    |
|-------------|---------------------------------------------|
| `Countries` | Upsert club record if it does not exist     |
| `Entries`   | Create one row per successfully imported athlete |

---

## Age Class Resolution Algorithm

### Inputs
- Athlete birth year: `substr($lue->LueCtrlCode, 0, 4)` (LueCtrlCode is `'YYYY-01-01'`)
- Tournament start year: `substr($_SESSION['TourWhenFrom'], 0, 4)` (not used in
  the SQL query itself, but used to determine the reference date context if
  needed in the future — currently the Classes table stores absolute birth-year
  ranges, not age ranges)
- Operator-selected division: `$division` (e.g. `'R'`, `'C'`, `'B'`)
- Athlete sex: `$lue->LueSex` — `0` = male → `ClSex = 1`; `1` = female → `ClSex = 2`

### SQL query
```sql
SELECT *
FROM Classes
WHERE ClTournament = {tourId}
  AND ClSex        = {clSex}
  AND (ClAlDivision = '' OR FIND_IN_SET({division}, ClAlDivision))
  AND (ClFrom = '' OR ClFrom = '0' OR ClFrom <= {birthYear})
  AND (ClTo   = '' OR ClTo   = '0' OR ClTo   >= {birthYear})
ORDER BY (ClTo - ClFrom) ASC
```

The `ORDER BY (ClTo - ClFrom) ASC` selects the narrowest matching birth-year
range first, which corresponds to the most specific age class (e.g. U18 before
the generic senior M class).

### Edge cases

| Scenario | Handling |
|----------|----------|
| No matching class | Import entry with `EnClass = ''`; add to class-unresolved list |
| Multiple matching classes | Take first result (narrowest range) |
| `ClFrom` = `'0'` or `''` | Treated as "no lower bound" |
| `ClTo`   = `'0'` or `''` | Treated as "no upper bound" |
| `ClAlDivision` = `''`    | Class allows all divisions |
| LueCtrlCode is null/empty | Birth year defaults to `'0'`; no class match → class-unresolved |

---

## Country/Club Upsert Strategy

1. `SELECT CoId FROM Countries WHERE CoTournament = ? AND CoCode = ?`
2. If not found: `INSERT INTO Countries SET CoTournament=?, CoCode=?, CoName=?, CoShortName=?`
   then use `safe_w_last_id()` as `CoId`.
3. `CoCode` is limited to **6 characters** in ianseo — `LueCountry` is always a
   3-letter club code (e.g. `'CSB'`), so truncation is not needed in practice,
   but the code uses `substr($lue->LueCountry, 0, 6)` defensively.
4. `CoName` ← `LueCoDescr` (full club name)
5. `CoShortName` ← `LueCoShort` (short club name, only set if column exists)

Note: `Countries.CoShortName` may not exist in all ianseo installations. The
INSERT uses only `CoCode` and `CoName` if `CoShortName` is absent; the
`Fun_BibImport.php` function checks for the column dynamically. However, to
keep implementation simple and consistent with how ianseo itself handles the
column, we include it unconditionally — it is present in all recent ianseo
versions.

---

## Transaction Scope

All `INSERT INTO Entries` statements for a single form submission are wrapped in
a single transaction:

```
safe_w_BeginTransaction()
  foreach valid athlete:
    upsert Countries record (auto-committed per INSERT — outside transaction
      semantics for FK safety, but in practice still within the transaction
      block so a rollback will undo them too)
    INSERT INTO Entries ...
safe_w_Commit()  ← on success
safe_w_Rollback() ← on any DB error
```

The Countries upsert is included inside the same transaction so a rollback on
any entry INSERT also reverts newly created country rows from the same batch.

---

## EnFirstName vs EnName Column Mapping

ianseo stores **family name** in `EnFirstName` and **given name** in `EnName`.
This is confirmed by `Partecipants/LookupTableLoad.php` lines 34–41.

Mapping:
- `LueFamilyName` → `EnFirstName`
- `LueName`       → `EnName`

---

## Files to Create

| File | Responsibility |
|------|----------------|
| `Modules/Sets/PL/Import/BibImport.php` | Main UI page: form rendering, POST handling, result display |
| `Modules/Sets/PL/Import/Fun_BibImport.php` | Processing functions: lookup, age class resolution, country upsert, entry creation |
| `FeaturesDocumentation/BibImport/architecture.md` | This document |

---

## Menu Placement

Added under `PART` (Participants) in `menu.php`:

```php
$ret['PART'][] = 'Import bib|' . $CFG->ROOT_DIR . 'Modules/Sets/PL/Import/BibImport.php';
```

This follows the same `if ($on && $_SESSION["TourLocRule"] == 'PL')` guard
already present in `menu.php`.

---

## Sportzona Warning Logic

Before rendering the form, the page checks:

```sql
SELECT COUNT(*) FROM LookUpEntries WHERE LueIocCode = 'POL'
```

If zero rows are found, a prominent warning banner is displayed with a link to
`Modules/Sets/PL/Lookup/Install.php`.

---

## Result Display Order (POST)

1. Success count (green box)
2. Duplicate table (if any)
3. Unmatched table (if any)
4. Class-unresolved table (if any)
5. Form again (division pre-selected to previous value, textarea empty)
