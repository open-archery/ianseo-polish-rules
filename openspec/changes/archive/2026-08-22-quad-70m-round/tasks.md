## 1. Sub-rule registration

- [x] 1.1 In `sets.php`, change the uniform `foreach` that assigns `array('Poland-Full')` to every allowed type so it still applies to all of them, then append `'Poland-4x70m'` to `$SetType['PL']['rules']['3']` only (types 1 and 6 keep just `Poland-Full`).

## 2. `Setup_3_PL.php` — session doubling

- [x] 2.1 Add `$isDouble = ($SubRule === 'Poland-4x70m');` near the top of the script (after `$TourType = 3;`).
- [x] 2.2 Add the local `pl_double_legs($legs, $isDouble)` helper (see design.md Decision 2) that repeats each leg into two consecutively-labelled legs when `$isDouble` is true, and returns `$legs` unchanged otherwise.
- [x] 2.3 Wrap the distances argument of every `CreateDistanceNew()` call (RM, RW, RU24M, RU24W, RU21M, RU21W, RU18M, RU18W, R50M, R50W, RU15M, RU15W, `C%`, `B%` — 13 calls total) in `pl_double_legs(..., $isDouble)`.
- [x] 2.4 Change `$tourDetNumDist` from the literal `'2'` to `$isDouble ? '4' : '2'`.
- [x] 2.5 Change `$DistanceInfoArray` construction to double via `array_merge($DistanceInfoArray, $DistanceInfoArray)` when `$isDouble` is true (see design.md Decision 3). Leave `$tourDetMaxDistScore` at `'360'` unchanged.

## 3. Verification

- [x] 3.1 Run `grep -n "CreateDistanceNew(" Setup_3_PL.php` and confirm every match's distances argument is wrapped in `pl_double_legs(`. — all 13 confirmed wrapped.
- [x] 3.2 Manually create a TourType 3 tournament with sub-rule `Poland-Full` in a running ianseo instance — confirm behaviour is unchanged from before this change (72 arrows, 2 sessions, `tourDetNumDist=2`). — verified via CLI (`GetSetupFile()` against dev DB): `TournamentDistances` byte-identical to pre-change rows.
- [x] 3.3 Manually create a TourType 3 tournament with sub-rule `Poland-4x70m` — confirm distance/session structure. — confirmed twice: CLI verification against a scratch DB, then for real via the user's own tournament (129 / `MKZLZS26`) through the actual tournament-creation UI. That real-UI pass caught a genuine bug the CLI method masked: `Setup_3_PL.php` compared `$SubRule` (a raw 1-based dropdown position, not the rule name) instead of `$subRuleName` — distances never doubled through the real form even though the dropdown and `Tournament.ToTypeSubRule` were correct. Fixed; `TournamentDistances` now shows `70m-1..70m-4` / `40m-1,40m-2,20m-1,20m-2` / `50m-1..50m-4` correctly after a `[[TourRuleReset]]` re-run on 129. `Tournament.ToNumDist=4` itself still not explicitly re-checked after the fix.
- [x] 3.4 Confirm elimination/finals configuration (first-phase cut counts, set/cumulative match format) is identical between the two sub-rules — no regression in `Poland-Full`'s existing elimination behaviour. — confirmed by inspection: the diff never touches `EvFinalFirstPhase`/`EvElimEnds`/`EvElimArrows`/`EvFinEnds`/`EvFinArrows`/`EvMatchMode`, all still literal constants independent of `$isDouble`.
- [x] 3.5 Confirm `Poland-4x70m` does not appear as a selectable sub-rule when creating a TourType 1 or TourType 6 tournament. — confirmed via the UI dropdown (user-verified).
- [x] 3.6 Run `tools/test.cmd` (or `tools/test.sh`) — full PHPUnit suite still passes. — 245 tests, 1077 assertions, all green (portable PHP 8.3 + `tools/phpunit.phar`), re-run after the `$subRuleName` fix too.

## 4. Documentation

- [x] 4.1 If any non-obvious footgun surfaces during implementation (e.g. an ianseo quirk with sub-rule resolution or `CreateDistanceInformation()` behaviour), add an entry to `gotchas.md` in the same commit. — added two: the CLI-invoked `GetSetupFile()` exit-255 artifact, and the `$SubRule`-is-a-dropdown-position footgun that caused the real bug above.
