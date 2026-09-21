## Why

The Sportzona lookup adapter's gender heuristic (`ends with "a"` → female, else male) misclassifies real athletes — confirmed live against the full 6,379-athlete registry. A misclassified `LueSex` flows into `pl_bibimport_resolve_class()`'s `ClSex` filter and assigns the wrong age/sex class at import, discovered only when the coach arrives on the field of play ([GitHub #61](https://github.com/open-archery/ianseo-polish-rules/issues/61)). Separately, 23 athletes have a two-given-name `firstName` joined by a comma (a Sportzona data-entry artifact, e.g. `"Artur,  Damian"`), which is fed whole into both `GivenName` and the gender heuristic today.

## What Changes

- Extract `sz_derive_gender()` out of `Lookup/SportzonaProxy.php` into a new pure, testable `Lookup/Fun_Gender.php` (`pl_derive_gender()`), mirroring the existing `Fun_ClubName.php` pattern.
- Add a static exception table checked before the "ends in a" fallback:
  - Female overrides (name doesn't end in "a"): Angeliki, Abigail, Ariel, Dinah, Elizabeth, Kendall, Madeleine, Miriam, Nelly, Nicole, Nikol, Noemi, Sophie, Vivienne, Zerin.
  - Male overrides (name ends in "a"): Barnaba, Bonawentura, Ilia, Illia, Jarema, Kosma, Kuba, Mykyta, Nikita.
- Add given-name normalization: when `firstName` contains a comma, use only the trimmed segment before the first comma — for both the emitted `GivenName` and as gender-heuristic input. Space-separated double given names (e.g. `"Jan Maciej"`) are unaffected — they are normal Polish naming convention, not a data artifact.
- Add `Lookup/GenderTest.php` covering every exception name and the comma-normalization cases.

## Capabilities

### Modified Capabilities

- `sportzona-lookup`: §4.4 Gender heuristic gains a static exception table checked before the "ends in a" rule; §4.2 Name fields gains comma-normalization for `firstName` before it is used as `GivenName` or as gender-heuristic input.

## Impact

- `Lookup/SportzonaProxy.php`: `sz_derive_gender()` removed, replaced by a call to the new `pl_derive_gender()`; `firstName` is normalized before being used for `GivenName`/`Gender`.
- New file `Lookup/Fun_Gender.php` + `Lookup/GenderTest.php`.
- No DB schema change. No change to `Import/Fun_BibImport.php` — it already just trusts `LueSex`.

## Non-goals

- Not fixing Sportzona's own data quality at the source (the comma-joined field) — normalizing on our side only.
- Not building a DB-backed/admin-editable exception list — a hardcoded table matches this module's existing `pl_club_word_map()` precedent and the list's size.
- Not attempting full name-based gender inference (e.g. a general Slavic-name classifier) — exception table only, operator review at registration remains the safety net per the existing spec.

Spec: Advisor agent. Design/code: Developer agent.
