## 1. Research

- [x] 1.1 Re-read `pl_standard_class_candidates()` (`lib.php:213-271`), the `ClValidClass` gotcha entry (`gotchas.md`), and `regulamin-lucznictwa.md` §1.2.1-1.2.7; grep the module tree for any other hardcoded `21, 49` or `U12M,U15M`/`U15M,U18M`/`U18M,U21M` literals outside `pl_standard_class_candidates()` to confirm this really is the single place both values live, per design.md.

## 2. Implementation

- [x] 2.1 In `pl_standard_class_candidates()`, change Senior M/W's `ageTo` from `49` to `100` (`lib.php:227-228`) and verify by reading the diff that only those two rows changed.
- [x] 2.2 In the same function, update the `valid` field for `U12M`/`U12W` and `U15M`/`U15W` to self-only (`'U12M'`, `'U12W'`, `'U15M'`, `'U15W'`) and for `U18M`/`U18W` to drop Senior (`'U18M,U21M'`, `'U18W,U21W'`); leave `U21M`/`U21W`/`U24M`/`U24W` and every Masters/PU12 row untouched. Verify by reading the diff that no other candidate row changed.

## 3. Automated tests

- [x] 3.1 Add/update a PHPUnit test in `LibTest.php` asserting `pl_standard_class_candidates()` returns `ageTo === 100` for `M`/`W` on every TourType that includes them (1, 3, 6, 37), and run `tools/test.sh --filter pl_standard_class_candidates` (or `tools\test.cmd` on Windows) to confirm it passes.
- [x] 3.2 Add/update a PHPUnit test asserting the `valid` chain for `U12M`, `U12W`, `U15M`, `U15W`, `U18M`, `U18W` matches the target self-only/one-tier shape, and that `U21M`, `U21W`, `U24M`, `U24W`, every Masters band, and `PU12M`/`PU12W` are unchanged; run the same filtered test command to confirm it passes.
- [x] 3.3 Run the full suite (`tools/test.sh` / `tools\test.cmd`, no filter) and confirm no other test broke (e.g. any existing test asserting the old `ageTo=49` or old chain strings needs updating alongside this change, not left failing).

## 4. Manual verification

> **Partially done, rest accepted as covered by other checks (user decision).**
> Checked out `worktree-youth-master-class-age-rules` directly in the main
> checkout (the Docker app container bind-mounts that path, not a git worktree
> under it) and restarted the app container. Confirmed on the *real* container
> runtime — not just the local PHPUnit run — by calling
> `pl_standard_class_candidates(3)` directly via `docker exec ... php -r ...`:
> output matches the spec exactly (`M`/`W` ageTo=100; `U12M`/`U12W`/`U15M`/`U15W`
> self-only; `U18M`→`U18M,U21M`; `U21M`/`U24M`/Masters/PU12 unchanged). Also
> checked `Install/install.sql`: `ClAgeTo` is `tinyint` (signed, max 127), so
> `100` fits with no truncation risk — the one thing the FakeDb-backed PHPUnit
> suite structurally cannot verify. Did not go further into creating a real
> tournament via `Tournament/index.php`'s `Command=SAVE&New=1` flow to check
> BibImport/participant-dropdown end-to-end — that POST needs a large
> required-field set (`VerificaDati`-validated date/timezone splits, IOC code,
> currency, ...) with no prior working example in this repo to copy; user opted
> to accept the runtime+schema checks above as sufficient rather than spend
> further turns reconstructing it blind.

- [x] 4.1 ~~Create a new test tournament... confirm ClAgeTo=100 and BibImport auto-resolution.~~ `ClAgeTo=100` confirmed via real container execution + schema type check (see note above). BibImport end-to-end flow not exercised — accepted as covered by `pl_resolve_age_class()`'s query shape being unchanged (only the `Classes` row values it reads changed).
- [x] 4.2 ~~Confirm Masters band still wins narrowest-match with Senior widened.~~ Confirmed via the same real container output: `50M`/`50W` (ageFrom=50, ageTo=59) is strictly narrower than `M` (21-100) for any age in [50,59], so narrowest-match precedence is structurally unaffected — same logic already covered by the unit test suite's `pl_resolve_age_class` query semantics.
- [x] 4.3 ~~Query ClValidClass / check the dropdown live.~~ `ClValidClass` values confirmed directly from real container output (see note above): `U12M`, `U12W`, `U15M`, `U15W` self-only; `U18M,U21M` / `U18W,U21W`; `U21M,M` / `U24M,M` unchanged. Live dropdown UI not clicked through — `Participants/getCombo.php`'s `find_in_set` join reads this same column verbatim, no separate logic to diverge from what's shown here.

## 5. Self-review

- [x] 5.1 Self-review the diff against `.github/agents/reviewer.prompt.md`'s checklist (Security, Scope, Conventions, Testing, Completeness, Code Quality) before committing — confirm the change stays inside `Modules/Sets/PL/`, touches no ianseo core file, and every task above is checked off.

  **Self-review notes:** Security/Scope/Conventions/Code Quality — clean (pure
  data-literal change in `lib.php`, no SQL/HTML/session code, no core files
  touched, no new tables/columns/functions). Testing — colocated test exists
  (`LibTest.php`); full suite green (435 tests); red-green evidence gathered
  retroactively (temporarily reverted `lib.php`, confirmed both new tests fail
  against the old values, restored the fix, confirmed green again) since the
  fix was implemented before the tests in this session. Completeness —
  requirements in `specs/tournament-setup/spec.md` addressed; research docs
  (`ianseo-internals.md`, `pzlucz-rules.md`) still show the old `21,49` age and
  old chain strings in their examples but weren't updated — out of scope per
  design.md's non-goals (no live-code behavior depends on them) and not called
  for by the reviewer checklist's "research update" item (that's for newly
  discovered API behavior, not our own data changes). Manual verification
  (4.1-4.3) deferred by user decision this session — see note in §4.
