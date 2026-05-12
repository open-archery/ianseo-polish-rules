## Context

ianseo's `Entries` table has two separate class columns:

| Column | Purpose |
|---|---|
| `EnAgeClass` | The athlete's age class (`Classes.ClId`, e.g. `'U18M'`) — what the participant list displays as "class" |
| `EnClass` | The competition class the athlete is scored under (`ClValidClass` entry, e.g. `'U18M'`) — used for ranking |

When an operator manually assigns a class via `Partecipants/UpdateClass.php`, ianseo always writes both columns together. Our `pl_bibimport_create_entry()` only ever wrote `EnClass`. Every athlete imported via BibImport therefore had an empty `EnAgeClass`, appearing as "class empty" in the participant list.

Reference: `Partecipants/UpdateClass.php:85-90`, `Partecipants/SelectAgeClass.php:43-45`, `Participants/getRows.php:468-470`.

## Goals / Non-Goals

**Goals:**
- Set `EnAgeClass = resolved ClId` in the `Entries` INSERT, mirroring `EnClass`.
- Update the bib-import spec to document both columns in the Entry Creation step.

**Non-Goals:**
- Backfilling `EnAgeClass` on previously imported entries.
- Setting `EnSubClass` — PL tournaments create no `SubClass` records.
- Any change to `pl_bibimport_resolve_class()` — age class resolution is correct.

## Decisions

**Set `EnAgeClass = $classId` (same value as `EnClass`)**

`$classId` is `$classRow->ClId` (e.g. `'U18M'`). In PL's class setup (`lib.php`), `ClId` is always the first element of `ClValidClass` — so `EnAgeClass` and `EnClass` should be identical for an athlete registered in their natural class. This matches ianseo's own flow: `SelectAgeClass.php` reads `ClValidClass` after the user picks an age class, and the UI pre-selects the first option (= ClId) as the competition class.

Alternative considered: derive `EnClass` from `ClValidClass` separately. Unnecessary — since ClId === first(ClValidClass) in our setup, no lookup is needed.

## Files to Modify

| File | Change |
|---|---|
| `Import/Fun_BibImport.php` | Add `, EnAgeClass = $classId` to the INSERT in `pl_bibimport_create_entry()`. Update the `@param` docblock for `$classId` to mention it maps to both columns. |
| `openspec/specs/bib-import/spec.md` | Add requirement clarifying that Entry Creation writes both `EnClass` and `EnAgeClass`. |

## Risks / Trade-offs

- **Already-imported athletes** will have `EnAgeClass = ''`. The operator can fix individual records via the standard ianseo participant editor. A bulk SQL update is possible but out of scope. Risk is low — the fix prevents future occurrences.
- **`EnClass` vs `EnAgeClass` divergence** is only possible when an athlete competes "up" (e.g. a U18 competing as U21). BibImport registers athletes in their natural class, so this case doesn't arise here.
