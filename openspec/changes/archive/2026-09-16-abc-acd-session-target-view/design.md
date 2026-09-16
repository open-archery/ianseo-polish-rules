## Context

See `proposal.md` - Why/What Changes for motivation. Relevant existing pieces:

- ianseo core's `createAvailableTargetSQL($session, $tourId)` (`Common/Globals.inc.php`, already loaded by the time any page reaches `config.php`) expands `Session.SesFirstTarget`/`SesTar4Session`/`SesAth4Target` into every valid `(Session, Target, Letter)` slot for a session — the exact source ianseo core itself uses for "which targets exist" (see `Partecipants/FindRedTarget.php`, `Partecipants-exp/actions/xmlGetFreeTargets.php`, `Partecipants/CheckTargetNo.php`). This module reuses it rather than re-deriving boss/letter capacity from scratch.
- `Targets/SetTargetABCACD.php` already renders a per-club color palette (`$palette`, a fixed hex array) for its existing per-run preview table. That palette is keyed by club and is not reused here — see proposal Non-goals.
- The page's session dropdown (`GetSessions('Q')`) lists every qualification session regardless of `SesAth4Target`; only the assignment form itself validates `SesAth4Target=4`.

## Goals / Non-Goals

**Goals:**
- Reuse core's `createAvailableTargetSQL()` as the source of truth for which bosses exist in the session.
- Render the grid independent of the assignment form's own fields/validation.
- Keep the grid's data query read-only and cheap (one query joining the core slot-list against `Qualifications`/`Entries`/`Divisions`/`Classes`).

**Non-Goals:**
- The grid does not validate or block on `SesAth4Target=4` the way the assignment form does — it is a read-only report and renders whatever letters the session actually has (A only, A–D, etc.), independent of whether ABC/ACD assignment is even applicable to that session.
- No interaction/click-to-assign from the grid — display only.

## Decisions

### Reuse `createAvailableTargetSQL()` instead of re-deriving boss/letter capacity
Calling the core function (already available globally once `config.php` is loaded, same as `safe_r_sql` etc.) keeps this module's notion of "which targets exist" identical to core's own, so it can never drift out of sync with how core itself computes free targets elsewhere.

**Alternative considered:** query `Session.SesFirstTarget`/`SesTar4Session`/`SesAth4Target` directly in a PL-module function and re-derive the slot list locally. Rejected — pure duplication of logic core already exposes as a function, with no PL-specific twist needed.

### One query, grouped in PHP by boss number
`SELECT` the core slot list `LEFT JOIN`ed to `Qualifications`/`Entries`/`Divisions`/`Classes` (same join shape as `pl_abc_acd_load_athletes`, minus the club grouping) to get, per occupied slot, its `CONCAT(EnDivision,EnClass)` label. In PHP: bucket rows by boss number, collect the distinct labels present on each boss's occupied letters. Zero distinct labels → free. One distinct label → that boss belongs to that division/class (regardless of how many of its letters are actually filled — a partially-filled single-class boss is normal, not mixed). More than one distinct label → mixed, rendered per its own requirement.

**Alternative considered:** reuse `pl_abc_acd_load_athletes`/the assignment preview's per-slot data directly. Rejected — that function is scoped to one `Event` filter and one session's *matched* athletes; the grid needs the *whole* session regardless of any filter, so it needs its own unfiltered query.

### Run-length encoding produces the colspan groups; mixed bosses are always singleton spans
After computing each boss's label (including "free"), a single left-to-right pass merges consecutive bosses sharing the identical label into one colspan group — except a "mixed" boss never merges with a neighbor even if the neighbor happens to compute an identical combined label string, keeping every anomaly individually visible rather than occasionally hidden by a coincidental string match.

### Separate color palette, keyed by label and assigned in encounter order
A second fixed hex-color array (distinct from the existing per-club `$palette`), indexed by first-seen order of each distinct division/class label within one page render — mirroring the existing club-palette assignment pattern (`$palette[$i % count($palette)]`) but keyed by label, not club code. The "free" label and "mixed" cells are not colored from this array at all: free gets one constant neutral color, mixed gets the striped/hatched treatment (CSS background pattern) called for in the spec, so neither competes with the rotating label palette.

## Risks / Trade-offs

- **A session with many bosses produces a wide table** (e.g. 40+ columns) → acceptable; matches how the existing per-run preview table already lists every boss vertically, just reoriented horizontally here. No pagination/collapsing planned.
- **Mixed-boss detection depends on saved `EnDivision`/`EnClass` staying consistent with `QuTarget`/`QuLetter`** → both already come from the same `Entries`/`Qualifications` join used elsewhere on this page; no new consistency risk introduced.

## Migration Plan

No data migration; purely additive read-only rendering. Rollback is a plain revert.
