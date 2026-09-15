## 1. Research

- [ ] 1.1 Re-read `pl_standard_class_candidates()` (`lib.php:213-271`), the `ClValidClass` gotcha entry (`gotchas.md`), and `regulamin-lucznictwa.md` §1.2.1-1.2.7; grep the module tree for any other hardcoded `21, 49` or `U12M,U15M`/`U15M,U18M`/`U18M,U21M` literals outside `pl_standard_class_candidates()` to confirm this really is the single place both values live, per design.md.

## 2. Implementation

- [ ] 2.1 In `pl_standard_class_candidates()`, change Senior M/W's `ageTo` from `49` to `100` (`lib.php:227-228`) and verify by reading the diff that only those two rows changed.
- [ ] 2.2 In the same function, update the `valid` field for `U12M`/`U12W` and `U15M`/`U15W` to self-only (`'U12M'`, `'U12W'`, `'U15M'`, `'U15W'`) and for `U18M`/`U18W` to drop Senior (`'U18M,U21M'`, `'U18W,U21W'`); leave `U21M`/`U21W`/`U24M`/`U24W` and every Masters/PU12 row untouched. Verify by reading the diff that no other candidate row changed.

## 3. Automated tests

- [ ] 3.1 Add/update a PHPUnit test in `LibTest.php` asserting `pl_standard_class_candidates()` returns `ageTo === 100` for `M`/`W` on every TourType that includes them (1, 3, 6, 37), and run `tools/test.sh --filter pl_standard_class_candidates` (or `tools\test.cmd` on Windows) to confirm it passes.
- [ ] 3.2 Add/update a PHPUnit test asserting the `valid` chain for `U12M`, `U12W`, `U15M`, `U15W`, `U18M`, `U18W` matches the target self-only/one-tier shape, and that `U21M`, `U21W`, `U24M`, `U24W`, every Masters band, and `PU12M`/`PU12W` are unchanged; run the same filtered test command to confirm it passes.
- [ ] 3.3 Run the full suite (`tools/test.sh` / `tools\test.cmd`, no filter) and confirm no other test broke (e.g. any existing test asserting the old `ageTo=49` or old chain strings needs updating alongside this change, not left failing).

## 4. Manual verification

- [ ] 4.1 Create a **new** test tournament (per memory: never reuse an existing one) of TourType 3 under the `SetSeniorClass` preset (no Masters classes), confirm via DB query that the `M`/`W` `Classes` rows have `ClAgeTo = 100`, and confirm an archer aged 55 auto-resolves to `M`/`W` through the BibImport age-class flow instead of landing in the class-unresolved list.
- [ ] 4.2 In a TourType 3 tournament created under `SetAllClass` (both Senior and Masters classes present), confirm an archer aged 55 still auto-resolves to the `50M`/`50W` Masters band, not Senior — narrowest-range match is unaffected by the wider Senior ceiling.
- [ ] 4.3 In any tournament with U12/U15/U18/U21/U24 classes, query `Classes.ClValidClass` (or check the participant-entry class-reassignment dropdown) and confirm: U12 and U15 offer only themselves, U18 offers itself plus U21 (not Senior), and U21/U24 still offer themselves plus Senior.

## 5. Self-review

- [ ] 5.1 Self-review the diff against `.github/agents/reviewer.prompt.md`'s checklist (Security, Scope, Conventions, Testing, Completeness, Code Quality) before committing — confirm the change stays inside `Modules/Sets/PL/`, touches no ianseo core file, and every task above is checked off.
