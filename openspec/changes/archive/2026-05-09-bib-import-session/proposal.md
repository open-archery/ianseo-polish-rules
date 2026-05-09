## Why

Athletes imported via BibImport have no `Qualifications` row created, so they
are invisible in session and target management until an operator manually edits
each athlete one-by-one to assign a session. The correct workflow is:
session setup → athlete import → target draw. The import must produce
Qualifications rows so the draw step can proceed immediately.

## What Changes

- Add a **session dropdown** to the BibImport form (Q-type sessions only,
  ordered by session number).
- Block the import form with a warning when no Q-type sessions exist for the
  tournament.
- For each imported athlete, `INSERT INTO Qualifications (QuId, QuSession)`
  immediately after the Entries row is created, inside the same transaction.
- Session selection is **mandatory** — no "unassigned" option is offered.

## Capabilities

### New Capabilities

_(none — this change modifies an existing capability)_

### Modified Capabilities

- `bib-import`: Import now requires a session selection and creates a
  Qualifications row per athlete; the import is blocked when no Q sessions exist.

## Non-goals

- Target and letter assignment (`QuTarget`, `QuLetter`) — that remains the
  target draw step.
- Creating or editing sessions — the operator must set up sessions before importing.
- Importing to Elimination or Finals sessions.

## Impact

- `Import/BibImport.php` — new session dropdown, session guard, validation
- `Import/Fun_BibImport.php` — `pl_bibimport_run()` gains `$session` param;
  `pl_bibimport_create_entry()` returns `EnId`; new
  `pl_bibimport_create_qualification()` helper
