## 1. Fun_Gender.php

- [ ] 1.1 Create `Lookup/Fun_Gender.php` with `pl_first_given_name(string $rawFirstName): string` (split on first comma, trim, return unchanged if no comma) and verify it compiles/lints with no syntax errors
- [ ] 1.2 Add `pl_derive_gender(string $firstName): string` to `Fun_Gender.php`: check the female-exception list, then the male-exception list (case-insensitive), then fall back to the existing "ends with `a`" rule; return `'M'`/`'W'` matching `sz_derive_gender()`'s existing contract
- [ ] 1.3 Add the female-exception array (Angeliki, Abigail, Ariel, Dinah, Elizabeth, Kendall, Madeleine, Miriam, Nelly, Nicole, Nikol, Noemi, Sophie, Vivienne, Zerin) and male-exception array (Barnaba, Bonawentura, Ilia, Illia, Jarema, Kosma, Kuba, Mykyta, Nikita) as documented in specs/sportzona-lookup/spec.md

## 2. Wire into SportzonaProxy.php

- [ ] 2.1 Replace the `require_once __DIR__ . '/Fun_ClubName.php';` block with an additional `require_once __DIR__ . '/Fun_Gender.php';`, remove `sz_derive_gender()` (now superseded by `pl_derive_gender()`), and verify no other file references the removed function (`grep -r sz_derive_gender`)
- [ ] 2.2 In the per-player transform loop, normalize `$firstName` with `pl_first_given_name()` immediately after it's read from `$player->firstName`, before it's used for `GivenName` or passed to `pl_derive_gender()`, and verify both output fields reflect the normalized value for a comma-containing input

## 3. Tests

- [ ] 3.1 Create `Lookup/GenderTest.php` (pure-function tests, no DB/HTTP stubbing needed) covering: every female-exception name, every male-exception name, an unlisted ends-in-"a" name (still female), an unlisted non-"a" name (still male), and case-insensitivity for at least one exception
- [ ] 3.2 Add `pl_first_given_name()` cases to the same test file: `"Artur,  Damian"` → `"Artur"`, `"Marcin,Artur"` → `"Marcin"`, `"Józef,"` → `"Józef"`, `"Jan Maciej"` → `"Jan Maciej"` (unchanged, no comma)
- [ ] 3.3 Run `tools\test.cmd --filter Gender` (or `tools/test.sh --filter Gender` in the container) and verify all new tests pass, then run the full suite (`tools\test.cmd`) and verify no existing test regressed

## 4. Gotchas

- [ ] 4.1 If anything during implementation costs real debugging time and isn't obvious from reading the code (e.g. an `mb_strtolower` / UTF-8 collation surprise in the exception match), add an entry to `gotchas.md` in the same commit, per CLAUDE.md
