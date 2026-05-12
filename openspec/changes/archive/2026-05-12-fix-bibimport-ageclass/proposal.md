## Why

After a BibImport run the participant list shows empty "class" and "subclass" fields for every imported athlete. The root cause is that `pl_bibimport_create_entry()` only sets `EnClass` in the `Entries` INSERT — but ianseo uses two separate columns: `EnAgeClass` (the age class shown in the UI) and `EnClass` (the competition class used for ranking). Leaving `EnAgeClass` unset means operators must open every athlete's record manually and re-assign the class after import, defeating the automation purpose of BibImport.

## What Changes

- `Fun_BibImport.php` — `pl_bibimport_create_entry()`: add `EnAgeClass = $classId` to the `Entries` INSERT, mirroring the existing `EnClass = $classId` line.
- `openspec/specs/bib-import/spec.md` — update the Entry Creation field table (§3, Step 5) to list `EnAgeClass` alongside `EnClass`.

## Capabilities

### New Capabilities

_(none — this is a bug fix in an existing capability)_

### Modified Capabilities

- `bib-import`: Step 5 of the processing spec (Entry creation) currently only lists `EnClass`. It must also specify that `EnAgeClass` is set to the same resolved class ID so the spec matches ianseo's actual two-column class model.

## Impact

- **`Import/Fun_BibImport.php`** — one additional line in `pl_bibimport_create_entry()`.
- **`openspec/specs/bib-import/spec.md`** — spec updated to reflect the correct DB schema.
- No schema changes, no new tables, no UI changes.
- No breaking changes. Athletes already imported will need a one-time manual fix (or a backfill query) — out of scope for this change.

## Non-goals

- Backfilling `EnAgeClass` for athletes imported before this fix.
- Setting `EnSubClass` — PL tournaments do not define any `SubClass` records, so leaving it blank is correct.
- Any change to class _resolution_ logic (`pl_bibimport_resolve_class`) — that is working correctly.
