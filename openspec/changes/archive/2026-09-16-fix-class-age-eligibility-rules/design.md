## Context

`pl_standard_class_candidates($TourType)` (`lib.php:213-271`) is the single
source of truth for every class ianseo creates in a PL tournament —
`CreateStandardClasses()`, `InsertStandardEvents()`, and every
`Setup_*_PL.php`'s own per-class distance/event/target-face loops all filter
down from this one array via `pl_class_in_preset()`. Each candidate row
carries `ageFrom`/`ageTo` (written to `Classes.ClAgeFrom`/`ClAgeTo`) and
`valid` (written to `Classes.ClValidClass`, the comma-separated upward-
eligibility chain `Participants/getCombo.php`'s class-reassignment dropdown
reads via `find_in_set`). See proposal.md for why both need to change.

Two consumers read these columns after class creation and must keep working
after the fix:
- `Import/Fun_BibImport.php`'s `pl_bibimport_resolve_class()` — narrowest
  `ClAgeFrom <= age <= ClAgeTo` match wins.
- ianseo core's `Participants/getCombo.php` "class" combo — offers every code
  in `ClValidClass` as an assignable class, joined by tournament (see
  gotchas.md: a chain must only name classes that actually exist in *that*
  tournament).

## Goals / Non-Goals

**Goals:**
- Senior M/W (`ageFrom=21, ageTo=127`) matches an archer of any adult age when
  no more specific class exists, on every TourType that has Senior (1, 3, 6, 37).
- `ClValidClass` chains for U12/U15/U18/U21/U24 match the target shape in
  proposal.md, with no change to Masters bands or PU12 (already correct).

**Non-Goals:**
- No change to the preset table (`pl_preset_table()`), sub-rule registration,
  or which classes exist per TourType/preset — only their age bounds and
  chain contents.
- No change to Masters band or PU12 `ageFrom`/`ageTo`/`valid` values.
- No retrofit of `Classes` rows in already-created tournaments — a live
  tournament keeps whatever `ClAgeTo`/`ClValidClass` it was created with;
  this only changes what new tournaments get.

## Decisions

**`ageTo=127` for Senior, not a literal "no cap" sentinel.** `ClAgeTo` is a
plain `tinyint` column (`Install/install.sql`) compared with `>=`; there's no
NULL/unbounded convention in this schema, and 127 is the highest value a
signed `tinyint` can hold without an `ALTER TABLE`. An earlier version of this
change used `100` (matching the value Masters' own 70+/80+ bands use for the
same "and older" concept, `lib.php:253`) — code review correctly pointed out
that `100` still contradicts the requirement's own "no upper bound" wording
(`pl_bibimport_resolve_class()`'s `ClAgeTo >= age` is literal, so an archer
aged 101+ wouldn't resolve). `127` isn't truly unbounded either, but it's the
schema's actual ceiling rather than an arbitrary round number, so there's no
cheaper way to get closer to PZŁucz's real "no upper bound" rule without a
migration. Masters' own bands keep `ageTo=100` (out of scope — see below).

**U18's chain becomes `'U18M,U21M'` (drop the trailing `,M`), not self-only.**
The domain owner confirmed in conversation that U18 may still opt up to U21
(consistent with the existing WA/FITA convention of moving up exactly one
age tier), but not straight to Senior. U12 and U15 lose their entire upward
chain (self-only), matching the pattern Masters/PU12 already use — U12/U15
become a parallel track, not a step in the main progression, which is also
consistent with `regulamin-lucznictwa.md` §1.2.1-1.2.7 defining each youth
category by its own upper age boundary rather than a cross-category ladder.

**No change to `pl_class_in_preset()`, `pl_division_has_classes()`, or any
preset table entry.** Both fixes are pure data changes inside
`pl_standard_class_candidates()`'s `$c[]` rows — every preset-filtering
function downstream reads whatever `ageFrom`/`ageTo`/`valid` the candidate
array hands it, with no logic of its own that assumes today's values.

**Not touching `40M`/`40W` Masters band's own upper bound (`ageTo=49`).**
That band is deliberately closed (proposal's non-goals) — only Senior's cap
is the leftover from before Masters existed; the Masters bands themselves
were already redesigned correctly in the prior change.

## Risks / Trade-offs

- **[Risk]** Widening Senior's age range could theoretically let a very old
  archer match Senior instead of a Masters band if band boundaries had gaps.
  → **Mitigation:** verified band coverage is contiguous from 40 through 100
  (40-49, 50-59, 60-69, 70-100, 80-100 — deliberately overlapping at the top,
  per `lib.php:249-252`'s own comment) — no gap exists between 21 and 100 for
  any age to fall through to a wider Senior match by accident. Ages 101-127
  have no Masters coverage at all (Masters bands keep their pre-existing
  `ageTo=100` cap, out of scope here) and fall through to Senior — this is a
  pre-existing Masters-band limitation, not something this change introduces,
  and resolving to Senior is strictly better than the prior behavior (no class
  at all above the old Senior/Masters ceiling).
- **[Risk]** A currently-live tournament (created before this fix) still has
  the old `ageTo=49`/wide chains baked into its `Classes` rows — this change
  only affects tournaments created afterward. → **Mitigation:** explicitly
  called out in proposal.md's Non-goals; no migration script proposed since
  retroactively editing a running tournament's class configuration is outside
  this change's scope and risks disrupting in-progress competitions.
- **[Risk]** Narrowing U12/U15/U18 chains changes what already-entered
  archers in a *new* tournament can be reassigned to via the entry-editor
  class dropdown, compared to tournaments created before this fix. →
  **Mitigation:** this is the intended behavior change (that's the bug being
  fixed); no data migration needed since it only affects class creation, not
  existing `Entries` rows.

## Migration Plan

No migration needed — this only changes what `CreateStandardClasses()` writes
for tournaments created after the fix ships. Existing tournaments are
unaffected (their `Classes` rows were already written with the old values and
are not touched).
