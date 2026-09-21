## Context

See proposal.md - Why. The relevant code today:

- `Lookup/SportzonaProxy.php` — runs outside ianseo's session context (fetched via `%`-path HTTP GET, see `.github/agents/research/ianseo-internals.md` for the lookup mechanism). `sz_derive_gender(string $firstName): string` (lines 150-158) is a private, untested function inline in this file. It's called once per player at line 229 (`'Gender' => sz_derive_gender($firstName)`), and `$firstName` (unmodified) is also emitted as `'GivenName' => $firstName` at line 228.
- `Lookup/Fun_ClubName.php` — the existing precedent for this kind of fix: a pure-function file with a static lookup table (`pl_club_word_map()`) consulted before falling back to a generic rule, with its own `Lookup/ClubNameTest.php`. No ianseo bootstrap required, per its own doc comment.
- ianseo consumes the adapter's JSON output into `LookUpEntries` (`LueSex`, `LueName`), which `Import/Fun_BibImport.php`'s `pl_bibimport_resolve_class()` reads via `ClSex IN (-1, $lueSex)` to pick an age/sex class. `pl_bibimport_run()` also uses `LueFamilyName`/`LueName` for duplicate-name display but does not reparse or re-derive gender itself — it trusts `LueSex` as-is.

## Goals / Non-Goals

**Goals:**
- Make gender derivation testable without HTTP/DB stubbing (pure function, same shape as `Fun_ClubName.php`).
- Apply the exception table and comma-normalization identically wherever gender is derived — there's exactly one call site today, but the extraction prevents a second call site from drifting.

**Non-Goals:**
- No change to `Import/Fun_BibImport.php` — it already only consumes `LueSex`; nothing there needs to know how it was derived.
- No admin UI or DB table for the exception list (see proposal.md - Non-goals).
- No retroactive fix for already-imported `LookUpEntries`/`Entries` rows — this only affects the next Sportzona sync. Re-syncing (ianseo's normal lookup-table refresh) picks up the corrected values; existing tournament entries are unaffected until an operator re-imports.

## Decisions

**New file `Lookup/Fun_Gender.php`, not inline in `SportzonaProxy.php`.**
Matches `Fun_ClubName.php`'s precedent exactly: `SportzonaProxy.php` requires it at the top the same way it already requires `Fun_ClubName.php`, and the new `pl_derive_gender()` needs no ianseo bootstrap. Alternative considered: keep the function inline and just add the two arrays next to it — rejected because it perpetuates the untested state (no `SportzonaProxyTest.php` exists, and one can't easily exist: the file executes top-level HTTP-fetch-and-`echo` logic on require, per its own header comment "must NOT require config.php or call CheckTourSession()").

**Exception table is two flat `array` literals of lowercase names, not a single `[name => gender]` map.**
Keeps the "female exceptions" and "male exceptions" symmetric and readable at a glance (mirrors how a reviewer would want to audit/extend this list), rather than one map where the override direction is only visible by reading the value. Matching lowercases the input via `mb_strtolower(..., 'UTF-8')` (same helper already used in `sz_derive_gender()` today) and checks `in_array($lower, $list, true)`.

**Comma-splitting happens once, before both `GivenName` and `Gender` are computed** — a single `pl_first_given_name(string $rawFirstName): string` helper in the new `Fun_Gender.php`, called at the top of the per-player loop in `SportzonaProxy.php` (replacing the raw `$firstName` assignment), not duplicated at each of the two use sites. Alternative considered: normalize only for the gender call and leave raw text in `GivenName` — rejected per the proposal's explicit request to use the normalized name for both, since the comma is Sportzona field noise, not real data either field should reproduce.

**`pl_derive_gender()` normalizes internally is *not* required** — normalization and gender derivation are two separate pure functions (`pl_first_given_name()` then `pl_derive_gender()`), composed at the call site. Keeps each function single-purpose and independently testable, consistent with `Fun_ClubName.php`'s split between `pl_normalize_whitespace()`, `pl_parse_club_name()`, etc.

## Risks / Trade-offs

- **[Risk]** A borderline call (e.g. Ariel → female, Elia → left female-by-default) is wrong for one specific real athlete → **Mitigation**: this was already the documented behavior in spec.md §6 ("Gender heuristic is approximate... Operators must plan for a manual gender-review step") — this change narrows the error surface, it doesn't claim to eliminate it. No behavior change to the operator-review expectation.
- **[Risk]** The exception list drifts out of date as new athletes register → **Mitigation**: same maintenance model as `pl_club_word_map()` (a developer extends the array when a new case is reported); acceptable given the list's current size (24 names) and the low rate of new exceptions found (17 in a 6,379-athlete registry).
- **[Risk]** `pl_first_given_name()` incorrectly truncates a legitimate single given name that happens to contain a comma for an unrelated reason → **Mitigation**: not currently possible — Sportzona's `firstName` field has no legitimate use of a comma; all 23 comma-containing values in the live registry are the two-name artifact this change targets.

## Migration Plan

No data migration. Deploy is a plain code change: next time ianseo triggers a Sportzona lookup sync (ianseo core's own lookup-refresh mechanism calling the adapter), the corrected `Gender`/`GivenName` values populate `LookUpEntries`. Rollback is a plain revert — `LookUpEntries` is a cache table ianseo repopulates from the adapter, not a source of truth.
