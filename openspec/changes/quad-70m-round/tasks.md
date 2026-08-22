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
- [x] 3.2 Manually create a TourType 3 tournament with sub-rule `Poland-Full` in a running ianseo instance — confirm behaviour is unchanged from before this change (72 arrows, 2 sessions, `tourDetNumDist=2`). — verified by calling `GetSetupFile()` directly against the dev container's DB (TourId 126, 0 entries) rather than a browser click-through: `TournamentDistances` came back byte-identical to the pre-change rows (`70m-1`/`70m-2` labels, no `a`/`b` suffixes). `Tournament.ToNumDist` itself couldn't be confirmed this way — see gotchas.md.
- [x] 3.3 Manually create a TourType 3 tournament with sub-rule `Poland-4x70m` — confirm distance/session structure. — same CLI method: `TournamentDistances` for TourId 126 came back exactly as designed for every class (RM/RW/RU24*/RU21*: 4×70m; RU18*/R50*: 4×60m; RU15*: 40m,40m,20m,20m; C%/B%: 4×50m). `Tournament.ToNumDist=4`/`ToTypeSubRule` not independently confirmed — the CLI harness crashes (pre-existing, see gotchas.md) before/at `UpdateTourDetails()`; needs a real browser create/reset to fully close out.
- [x] 3.4 Confirm elimination/finals configuration (first-phase cut counts, set/cumulative match format) is identical between the two sub-rules — no regression in `Poland-Full`'s existing elimination behaviour. — confirmed by inspection: the diff never touches `EvFinalFirstPhase`/`EvElimEnds`/`EvElimArrows`/`EvFinEnds`/`EvFinArrows`/`EvMatchMode`, all still literal constants independent of `$isDouble`.
- [ ] 3.5 Confirm `Poland-4x70m` does not appear as a selectable sub-rule when creating a TourType 1 or TourType 6 tournament. — verified by inspection (`sets.php` appends only to `$SetType['PL']['rules']['3']`); not confirmed in the tournament-creation dropdown UI.
- [x] 3.6 Run `tools/test.cmd` (or `tools/test.sh`) — full PHPUnit suite still passes. — 245 tests, 1077 assertions, all green (portable PHP 8.3 + `tools/phpunit.phar`).

## 4. Documentation

- [x] 4.1 If any non-obvious footgun surfaces during implementation (e.g. an ianseo quirk with sub-rule resolution or `CreateDistanceInformation()` behaviour), add an entry to `gotchas.md` in the same commit. — added: CLI-invoked `GetSetupFile()` exits 255 with no output even on the unmodified code path.

**Still open — needs a real browser session, not just CLI:** confirm `Tournament.ToNumDist`/`ToTypeSubRule` persist correctly through the actual tournament-creation UI (task 3.2/3.3's leftover), and confirm the sub-rule dropdown itself hides `Poland-4x70m` for types 1/6 (task 3.5).
