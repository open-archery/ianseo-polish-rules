## Context

BibImport currently creates only `Entries` rows. ianseo's standard athlete
registration flow (via `PopEdit.php`) always creates a matching `Qualifications`
row immediately after the `Entries` insert. Without a `Qualifications` row,
imported athletes are invisible in session/target management and cannot proceed
to the target draw step.

The workflow order is: **session setup → athlete import → target draw.**
Sessions therefore exist before import runs, making it safe to require a session
selection at import time.

## Goals / Non-Goals

**Goals:**
- Add a mandatory session dropdown (Q-type only) to the import form.
- Create a `Qualifications (QuId, QuSession)` row per athlete inside the existing
  transaction.
- Block the form when no Q sessions exist for the tournament.

**Non-Goals:**
- Target/letter assignment (`QuTarget`, `QuLetter`) — remains the draw step.
- Elimination or Finals session support.
- Retroactively creating Qualifications rows for athletes imported before this change.

## Decisions

### D1 — Session selection is mandatory; no "unassigned" option
Allowing QuSession=0 was the previous implicit state; it caused invisible-athlete
bugs. Forcing a real session at import time eliminates the problem at the source.
A "no session" option would just recreate the bug with an extra click.

### D2 — Only Q sessions shown in dropdown
Athletes are imported before the qualification round. E/F/T sessions have no
meaning at this stage. Filtering `SesType = 'Q'` prevents operator error.

### D3 — Qualifications row inserted inside the same transaction
The `Entries` insert and the `Qualifications` insert are either both committed
or both rolled back. This matches ianseo's internal consistency model and
prevents orphaned entry rows.

### D4 — `pl_bibimport_create_entry()` returns the new `EnId`
Currently the function returns `void`. Changing the return type to `int`
(the result of `safe_w_last_id()` called inside the function) lets the caller
immediately insert the Qualifications row without an extra query.

Alternatively, `safe_w_last_id()` could be called in `pl_bibimport_run()` after
the entry insert. Returning EnId from the function is marginally cleaner because
it keeps DB state reading adjacent to the INSERT.

### D5 — Session guard mirrors existing Sportzona warning pattern
The Sportzona warning (empty LookUpEntries) already shows a banner and
conditionally hides the form. The no-sessions guard uses the same pattern for
visual and code consistency.

## Files Modified

| File | Change |
|---|---|
| `Import/BibImport.php` | Load Q sessions; render session dropdown; guard; pass session to `pl_bibimport_run()` |
| `Import/Fun_BibImport.php` | `pl_bibimport_create_entry()` returns `int` (EnId); new `pl_bibimport_create_qualification()`; `pl_bibimport_run()` gains `$session` param |

## Risks / Trade-offs

- **Existing imported athletes have no Qualifications rows** → those athletes
  still need manual editing. Out of scope, but operators should be aware.
- **Session deleted after import** → Qualifications rows with a stale QuSession
  value would remain. No mitigation needed; this is an operator workflow concern,
  not a code concern.

## Open Questions

_(none — design is fully resolved from the explore conversation)_
